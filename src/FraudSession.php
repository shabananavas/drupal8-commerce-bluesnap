<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service class for handling fraud prevention in bluesnap transactions.
 */
class FraudSession implements FraudSessionInterface {

  use StringTranslationTrait;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStore;

  /**
   * Constructs a FraudSession object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_tempstore
   *   The private temp store factory.
   */
  public function __construct(PrivateTempStoreFactory $private_tempstore) {
    $this->privateTempStore = $private_tempstore->get('commerce_bluesnap');
  }

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

  /**
   * {@inheritdoc}
   */
  public function get() {
    $session_id = $this->privateTempStore->get('fraud_session_id');
    if (!$session_id) {
      $session_id = $this->generate();
      $this->privateTempStore->set('fraud_session_id', $session_id);
    }

    return $session_id;
  }

  /**
   * {@inheritdoc}
   */
  public function generate() {
    return bin2hex(openssl_random_pseudo_bytes(16));
  }

  /**
   * {@inheritdoc}
   */
  public function remove() {
    $this->privateTempStore->delete('fraud_session_id');
  }

  /**
   * {@inheritdoc}
   */
  public function iframe($mode, StoreInterface $store) {
    $url = FraudSessionInterface::API_URL_PRODUCTION;
    if ($mode == "sandbox") {
      $url = FraudSessionInterface::API_URL_SANDBOX;
    }

    $kount_merchant_id = $this->getKountMerchantID($store);
    if (empty($kount_merchant_id)) {
      $kount_merchant_id = FraudSessionInterface::KOUNT_MERCHANT_ID;
    }

    return [
      '#type' => 'inline_template',
      '#template' => '
        <iframe
          width="1"
          height="1"
          frameborder="0"
          scrolling="no"
          src="{{ url }}/servlet/logo.htm?s={{ fraud_session_id }}&m={{ merchant_id}}">
          <img width="1" height="1" src="{{ url }}"/servlet/logo.gif?s={{ fraud_session_id }}&m={{ merchant_id}}>
        </iframe>',
      '#context' => [
        'url' => $url,
        'fraud_session_id' => $this->get(),
        'merchant_id' => $kount_merchant_id,
      ],
    ];
  }

}
