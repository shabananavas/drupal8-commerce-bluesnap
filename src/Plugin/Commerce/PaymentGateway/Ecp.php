<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\AltTransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;
use Drupal\commerce_bluesnap\Ipn\HandlerInterface as IpnHandlerInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Bluesnap ACH/ECP payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "bluesnap_ecp",
 *   label = "BlueSnap (ACH/ECP)",
 *   display_label = "BlueSnap (ACH/ECP)",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_bluesnap\PluginForm\Bluesnap\EcpPaymentMethodAddForm",
 *   },
 *   payment_method_types = {"bluesnap_ecp"},
 *   payment_type = "bluesnap_ecp",
 * )
 */
class Ecp extends OnsiteBase {

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // Prepare the data required to process an ACH/ECP transaction.
    $data = $this->prepareTransactionData($payment, $payment_method, $amount);

    // We create Vaulted Shoppers for both authenticated and anonymous. For
    // authenticated users we store the Vaulted Shopper ID as the user's remote
    // ID, while for anonymous users we store it as the payment method's remote
    // ID.
    $owner = $payment_method->getOwner();
    if ($owner->isAuthenticated()) {
      $data['vaultedShopperId'] = $this->getRemoteCustomerId($owner);
    }
    else {
      $data['vaultedShopperId'] = $payment_method->getRemoteId();
    }

