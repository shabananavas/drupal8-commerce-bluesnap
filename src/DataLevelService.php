<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Bluesnap data level service class.
 *
 * Bluesnap's Enhanced data levels, such as Level 2 and Level 3,
 * require extra information to process the transaction .
 * This service provides a settings form to configure the data
 * level in store and methods for getting
 * level 2 or level 3 data depending on the
 * card type and store settings.
 */
class DataLevelService implements DataLevelServiceInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(StoreInterface $store) {
    $form = [
      '#type' => 'details',
      '#title' => $this->t('Data level settings'),
      '#open' => TRUE,
    ];

    // Build the form elements.
    $settings = $this->getSettings($store);
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable BlueSnap level 2/3 data processing'),
      '#default_value' => $settings ? $settings->status : FALSE,
    ];
    $form['level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Data processing level'),
      '#options' => [
        self::LEVEL_2_ID => $this->t('Level 2'),
        self::LEVEL_3_ID => $this->t('Level 3'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="bluesnap[data_level][settings][status]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      '#default_value' => $settings ? $settings->type : self::LEVEL_2_ID,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(StoreInterface $store) {
    $settings = $store->get('bluesnap_settings')->value;
    $settings = json_decode($settings);

    return $settings->data_level;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(OrderInterface $order, $card_type) {
    $store = $order->getStore();
    $output = [];

    // Get data level settings for store.
    $settings = $this->getSettings($store);
    if (!$settings->status) {
      return $output;
    }

    // Build and return the data depending on the configured level.
    $data_level = $settings->level;
    $is_level_3 = $data_level === self::LEVEL_3_ID;
    if ($is_level_3 && $this->cardTypeSupportsLevel3($card_type)) {
      $output['level3Data'] = $this->level3Data($order, $card_type);
      return $output;
    }
    $is_level_2 = $data_level === self::LEVEL_2_ID;
    if ($is_level_2 && $this->cardTypeSupportsLevel2($card_type)) {
      $output['level2Data'] = $this->level2Data($order, $card_type);
      return $output;
    }

    return $output;
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
    return in_array(['mastercard', 'visa', 'amex']);
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
   * @return int
   *   Sum of tax rates.
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
