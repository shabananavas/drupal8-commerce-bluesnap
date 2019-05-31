<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service class for handling fraud prevention in bluesnap transactions.
 */
class KountAccount implements KountAccountInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(StoreInterface $store) {
    $form = [];

    // Get bluesnap data level settings.
    $settings = $this->getSettings($store);

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Kount settings'),
      '#open' => FALSE,
    ];
    $form['settings']['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kount merchant ID'),
      '#default_value' => $settings ? $settings->merchant_id : '0',
      '#description' => $this->t("
        If you are using Kount Enterprise, Please
        provide kount merchant ID.
        Leave empty to use BlueSnap's Kount Merchant ID.
      "),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(StoreInterface $store) {
    $settings = $store->get('bluesnap_settings')->value;
    $settings = json_decode($settings);

    return $settings->kount->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getKountMerchantId(StoreInterface $store) {
    $settings = $this->getSettings($store);

    return $settings->merchant_id;
  }

}
