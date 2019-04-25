<?php

namespace Drupal\commerce_bluesnap;

use Bluesnap\VaultedShopper as BlueSnapVaultedShopper;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase;
use Exception;

/**
 * A utility service providing functionality related to vaulted shopper.
 */
class VaultedShopper {

  /**
   * Constructor.
   */
  public function __construct(ApiService $api_service) {
    $this->apiService = $api_service;
  }

  /**
   * Creates a new vaulted shopper on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase $payment_gateway
   *   The payment gateway we're using.
   * @param array $data
   *   An array of shopper data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function createVaultedShopper(OnsiteBase $payment_gateway, array $data) {
    $this->apiService->initializeBlueSnap($payment_gateway);
    $response = BlueSnapVaultedShopper::create($data);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Adds a new payment to an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase $payment_gateway
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
  public function addPaymentToVaultedShopper(OnsiteBase $payment_gateway, $vaulted_shopper_id, array $data) {
    $this->apiService->initializeBlueSnap($payment_gateway);
    // Fetch the vaulted shopper and add the card details.
    $vaulted_shopper = $this->getVaultedShopper($payment_gateway, $vaulted_shopper_id);
    $vaulted_shopper->paymentSources = $data;

    // Update the vaulted shopper on BlueSnap with the new card.
    $response = BlueSnapVaultedShopper::update($vaulted_shopper_id, $vaulted_shopper);
    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Deletes a card from an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase $payment_gateway
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
  public function deleteCardFromVaultedShopper(OnsiteBase $payment_gateway, $vaulted_shopper_id, array $data) {
    $this->apiService->initializeBlueSnap($payment_gateway);

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
    $response = BlueSnapVaultedShopper::update($vaulted_shopper_id, $vaulted_shopper);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Fetch an existing vaulted shopper from the BlueSnap gateway.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase $payment_gateway
   *   The payment gateway we're using.
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   */
  public function getVaultedShopper(OnsiteBase $payment_gateway, $vaulted_shopper_id) {
    $this->apiService->initializeBlueSnap($payment_gateway);
    $response = BlueSnapVaultedShopper::get($vaulted_shopper_id);

    if ($response->failed()) {
      throw new Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Creates the payment method for an authenticated user.
   *
   * @param \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\OnsiteBase $payment_gateway
   *   The payment gateway we're using.
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   * @param array $shopper_data
   *   The BlueSnap shopper information.
   * @param string $customer_id
   *   The BlueSnap customer ID.
   */
  public function vaultedShopper(
    OnsiteBase $payment_gateway,
    PaymentMethodInterface $payment_method,
    array $payment_details,
    array $shopper_data,
    $customer_id = NULL
  ) {
    // If this is an existing BlueSnap customer, use the token to create the new
    // card.
    if ($customer_id) {
      // Add the card to the existing vaulted shopper.
      try {
        return $this->addPaymentToVaultedShopper($payment_gateway, $customer_id, $shopper_data);
      }
      catch (Exception $e) {
        throw new HardDeclineException('Unable to verify the credit card: ' . $e->getMessage());
      }
    }
    // If it's a new customer, create a new vaulted shopper on BlueSnap.
    else {
      // Create a new vaulted shopper.
      try {
        $remote_payment_method = $this->createVaultedShopper($payment_gateway, $shopper_data);
        return $remote_payment_method;
      }
      catch (Exception $e) {
        throw new HardDeclineException('Unable to verify the credit card: ' . $e->getMessage());
      }
    }
  }

  /**
   * Provides credit card VaultedShopper request data.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   VaultedShopper data for Hosted payment fields transaction.
   */
  public function getCreditCardVaultedShopperData(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $payment_method->getBillingProfile()->get('address')->first();
    $data = [
      'firstName' => $address->getGivenName(),
      'lastName' => $address->getFamilyName(),
      'paymentSources' => [
        'creditCardInfo' => [
          [
            'pfToken' => $payment_details['bluesnap_token'],
            'billingContactInfo' => $this->getBillingContactInfo($payment_method),
          ],
        ],
      ],

    ];
    return $data;
  }

  /**
   * Provides ECP/ACH VaultedShopper request data.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   VaultedShopper data for ECP transaction.
   */
  public function getEcpVaultedShopperData(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $payment_method->getBillingProfile()->get('address')->first();
    $data = [
      'firstName' => $address->getGivenName(),
      'lastName' => $address->getFamilyName(),
      'paymentSources' => [
        'ecpInfo' => [
          'ecp' => [
            'routingNumber' => $payment_details['routing_number'],
            'accountType' => $payment_details['account_type'],
            'accountNumber' => $payment_details['account_number'],
          ],
          'billingContactInfo' => $this->getBillingContactInfo($payment_method),
        ],
      ],
    ];
    return $data;
  }

  /**
   * Provides the billing contact info from billing profile.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   *
   * @return array
   *   Billing contact info in an array form as required by VaultedShopper API.
   */
  public function getBillingContactInfo(PaymentMethodInterface $payment_method) {
    $address = $payment_method->getBillingProfile()->get('address')->first();
    $billing_info = [
      'firstName' => $address->getGivenName(),
      'lastName' => $address->getFamilyName(),
      'address1' => $address->getAddressLine1(),
      'address2' => $address->getAddressLine2(),
      'city' => $address->getLocality(),
      'state' => $address->getAdministrativeArea(),
      'zip' => $address->getPostalCode(),
      'country' => $address->getCountryCode(),
    ];
    return $billing_info;
  }

}