    // Create the payment transaction on BlueSnap.
    $client = $this->clientFactory->get(
      AltTransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->create($data);

    // Mark the payment as completed.
    $payment->setState('pending');
    $payment->setRemoteId($result->id);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    $required_keys = [
      'routing_number',
      'account_number',
      'account_type',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf(
          '$payment_details must contain the %s key.',
          $required_key
        ));
      }
    }

    $remote_id = NULL;

    // If authenticated user, create or update the vaulted shopper details.
    // There is no remote ID for the payment method provided by BlueSnap; we
    // store the Vaulted Shopper ID as the method's remote ID instead. Even
    // though it won't be used for authenticated users, it may still be proven
    // valuable in the future. For example, if for whatever reason the Drupal
    // user changes its corresponding Vaulted Shopper we will still have here
    // the original Vaulted Shopper corresponding to this card for reference.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $remote_id = $this->doCreatePaymentMethodForAuthenticatedUser(
        $payment_method,
        $payment_details,
        $this->getRemoteCustomerId($owner)
      );
    }
    // We are not storing the full account/routing numbers for ECP payment
    // methods for security reasons. The only way to then trigger a transaction
    // is to create a Vaulted Shopper for anonymous users as well. We do not
    // have a remote ID for the payment method itself and we cannot store the
    // Vaulted Shopper ID to the anonymous user; we will therefore store the
    // Vaulted Shopper ID to the payment method and use it to later trigger the
    // transaction.
    else {
      $vaulted_shopper = $this->createVaultedShopper(
        $payment_method,
        $payment_details
      );
      $remote_id = $vaulted_shopper->id;
    }

    // Save the payment method.
    $payment_method->routing_number = $this->truncateEcpNumber(
      $payment_details['routing_number']
    );
    $payment_method->account_number = $this->truncateEcpNumber(
      $payment_details['account_number']
    );
    $payment_method->account_type = $payment_details['account_type'];
    $payment_method->setRemoteId($remote_id);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $owner = $payment_method->getOwner();
    // If there's no owner we won't be able to delete the remote payment method
    // as we won't have a remote profile. Just delete the payment method locally
    // in that case.
    if (!$owner) {
      $payment_method->delete();
      return;
    }
    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $this->ipnHandler->checkRequestAccess($request);

    // Get the IPN data and type.
    $ipn_data = $this->ipnHandler->parseRequestData(
      $request,
      [IpnHandlerInterface::IPN_TYPE_CHARGE]
    );
    $ipn_type = $this->ipnHandler->getType($ipn_data);

    // Delegate to the appropriate method based on type.
    switch ($ipn_type) {
      case IpnHandlerInterface::IPN_TYPE_CHARGE:
        $this->ipnCharge($ipn_data);
    }
  }

  /**
   * Creates the payment method for an authenticated user.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   * @param string $customer_id
   *   The remote customer ID i.e. BlueSnap's Vaulted Shopper ID.
   *
   * @return array
   *   The ID of the existing or newly created Vaulted Shopper.
   */
  protected function doCreatePaymentMethodForAuthenticatedUser(
    PaymentMethodInterface $payment_method,
    array $payment_details,
    $customer_id = NULL
  ) {
    if ($customer_id) {
      $this->doCreatePaymentMethodForExistingVaultedShopper(
        $payment_method,
        $payment_details,
        $customer_id
      );

      return $customer_id;
    }

    return $this->doCreatePaymentMethodForNewVaultedShopper(
      $payment_method,
      $payment_details
    );
  }

  /**
   * Creates the payment method for a user with an existing Vaulted Shopper.
   *
   * Adds a new ECP payment source to the associated Vaulted Shopper in
   * BlueSnap.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   * @param string $customer_id
   *   The remote customer ID i.e. BlueSnap's Vaulted Shopper ID.
   */
  protected function doCreatePaymentMethodForExistingVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details,
    $customer_id
  ) {
    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );

    // Add the card to the existing vaulted shopper.
    $client->addEcp(
      $customer_id,
      $this->prepareEcpDetails($payment_method, $payment_details)
    );
  }

  /**
   * Creates the payment method for a user without an existing Vaulted Shopper.
   *
   * Creates a new Vaulted Shopper with the ECP payment source in BlueSnap and
   * it stores its ID in the .
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The ID of the newly created Vaulted Shopper.
   */
  protected function doCreatePaymentMethodForNewVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    $vaulted_shopper = $this->createVaultedShopper(
      $payment_method,
      $payment_details
    );

    // Save the new customer ID.
    $owner = $payment_method->getOwner();
    $this->setRemoteCustomerId($owner, $vaulted_shopper->id);
    $owner->save();

    return $vaulted_shopper->id;
  }

  /**
   * {@inheritdoc}
   */
  protected function preparePaymentSourcesDataForVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    $ecp_data = $this->prepareEcpDetails(
      $payment_method,
      $payment_details
    );

    return ['ecpDetails' => [$ecp_data]];
  }

  /**
   * Prepare the data for triggering an ACH/ECP transaction.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment for which the transaction is being prepared.
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   *
   * @return array
   *   An array containing the data required to process an ACH/ECP transaction.
   */
  protected function prepareTransactionData(
    PaymentInterface $payment,
    PaymentMethodInterface $payment_method
  ) {
    // Prepare the transaction amount.
    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    return [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      // Authorization is captured by the payment method form.
      'authorizedByShopper' => TRUE,
      'transactionMetadata' => [
        'metaData' => [
          [
            'metaKey' => 'order_id',
            'metaValue' => $payment->getOrderId(),
            'metaDescription' => 'The transaction\'s order ID.',
          ],
          [
            'metaKey' => 'store_id',
            'metaValue' => $payment->getOrder()->getStoreId(),
            'metaDescription' => 'The transaction\'s store ID.',
          ],
        ],
      ],
      // Note that the account/routing numbers must already be truncated.
      'ecpTransaction' => [
        'publicAccountNumber' => $payment_method->account_number->value,
        'publicRoutingNumber' => $payment_method->routing_number->value,
        'accountType' => $payment_method->account_type->value,
      ],
    ];
  }

  /**
   * Prepares the ECP data for adding to a vaulted shopper.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The ECP data as required by the Vaulted Shoppers API.
   */
  protected function prepareEcpDetails(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    return [
      'billingContactInfo' => $this->prepareBillingContactInfo($payment_method),
      'ecp' => [
        'routingNumber' => $payment_details['routing_number'],
        'accountType' => $payment_details['account_type'],
        'accountNumber' => $payment_details['account_number'],
      ],
    ];
  }

  /**
   * Returns the last 5 digits of an ECP account or routing number.
   *
   * @param string $number
   *   The number to truncate in text format.
   *
   * @return string
   *   The truncated number.
   */
  protected function truncateEcpNumber($number) {
    return substr($number, -5);
  }

  /**
   * Acts when a CHARGE IPN is received.
   *
   * Sets the status of the payment to completed.
   *
   * @param array $ipn_data
   *   The IPN request data.
   */
  protected function ipnCharge(array $ipn_data) {
    $payment = $this->ipnHandler->getEntity($ipn_data);

    // We only mark the payment as completed if it is currently in pending
    // state. If it is refunded (fully or partially) or voided, there's
    // something unexpected going on; maybe another action was taken before
    // receiving the CHARGE IPN - even though that would be unusual.
    $state = $payment->get('state')->first()->getId();
    if ($state !== 'pending') {
      return;
    }

    $payment->set('state', 'completed');
    $payment->save();
  }

}
