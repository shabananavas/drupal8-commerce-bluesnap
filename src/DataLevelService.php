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
  public function buildDataLevelSettingsForm(StoreInterface $store) {
    $form = [];

    // Get bluesnap data level settings.
    $settings = $this->getDataLevelSetting($store);

    $form['bluesnap_data_level_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Bluesnap data level settings'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];
    $form['bluesnap_data_level_settings']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable bluesnap level 2/3 data processing'),
      '#default_value' => $settings ? $settings->status : '0',
    ];
    $form['bluesnap_data_level_settings']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Bluesnap data processing level'),
      '#options' => [
        'level_2' => $this->t('Level 2'),
        'level_3' => $this->t('Level 3'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="bluesnap_data_level_settings[status]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      '#default_value' => $settings ? $settings->type : 'level_2',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataLevelSetting(StoreInterface $store) {
    $settings = $store->get('bluesnap_data_level_settings')->value;
    $settings = json_decode($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(OrderInterface $order, $card_type) {
    $store = $order->getStore();
    $output = [];
    // Get data level settings for store.
    $settings = $this->getDataLevelSetting($store);
    if (!($settings->status)) {
      return $output;
    }

    // Check the data level setting and
    // return level2 or level3 data.
    $data_type = $settings->type;
    if ($data_type == 'level_3' && in_array($card_type, $this->getCardSupportLevel3())) {
      $output['level3Data'] = $this->level3Data($order, $card_type);
      return $output;
    }
    if ($data_type == 'level_2' && in_array($card_type, $this->getCardSupportLevel2())) {
      $output['level2Data'] = $this->level2Data($order, $card_type);
      return $output;
    }
    return $output;
  }

  /**
   * Provides the card types which supports data level 3.
   *
   * @return array
   *   Array of level 3 supported card types.
   */
  private function getCardSupportLevel3() {
    return ['mastercard', 'visa'];
  }

  /**
   * Provides the card types which supports data level 2.
   *
   * @return array
   *   Array of level 2 supported card types.
   */
  private function getCardSupportLevel2() {
    return ['mastercard', 'visa', 'amex'];
  }

  /**
   * Provides bluesnap level2 data for transaction processing.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $card_type
   *   The card type.
   *
   * @return array
   *   Array of level 2 data.
   */
  private function level2Data(OrderInterface $order, $card_type) {
    $level_2_data = [];
    $level_2_data['customerReferenceNumber'] = $this->getCustomerReferenceNumber($order);

    if ($sales_tax = $this->getOrderAdjustment($order, 'tax')) {
      $level_2_data['salesTaxAmount'] = $sales_tax;
    }

    if ($card_type != 'amex') {
      return $level_2_data;
    }

    // Amex Level 2 with TAA (Transaction Advice Addendum) requires.
    // destinationZipCode along with level2Data.
    if ($shipping_info = $this->getShippingInfo($order)) {
      $level_2_data['destinationZipCode'] = $shipping_info['destinationZipCode'];
    }

    // This is an Amex-specific
    // level that contains item data (such as item description and quantity)
    // in addition to Level 2 fields. Only lineItemTotal, description,
    // itemQuantity are required.
    $level_2_data['level3DataItem'] = $this->level3DataItems($order, $card_type);
    return $level_2_data;
  }

  /**
   * Provides bluesnap level3 data for transaction processing.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $card_type
   *   The card type.
   *
   * @return array
   *   Array of level 3 data.
   */
  private function level3Data(OrderInterface $order, $card_type) {
    $level_3_data = $this->level2Data($order, $card_type);

    if ($freight_amount = $this->getOrderAdjustment($order, 'shipping')) {
      $level_3_data['freightAmount'] = $freight_amount;
    }

    if ($tax = $this->getOrderAdjustment($order, 'tax')) {
      $level_3_data['taxAmount'] = $tax;
    }

    if ($tax_rate = $this->getOrderTaxRate($order)) {
      $level_3_data['taxRate'] = $tax_rate;
    }

    if ($promotion = $this->getOrderAdjustment($order, 'promotion')) {
      $level_3_data['discountAmount'] = $promotion;
    }

    $shipping_info = $this->getShippingInfo($order);
    if (!empty($shipping_info)) {
      $level_3_data = $level_3_data + $shipping_info;
    }

    $level_3_data['level3DataItems'] = $this->level3DataItems($order, $card_type);
    return $level_3_data;
  }

  /**
   * Provides customer reference number of an order.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return string
   *   Order customer reference number.
   */
  private function getCustomerReferenceNumber(OrderInterface $order) {
    return $order->getCustomerId();
  }

  /**
   * Provides order adjustment total amount.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $type
   *   Adjustment type.
   *
   * @return int|null
   *   Adjusment total amount and null if no adjustment found.
   */
  private function getOrderAdjustment(OrderInterface $order, $type) {
    if (empty($order->collectAdjustments([$type]))) {
      return;
    }
    return $this->getAdjustmentTotal($order->collectAdjustments([$type]));
  }

  /**
   * Provides sum of tax rates associated with an order.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return int|null
   *   Sum of tax rates and null if no tax adjustment.
   */
  private function getOrderTaxRate(OrderInterface $order) {
    if (empty($order->collectAdjustments(['tax']))) {
      return;
    }
    return $this->getTaxRateTotal($order->collectAdjustments(['tax']));
  }

  /**
   * Provides shipping country code and zip code.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return array|null
   *   Array of shipping country and zip code, null if no shipping info.
   */
  private function getShippingInfo(OrderInterface $order) {
    if (!$order->hasField('shipments')) {
      return;
    }

    $data = [];
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile()->get('address')->first();
      $data['destinationZipCode'] = $shipping_profile->getPostalCode();
      $data['destinationCountryCode'] = $shipping_profile->getCountryCode();
    }
    return $data;
  }

  /**
   * Provides bluesnap level3 data items for transaction processing.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $card_type
   *   The card type.
   *
   * @return array
   *   Array of level 3 data items.
   */
  private function level3DataItems(OrderInterface $order, $card_type) {
    $output = [];
    // Loop through order items and generate the level3 data array.
    foreach ($order->getItems() as $key => $order_item) {

      // Line item data.
      $output[$key]['lineItemTotal'] = $order_item->getTotalPrice()->getNumber();
      $output[$key]['description'] = $order_item->getTitle();
      $output[$key]['itemQuantity'] = $order_item->getQuantity();

      // For amex cards, only lineItemTotal, description,
      // itemQuantity are required.
      if ($card_type == 'amex') {
        continue;
      }

      $output[$key]['unitCost'] = $order_item->getUnitPrice()->getNumber();
      // Purchased product data.
      $purchased_entity = $order_item->getPurchasedEntity();
      $output[$key]['commodityCode'] = $purchased_entity->getSku();
      $output[$key]['productCode'] = $purchased_entity->getSku();

      // Discount Info.
      if ($promotion = $this->getOrderItemAdjustment($order_item, 'promotion')) {
        $output[$key]['discountAmount'] = -($promotion);
        // Set discount indicator as no, as the line item
        // cost we pass is not discounted value.
        $output[$key]['discountIndicator'] = 'N';
      }

      // Tax Info.
      if ($tax = $this->getOrderItemAdjustment($order_item, 'tax')) {
        $output[$key]['taxAmount'] = $tax;
        // Set grossNetIndicator as no, as tax amount is not
        // included in the line item cost we pass.
        $output[$key]['grossNetIndicator'] = 'N';
      }

      // Tax Rate Info.
      if ($tax_rate = $this->getOrderItemTaxRate($order_item)) {
        $output[$key]['taxRate'] = $tax_rate;
      }
    }
    return $output;
  }

  /**
   * Provides order item adjustment total amount.
   *
   * @param Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order item object.
   * @param string $type
   *   Adjustment type.
   *
   * @return int|null
   *   Adjusment total amount.
   */
  private function getOrderItemAdjustment(OrderItemInterface $order_item, $type) {
    $adjustments = $order_item->getAdjustments([$type]);
    if (empty($adjustments)) {
      return;
    }
    return $this->getAdjustmentTotal($adjustments);
  }

  /**
   * Provides sum of tax rates associated with an order item.
   *
   * @param Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order object.
   *
   * @return int|null
   *   Sum of tax rates, null if no tax adjustment found.
   */
  private function getOrderItemTaxRate(OrderItemInterface $order_item) {
    $adjustments = $order_item->getAdjustments(['tax']);
    if (empty($adjustments)) {
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
  private function getAdjustmentTotal(array $adjustments) {
    // Loop through each adjustment component to get the total
    // adjustment amount.
    $total_price = NULL;
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
  private function getTaxRateTotal(array $adjustments) {
    // Loop through each tax adjustment to get the total
    // tax rate.
    $total_tax_rate = 0;
    foreach ($adjustments as $tax) {
      $total_tax_rate += $tax->getPercentage();
    }

    return $total_tax_rate;
  }

}
