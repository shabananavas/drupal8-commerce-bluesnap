<?php

namespace Drupal\commerce_bluesnap;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Service class for handling fraud prevention in bluesnap transactions.
 */
class FraudSession implements FraudSessionInterface {

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
   * Returns the fraud session ID.
   *
   * A new ID will be generated if none exists yet.
   *
   * @return string
   *   Bluesnap fraud session ID
   */
  public function get() {
    $this->privateTempStore->get('fraud_session_id');
    if (!$this->privateTempStore->get('fraud_session_id')) {
      $this->privateTempStore->set('fraud_session_id', $this->generate());
    }

    return $this->privateTempStore->get('fraud_session_id');
  }

  /**
   * Generates bluesnap fraud session ID.
   *
   * @return string
   *   Bluesnap fraud session ID
   */
  public function generate() {
    return bin2hex(openssl_random_pseudo_bytes(16));
  }

  /**
   * Removes fraud session ID from user temp storage.
   */
  public function remove() {
    $this->privateTempStore->delete('fraud_session_id');
  }

  /**
   * Provides bluesnap device datacollector iframe.
   *
   * @param string $mode
   *   The bluesnap exchange rate API mode, test or production.
   *
   * @return array
   *   Render array which has bluesnap device datacollector iframe markup.
   */
  public function iframe($mode) {
    $url = 'https://www.bluesnap.com';
    if ($mode == "test") {
      $url = 'https://sandbox.bluesnap.com';
    }
    return [
      '#type' => 'inline_template',
      '#template' => '
        <iframe
          width="1"
          height="1"
          frameborder="0"
          scrolling="no"
          src="{{ url }}/servlet/logo.htm?s={{ fraud_session_id }}">
          <img width="1" height="1" src="{{ url }}"/servlet/logo.gif?s>
        </iframe>',
      '#context' => [
        'url' => $url,
        'fraud_session_id' => $this->get(),
      ],
    ];
  }

}
