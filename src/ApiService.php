<?php

namespace Drupal\commerce_bluesnap;

use Bluesnap\Bluesnap;
use Bluesnap\CardTransaction;
use Bluesnap\AltTransaction;
use Bluesnap\HostedPaymentFieldsToken;
use Bluesnap\Refund;
use Bluesnap\VaultedShopper;

use Exception;

use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\Ach;
use Psr\Log\LoggerInterface;

/**
 * A utility service providing functionality related to Commerce BlueSnap.
 */
class ApiService {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    LoggerInterface $logger
  ) {
    $this->logger = $logger;
  }

  /**
   * Initialize the BlueSnap client.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   */
  public function initializeBlueSnap(OnsiteBase $payment_gateway) {
    // Initialize BlueSnap.
    Bluesnap::init(
      $payment_gateway->getEnvironment(),
      $payment_gateway->getUsername(),
      $payment_gateway->getPassword()
    );
  }

  /**
   * Creates and returns a BlueSnap Hosted Payment Fields token.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   *
   * @return string
   *   The unique token from BlueSnap.
   */
  public function getHostedPaymentFieldsToken(HostedPaymentFields $payment_gateway) {
    $this->initializeBlueSnap($payment_gateway);
    $data = HostedPaymentFieldsToken::create();

    return $data['hosted_payment_fields_token'];
  }

  /**
   * Creates a new payment transaction on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function createTransaction(HostedPaymentFields $payment_gateway, array $data) {
    $this->initializeBlueSnap($payment_gateway);
    $response = CardTransaction::create($data);

    if ($response->failed()) {
      $this->logger->log('error', $response->data);
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Creates a new alternative payment transaction on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\Ach $payment_gateway
   *   The payment gateway we're using.
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function createAltTransaction(Ach $payment_gateway, array $data) {
    $this->initializeBlueSnap($payment_gateway);
    $response = AltTransaction::create($data);
    if ($response->failed()) {
      $this->logger->log('error', $response->data);
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Updates an existing payment transaction on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param int $transaction_id
   *   The BlueSnap transaction ID.
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function updateTransaction(HostedPaymentFields $payment_gateway, $transaction_id, array $data) {
    $this->initializeBlueSnap($payment_gateway);
    $response = CardTransaction::update($transaction_id, $data);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Refund an existing transaction on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param int $transaction_id
   *   The BlueSnap transaction ID.
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function refundTransaction(HostedPaymentFields $payment_gateway, $transaction_id, array $data) {
    $this->initializeBlueSnap($payment_gateway);
    $response = Refund::update($transaction_id, $data);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Creates a new vaulted shopper on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param array $data
   *   An array of shopper data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function createVaultedShopper(HostedPaymentFields $payment_gateway, array $data) {
    $this->initializeBlueSnap($payment_gateway);
    $response = VaultedShopper::create($data);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Adds a new card to an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array of card data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function addCardToVaultedShopper(HostedPaymentFields $payment_gateway, $vaulted_shopper_id, array $data) {
    $this->initializeBlueSnap($payment_gateway);

    // Fetch the vaulted shopper and add the card details.
    $vaulted_shopper = $this->getVaultedShopper($payment_gateway, $vaulted_shopper_id);
    $vaulted_shopper->paymentSources = [
      'creditCardInfo' => [
        $data,
      ],
    ];

    // Update the vaulted shopper on BlueSnap with the new card.
    $response = VaultedShopper::update($vaulted_shopper_id, $vaulted_shopper);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Deletes a card from an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array with the details of the card that needs to be deleted.
   *
   * @return mixed
   *   The response returned from BlueSnap, if it was a success.
   *
   * @throws \Exception
   */
  public function deleteCardFromVaultedShopper(HostedPaymentFields $payment_gateway, $vaulted_shopper_id, array $data) {
    $this->initializeBlueSnap($payment_gateway);

    // Fetch the vaulted shopper and remove the card from their payment sources.
    $vaulted_shopper = $this->getVaultedShopper($payment_gateway, $vaulted_shopper_id);

    // Go through all the cards of this user and if we find a matching one,
    // set the status of the card to delete.
    $payment_sources = $vaulted_shopper->paymentSources->creditCardInfo;
    $card_found = FALSE;
    foreach ($payment_sources as $key => $payment_source) {
      $card = $payment_source->creditCard;
      if (
        $card->cardLastFourDigits === $data['cardLastFourDigits']
        && $card->expirationMonth === $data['expirationMonth']
        && $card->expirationYear === $data['expirationYear']
      ) {
        $card_found = TRUE;
        $vaulted_shopper->paymentSources->creditCardInfo[$key]->status = 'D';
      }
    }

    // Just return if we don't have a matching card.
    if (!$card_found) {
      return;
    }

    // Update the vaulted shopper on BlueSnap with the deleted card.
    $response = VaultedShopper::update($vaulted_shopper_id, $vaulted_shopper);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Fetch an existing vaulted shopper from the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields $payment_gateway
   *   The payment gateway we're using.
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function getVaultedShopper(HostedPaymentFields $payment_gateway, $vaulted_shopper_id) {
    $this->initializeBlueSnap($payment_gateway);
    $response = VaultedShopper::get($vaulted_shopper_id);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

}
