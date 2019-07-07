<?php

namespace Drupal\commerce_bluesnap\EnhancedData;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Default implementation of the enhanced data service.
 */
class Data implements DataInterface {

  /**
   * {@inheritdoc}
   */
  public function getData(OrderInterface $order, $card_type) {
    $output = [];

    // Get the blueSnap enhanced data level.
    // Proceed only if enhanced data is set in either store or product variation
    // config.
    $data_level = $this->dataLevel($order);
    if (!$data_level) {
      return $output;
    }

    // Build and return the data depending on the configured level.
    $is_level_3 = $data_level === DataInterface::LEVEL_3_ID;
    if ($is_level_3 && $this->cardTypeSupportsLevel3($card_type)) {
      $output['level3Data'] = $this->level3Data($order, $card_type);
      return $output;
    }
    $is_level_2 = $data_level === DataInterface::LEVEL_2_ID;
    if ($is_level_2 && $this->cardTypeSupportsLevel2($card_type)) {
      $output['level2Data'] = $this->level2Data($order, $card_type);
      return $output;
    }

    return $output;
  }

  /**
   * Returns the data level for the given order.
   *
   * Enhanced data levels are configured either at the store or at the product
   * variation level. If the data level is set at the store level, transactions
   * for orders belonging to the store will contain enhanced data with minimum
   * level that of the store. If data levels are additionally configured for one
   * or more of the purchased entities for the order items, the level may be
   * increased if at least one variation requires so.
   *
   * If enhanced data are not configured to be required at the store level,
   * transactions for orders belonging to the store will not contain enhanced
   * data unless required by one or more of the purchased entities of the
   * order.
   *
   * Examples:
   * - Store determines Level 2. No purchased entity determines a level. Result
   *   is Level 2.
   * - Store determines Level 2. At least one purchased entity determines level
   *   3. Result is Level 3.
   * - Store does not determine a level. No purchased entity determines a
   *   level. Result is no enhanced data sent.
   * - Store does not determine a level. At least one purchased entity
   *   determines Level 2. Result is Level 2.
   * - Store does not determine a level. Some purchased entities determine Level
   *   2 and one purchased entity determines Level 3. Result is Level 3.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object for which we are checking the enhanced data status.
   *
   * @return string|null
   *   The data level, NULL if none could be determined for the order.
   */
  protected function dataLevel(OrderInterface $order) {
    $level = NULL;
    $store = $order->getStore();

    if ($store->hasField('bluesnap_config')) {
      $config_field = $store->get('bluesnap_config');
      if (!$config_field->isEmpty()) {
        $store_settings = $config_field->value['enhanced_data'];
      }
      if (isset($store_settings) && $store_settings['status']) {
        $level = $store_settings['level'];
      }
    }

    // No higher level than 3; if the store determines level 3 then we include
    // level 3 data for all orders belonging to the store regardless of the
    // settings at the purchased entity level.
    if ($level === DataInterface::LEVEL_3_ID) {
      return $level;
    }

    // Otherwise, if no level is determined at the store level or we have level
    // 2, we check if we have settings requiring a higher level for any of the
    // purchased entities for the order items.
    foreach ($order->getItems() as $order_item) {
      if (!$order_item->hasPurchasedEntity()) {
        continue;
      }

      $purchased_entity = $order_item->getPurchasedEntity();

      // Not all variation types can define their own enhanced data
      // configuration. Purchased entities may also be of other types than
      // product variations, such as product bundles.
      if (!$purchased_entity->hasField('bluesnap_config_enhanced_data')) {
        continue;
      }

      // Get enhanced data settings for product.
      $item_settings_field = $purchased_entity->get('bluesnap_config_enhanced_data');
      if ($item_settings_field->isEmpty()) {
        continue;
      }

      $item_settings = $item_settings_field->value;
      if (!$item_settings['status']) {
        continue;
      }

      // If at least one item requires level 3, nothing more to do. We determine
      // level 3 for the order.
      if ($item_settings['level'] === DataInterface::LEVEL_3_ID) {
        return DataInterface::LEVEL_3_ID;
      }

      // Otherwise, the level must already be level 2 (in which case there's
      // nothing to do here - move on to the next item), or it must not be
      // previously set (in which case we set it to level 2 that is the level we
      // require here).
      if (!$level) {
        $level = $item_settings['level'];
      }
    }

    return $level;
  }

  /**
   * Returns whether the given card type supports data level 3.
   *
   * @param string $card_type
   *   The card type to check.
   *
   * @return array
   *   Array of level 3 supported card types.
   */
  protected function cardTypeSupportsLevel3($card_type) {
    return in_array($card_type, ['mastercard', 'visa']);
  }

