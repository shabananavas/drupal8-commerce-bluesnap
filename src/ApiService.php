<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields;

use Bluesnap\Bluesnap;
use Bluesnap\HostedPaymentFieldsToken;

/**
 * A utility service providing functionality related to Commerce BlueSnap.
 */
class ApiService {

  /**
   * Initialized the BlueSnap client.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   */
  public function initializeBlueSnap(HostedPaymentFields $payment_gateway) {
    // Initialize BlueSnap.
    Bluesnap::init(
      $payment_gateway->getEnvironment(),
      $payment_gateway->getUsername(),
      $payment_gateway->getPassword()
    );
  }

  /**
   * Creates and returns a Hosted Payment Fields token.
   *
   * @return string
   *   The unique token from BlueSnap.
   */
  public function getHostedPaymentFieldsToken() {
    return HostedPaymentFieldsToken::create();
  }

}
