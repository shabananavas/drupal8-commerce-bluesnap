<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service class for handling fraud prevention in bluesnap transactions.
 */
class KountAccount implements KountAccountInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(FormStateInterface $form_state) {
    $store = $form_state->getFormObject()->getEntity();
    $form = [
      '#type' => 'details',
      '#title' => $this->t('Kount settings'),
      '#open' => FALSE,
    ];

    // Build the form element for the Kount merchant ID.
    $settings = $this->getSettings($store);
    $form['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kount merchant ID'),
      '#default_value' => $settings ? $settings->merchant_id : NULL,
      '#description' => $this->t("
        If you are using Kount Enterprise, please provide your Kount merchant ID.
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
    if (empty($settings->kount)) {
      return;
    }

    return $settings->kount;
  }

  /**
   * {@inheritdoc}
   */
  public function getMerchantId(StoreInterface $store) {
    $settings = $this->getSettings($store);
    if (empty($settings)) {
      return;
    }

    return $settings->merchant_id;
  }

}