  /**
   * Returns whether the given card type supports data level 2.
   *
   * @param string $card_type
   *   The card type to check.
   *
   * @return array
   *   Array of level 2 supported card types.
   */
  protected function cardTypeSupportsLevel2($card_type) {
    return in_array($card_type, ['mastercard', 'visa', 'amex']);
  }

  /**
   * Prepares the Level 2 data for transaction processing.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object for which we are preparing the data.
   * @param string $card_type
   *   The type of the card used for the transaction.
   *
   * @return array
   *   Array of level 2 data.
   */
  protected function level2Data(OrderInterface $order, $card_type) {
    $data = [];

    $data['customerReferenceNumber'] = $this->getCustomerReferenceNumber($order);

    if ($card_type != 'amex') {
      return $data;
    }

    // Amex Level 2 with TAA (Transaction Advice Addendum) requires.
    // destinationZipCode along with level2Data.
    $shipping_info = $this->getShippingInfo($order);
    if ($shipping_info) {
      $data['destinationZipCode'] = $shipping_info['destinationZipCode'];
    }

    // This is an Amex-specific level that contains item data (such as item
    // description and quantity) in addition to Level 2 fields. Only
    // lineItemTotal, description, itemQuantity are required.
    $data['level3DataItem'] = $this->level3DataItems($order, $card_type);

    return $data;
  }

  /**
   * Prepares the Level 2 data for transaction processing.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object for which we are preparing the data.
   * @param string $card_type
   *   The type of the card used for the transaction.
   *
   * @return array
   *   Array of level 3 data.
   */
  protected function level3Data(OrderInterface $order, $card_type) {
    // Level 3 data contains level2 data and other data items.
    $data = $this->level2Data($order, $card_type);

    $freight_amount = $this->getOrderAdjustment($order, 'shipping');
    if ($freight_amount) {
      $data['freightAmount'] = $freight_amount;
    }

    $tax = $this->getOrderAdjustment($order, 'tax');
    if ($tax) {
      $data['taxAmount'] = $tax;
    }

    $tax_rate = $this->getOrderTaxRate($order);
    if ($tax_rate) {
      $data['taxRate'] = $tax_rate;
    }

    $promotion = $this->getOrderAdjustment($order, 'promotion');
    if ($promotion) {
      $data['discountAmount'] = $promotion;
    }

    $shipping_info = $this->getShippingInfo($order);
    if ($shipping_info) {
      $data = $data + $shipping_info;
    }

    $data['level3DataItems'] = $this->level3DataItems($order, $card_type);

    return $data;
  }

  /**
   * Provides the reference number used by shopper to track order.
   *
   * We use the order ID for that purpose. We could/should be using the order
   * number, but there is a limitation of 17 characters and the order number
   * might exceed that limit if it is customized; the order ID wouldn't.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return string
   *   Order customer reference number.
   */
  protected function getCustomerReferenceNumber(OrderInterface $order) {
    return $order->id();
  }

  /**
   * Provides order adjustment total amount.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $type
   *   Adjustment type.
   *
   * @return int|null
   *   Adjusment total amount and null if no adjustment found.
   */
  protected function getOrderAdjustment(OrderInterface $order, $type) {
    $adjustments = $order->collectAdjustments([$type]);

    if (empty($adjustments)) {
      return;
    }

    return $this->getAdjustmentTotal($adjustments);
  }

  /**
   * Provides sum of tax rates associated with an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return int|null
   *   Sum of tax rates and null if no tax adjustment.
   */
  protected function getOrderTaxRate(OrderInterface $order) {
    $tax_adjustments = $order->collectAdjustments(['tax']);

    if (!$tax_adjustments) {
      return;
    }

    return $this->getTaxRateTotal($tax_adjustments);
  }

  /**
   * Provides shipping country code and zip code.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return array|null
   *   Array of shipping country and zip code, null if no shipping info.
   */
  protected function getShippingInfo(OrderInterface $order) {
    // Nothing to do if the order does not have shipments.
    if (!$order->hasField('shipments')) {
      return;
    }
    $shipments_field = $order->get('shipments');
    if ($shipments_field->isEmpty()) {
      return;
    }

    $data = [];

    foreach ($shipments_field->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile()
        ->get('address')
        ->first();

      $data['destinationZipCode'] = $shipping_profile->getPostalCode();
      $data['destinationCountryCode'] = $shipping_profile->getCountryCode();
    }

    return $data;
  }

  /**
   * Provides BlueSnap level 3 data items for transaction processing.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $card_type
   *   The card type.
   *
   * @return array
   *   Array of level 3 data items.
   */
  protected function level3DataItems(OrderInterface $order, $card_type) {
    $output = [];

    // Loop through order items and generate the level3 data array.
    foreach ($order->getItems() as $key => $order_item) {
      $output[$key] = $this->getBasicDataItems($order_item);

      // For amex cards only basic data items are required.
      if ($card_type === 'amex') {
        continue;
      }

      // For cards other than amex we need items data other than
      // the basic one.
      $output[$key] = $output[$key] + $this->getDataItems($order_item);
    }

    return $output;
  }

