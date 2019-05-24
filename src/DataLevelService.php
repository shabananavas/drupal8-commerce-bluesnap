<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;
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
  public function level2Data(OrderInterface $order) {
    $level_2_data = [];
    $level_2_data['customerReferenceNumber'] = $this->getCustomerReferenceNumber($order);
    $level_2_data['salesTaxAmount'] = $this->getAdjustment($order, 'tax');
    return $level_2_data;
  }

  /**
   * {@inheritdoc}
   */
  public function level3Data(OrderInterface $order) {
    $level_3_data = $this->level2Data($order, $card_type);
    $level_3_data['freightAmount'] = $this->getAdjustment($order, 'shipping');
    $level_3_data['taxAmount'] = $this->getAdjustment($order, 'tax');
    $level_3_data['taxRate'] = $this->getTaxRate($order);
    $level_3_data['discountAmount'] = $this->getAdjustment($order, 'promotion');
    $shipping_info = $this->getShippingInfo($order, 'promotion');
    if (!empty($shipping_info)) {
      $level_3_data = $level_3_data + $shipping_info;
    }
    return $level_3_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerReferenceNumber(OrderInterface $order) {
    return $order->getCustomerId();
  }

  /**
   * {@inheritdoc}
   */
  public function getAdjustment(OrderInterface $order, $type) {
    if (empty($order->collectAdjustments([$type]))) {
      return;
    }

    // Loop through each tax component to get the total
    // tax amount.
    $total_price = NULL;
    foreach ($order->collectAdjustments([$type]) as $tax) {
      if ($total_price === NULL) {
        $total_price = $tax->getAmount();
        continue;
      }
      $total_price = $total_price->add($tax->getAmount());
    }

    // Make promotion number to be a positive integer.
    if ($type == "promotion") {
      return -($total_price->getNumber());
    }

    return $total_price->getNumber();
  }

  /**
   * {@inheritdoc}
   */
  public function getTaxRate(OrderInterface $order) {
    if (empty($order->collectAdjustments(['tax']))) {
      return;
    }

    // Loop through each tax component to get the total
    // tax rate.
    $total_tax_tate = 0;
    foreach ($order->collectAdjustments(['tax']) as $tax) {
      $total_tax_tate += $tax->getPercentage();
    }

    return $total_tax_tate;
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingInfo(OrderInterface $order) {
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

}
