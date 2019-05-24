<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Bluesnap data level service class.
 *
 * Provides level 2 and level 3 transaction data.
 */
class DataLevelService {

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
          ':input[name="bluesnap_data_level_settings[bluensnap_data_level_status]"]' => [
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
    $settings = $store->get('bluensnap_data_level_settings')->value;
    $settings = json_decode($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(OrderInterface $order) {
    $store = $order->getStore();
    $output = [];
    // Get data level settings for store.
    $settings = $this->getDataLevelSetting($store);
    if (!($settings->status)) {
      return [];
    }

    // Check the data level setting and
    // return level2 or level3 data.
    $data_type = $settings->type;
    if ($data_type == 'level_3') {
      $output['level3Data'] = $this->level3Data($order, $card_type);
      return $output;
    }
    $output['level2Data'] = $this->level2Data($order, $card_type);
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  private function level2Data(OrderInterface $order) {
    $level_2_data = [];
    $level_2_data['customerReferenceNumber'] = $this->getCustomerReferenceNumber($order);

    if ($sales_tax = $this->getOrderAdjustment($order, 'tax')) {
      $level_2_data['salesTaxAmount'] = $sales_tax;
    }
    return $level_2_data;
  }

  /**
   * {@inheritdoc}
   */
  private function level3Data(OrderInterface $order) {
    $level_3_data = $this->level2Data($order);

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

    $shipping_info = $this->getShippingInfo($order, 'promotion');
    if (!empty($shipping_info)) {
      $level_3_data = $level_3_data + $shipping_info;
    }

    $level_3_data = $level_3_data + $this->level3DataItems($order);
    return $level_3_data;
  }

  /**
   * {@inheritdoc}
   */
  private function getCustomerReferenceNumber(OrderInterface $order) {
    return $order->getCustomerId();
  }

  /**
   * {@inheritdoc}
   */
  private function getOrderAdjustment(OrderInterface $order, $type) {
    if (empty($order->collectAdjustments([$type]))) {
      return;
    }
    return $this->getAdjustmentTotal($order->collectAdjustments([$type]));
  }

  /**
   * {@inheritdoc}
   */
  private function getOrderTaxRate(OrderInterface $order) {
    if (empty($order->collectAdjustments(['tax']))) {
      return;
    }
    return $this->getTaxRateTotal($order->collectAdjustments(['tax']));
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  private function level3DataItems(OrderInterface $order) {
    $output = [];
    // Loop through order items and generate the level3 data array.
    foreach ($order->getItems() as $key => $order_item) {

      // Line item data.
      $output[$key]['lineItemTotal'] = $order_item->getTotalPrice()->getNumber();
      $output[$key]['unitCost'] = $order_item->getUnitPrice()->getNumber();
      $output[$key]['description'] = $order_item->getTitle();
      $output[$key]['itemQuantity'] = $order_item->getQuantity();

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
   * {@inheritdoc}
   */
  private function getOrderItemAdjustment(OrderItemInterface $order_item, $type) {
    $adjustments = $order_item->getAdjustments([$type]);
    if (empty($adjustments)) {
      return;
    }
    return $this->getAdjustmentTotal($adjustments);
  }

  /**
   * {@inheritdoc}
   */
  private function getOrderItemTaxRate($order_item) {
    $adjustments = $order_item->getAdjustments(['tax']);
    if (empty($adjustments)) {
      return;
    }
    return $this->getTaxRateTotal($adjustments);
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  private function getTaxRateTotal(array $adjustments) {
    // Loop through each tax adjustment to get the total
    // tax rate.
    $total_tax_tate = 0;
    foreach ($adjustments as $tax) {
      $total_tax_tate += $tax->getPercentage();
    }

    return $total_tax_tate;
  }

}
