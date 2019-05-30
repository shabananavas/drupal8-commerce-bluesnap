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
  public function iframe($mode) {
    $url = FraudSessionInterface::API_URL_PRODUCTION;
    if ($mode == "sandbox") {
      $url = FraudSessionInterface::API_URL_SANDBOX;
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
