<?php

namespace Drupal\commerce_bluesnap;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Client for making requests to the Card/Wallet Transactions API.
 */
class FraudSession {

  /**
   * The shared temporary storage for commerce_bluesnap.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * Constructs a FraudSession object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStore = $temp_store_factory->get('commerce_bluesnap');
  }

  /**
   * Returns fraud session id
   *
   * @return string
   *   Bluesnap fraud session ID
   */
  public function get() {
    if (!$this->tempStore->get('fraud_session_id')) {
      $this->tempStore->set('fraud_session_id', $this->generate());
    }
    return $this->tempStore->get('fraud_session_id');
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
    $this->tempStore->delete('fraud_session_id');
  }

}
