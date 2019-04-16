<?php

namespace Drupal\commerce_bluesnap;

use Bluesnap\VaultedShopper;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields;

use Bluesnap\Bluesnap;
use Bluesnap\CardTransaction;
use Bluesnap\HostedPaymentFieldsToken;
use Exception;
use stdClass;

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
    $data = HostedPaymentFieldsToken::create();

    return $data['hosted_payment_fields_token'];
  }

  /**
   * Creates a new payment transaction on the BlueSnap gateway.
   *
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   */
  public function createTransaction(array $data) {
    try {
      $response = CardTransaction::create($data);

      if ($response->failed()) {
        $error = $response->data;
        return [];
      }

      $transaction = $response->data;

      return $transaction;
    }
    catch (Exception $e) {
      ksm($e);
    }
  }

  public function createVaultedShopper(stdClass $data) {
    $response = VaultedShopper::create($data);

    if ($response->failed()) {
      $error = $response->data;
      return [];
    }

    $transaction = $response->data;

    return $transaction;
  }

  public function updateVaultedShopper($vaulted_shopper_id, stdClass $data) {
    $response = VaultedShopper::update($vaulted_shopper_id, $data);

    if ($response->failed()) {
      $error = $response->data;
      return [];
    }

    $transaction = $response->data;

    return $transaction;
  }

}