  /**
   * Provides basic BlueSnap level 3 data items for transaction processing.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order object.
   *
   * @return array
   *   Array of level 3 basic data items.
   */
  protected function getBasicDataItems(OrderItemInterface $order_item) {
    // Line item data.
    $output['lineItemTotal'] = $this->getTotal($order_item);
    $output['description'] = $order_item->getTitle();
    $output['itemQuantity'] = $order_item->getQuantity();

    return $output;
  }

  /**
   * Provides order item total excluding the tax and promotion adjustment.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order item object.
   *
   * @return int
   *   Line item total price excluding the tax and promotion adjustment.
   */
  protected function getTotal(OrderItemInterface $order_item) {
    $total_price = $order_item->getTotalPrice();

    // Subtract the tax and promotion adjustments from line item total
    // if they are included in base price.
    foreach ($order_item->getAdjustments(['tax', 'promotion']) as $adjustment) {
      if ($adjustment->isIncluded()) {
        $total_price = $total_price->subtract($adjustment->getAmount());
      }
    }

    return $total_price->getNumber();
  }

  /**
   * Provides BlueSnap level 3 data items for transaction processing.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order object.
   *
   * @return array
   *   Array of level 3 data items.
   */
  protected function getDataItems(OrderItemInterface $order_item) {
    $output['unitCost'] = $order_item->getUnitPrice()->getNumber();

    // Purchased product data.
    $purchased_entity = $order_item->getPurchasedEntity();
    $output['productCode'] = $purchased_entity->getSku();

    // Discount amount applied to transaction.
    $promotion = $this->getOrderItemAdjustment($order_item, 'promotion');
    if ($promotion) {
      $output['discountAmount'] = -($promotion);

      // Set discount indicator to No, as the total line item cost we pass is
      // not discounted value.
      $output['discountIndicator'] = 'N';
    }

    // Total tax/VAT amount for transaction.
    $tax = $this->getOrderItemAdjustment($order_item, 'tax');
    if ($tax) {
      $output['taxAmount'] = $tax;

      // Set grossNetIndicator as no, as tax amount is not
      // included in the total line item cost we pass.
      $output['grossNetIndicator'] = 'N';
    }

    // Tax/VAT rate applied to transaction.
    $tax_rate = $this->getOrderItemTaxRate($order_item);
    if ($tax_rate) {
      $output['taxRate'] = $tax_rate;
    }

    return $output;
  }

  /**
   * Provides order item adjustment total amount.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order item object.
   * @param string $type
   *   Adjustment type.
   *
   * @return int|null
   *   Adjusment total amount.
   */
  protected function getOrderItemAdjustment(OrderItemInterface $order_item, $type) {
    $adjustments = $order_item->getAdjustments([$type]);
    if (!$adjustments) {
      return;
    }

    return $this->getAdjustmentTotal($adjustments);
  }

  /**
   * Provides sum of tax rates associated with an order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order object.
   *
   * @return int|null
   *   Sum of tax rates, null if no tax adjustment found.
   */
  protected function getOrderItemTaxRate(OrderItemInterface $order_item) {
    $adjustments = $order_item->getAdjustments(['tax']);

    if (!$adjustments) {
      return;
    }

    return $this->getTaxRateTotal($adjustments);
  }

  /**
   * Provides sum of adjustments.
   *
   * @param array $adjustments
   *   Array of adjustments.
   *
   * @return int
   *   Sum of adjustments.
   */
  protected function getAdjustmentTotal(array $adjustments) {
    $total_price = NULL;

    // Loop through each adjustment component to get the total
    // adjustment amount.
    foreach ($adjustments as $adjustment) {
      if ($total_price === NULL) {
        $total_price = $adjustment->getAmount();
        continue;
      }
      $total_price = $total_price->add($adjustment->getAmount());
    }

    return $total_price->getNumber();
  }

  /**
   * Provides sum of tax rates.
   *
   * @param array $adjustments
   *   Array of adjustments.
   *
   * @return string|null
   *   Sum of tax rates, NULL if no percentage tax found
   */
  protected function getTaxRateTotal(array $adjustments) {
    $total_tax_rate = NULL;

    // Loop through each tax adjustment to get the total
    // tax rate.
    foreach ($adjustments as $tax) {
      $percentage = $tax->getPercentage();

      // If even just one tax adjustment is not calculated as a percentage, then
      // the whole tax is not considered a percentage. Do not pass the tax rate
      // property to BlueSnap.
      if ($percentage === NULL) {
        return;
      }

      $total_tax_rate += $tax->getPercentage();
    }

    return $total_tax_rate;
  }

}
