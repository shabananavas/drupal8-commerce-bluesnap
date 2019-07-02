<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

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
  public function iframe($mode, $kount_merchant_id = NULL) {
    $url = FraudSessionInterface::API_URL_PRODUCTION;
    if ($mode == "sandbox") {
      $url = FraudSessionInterface::API_URL_SANDBOX;
    }

    // Prepare the iframe template. The query parameters passed to the iframe's
    // and image's src tags depend on whether we have a custom merchant ID
    // (enterprise accounts) or not. In the latter case we do not
    // pass a merchant ID and BlueSnap will use a default merchant ID.
    $params = $this->iframeParams($kount_merchant_id);
    $iframe = '
      <iframe
        width="1"
        height="1"
        frameborder="0"
        scrolling="no"
        src="{{ url }}/servlet/logo.htm?{{ params }}">
        <img width="1" height="1" src="{{ url }}/servlet/logo.gif?{{ params }}">
      </iframe>
    ';

    return [
      '#type' => 'inline_template',
      '#template' => $iframe,
      '#context' => [
        'url' => $url,
        'params' => $params,
      ],
    ];
  }

  /**
   * Prepares the query parameters for the fraud session iframe.
   *
   * It includes the Kount merchant ID if we have one, it only includes the
   * fraud session otherwise.
   *
   * @param string|null $kount_merchant_id
   *   The Kount merchant Id, if we have one (enterprise accounts); NULL
   *   otherwise.
   *
   * @return string
   *   The query parameters string as required for the iframe template.
   */
  protected function iframeParams($kount_merchant_id = NULL) {
    // Add fraud session ID to the param.
    $params = 's=' . $this->get();

    // Append merchant ID to param if we have a kount merchant ID.
    if ($kount_merchant_id) {
      $params .= '&m=' . $kount_merchant_id;
    }

    return $params;
  }

}
