<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\AltTransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\SubscriptionClientInterface;
use Drupal\commerce_bluesnap\Api\SubscriptionChargeClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

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

    // Check whether the order is a recurring order, if yes
    // perform the recurring transaction.
    $result_id = $this->recurringTransaction($payment, $capture);

    if (empty($result_id)) {
      $data = $this->ecpTransactionData($payment, $capture);

      // Create the payment transaction on BlueSnap.
      $client = $this->clientFactory->get(
        AltTransactionsClientInterface::API_ID,
        $this->getBluesnapConfig()
      );
      $result = $client->create($data);
      $result_id = $result->id;
    }

    // Mark the payment as completed.
    $payment->setState('pending');
    $payment->setRemoteId($result_id);
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
   * Create a recurring payment transaction in bluesnap.
   *
   * Check whether the order is recurring or not and if yes
   * make a recurring create/charge transaction in bluesnap.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param bool $capture
   *   Whether the created payment should be captured (VS authorized only).
   *   Allowed to be FALSE only if the plugin supports authorizations.
   *
   * @return int
   *   The BlueSnap response ID.
   */
  protected function recurringTransaction(PaymentInterface $payment, $capture) {
    $order = $payment->getOrder();

    // If recurring order,
    // use merchant managed subscription charge API.
    if ($this->isRecurring($order)) {
      $client = $this->clientFactory->get(
        SubscriptionChargeClientInterface::API_ID,
        $this->getBluesnapConfig()
      );

      // Data required for a merchant managed subscription charge.
      $data = $this->merchantManagedSubscriptionChargeData($payment, $capture);
      $data['subscription_id'] = $this->subscriptionId($order);

      $result = $client->create($data);

      return $result->subscriptionId;
    }

    // If initial recurring order,
    // use merchant managed subscription create API.
    elseif ($this->isInitialRecurring($order)) {
      $client = $this->clientFactory->get(
        SubscriptionClientInterface::API_ID,
        $this->getBluesnapConfig()
      );

      // Data required for a merchant managed subscription.
      $data = $this->ecpTransactionData($payment, $capture);
      $result = $client->create($data);

      // The subscription ID is not returned in the
      // Create Subscription response.
      // It is created once the payment has been processed
      // (typically within 2–6 business days).
      // You then be informed of the subscription ID via Charge webhook
      // or via Retrieve Specific Charge request.
      // @to-do Fetch subscription ID in IPN implementation.
      if (!empty($result->subscriptionId)) {
        return $result->subscriptionId;
      }

      return $result->transactionId;
    }

    return FALSE;
  }

  /**
   * Prepares the transaction data required for blueSnap Ecp transaction API.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param bool $capture
   *   Whether the created payment should be captured (VS authorized only).
   *   Allowed to be FALSE only if the plugin supports authorizations.
   *
   * @return array
   *   The card transaction data array as required by BlueSnap.
   */
  protected function ecpTransactionData(PaymentInterface $payment, $capture) {
    $payment_method = $payment->getPaymentMethod();

    // Prepare the data required to process an ACH/ECP transaction.
    $data = $this->prepareTransactionData($payment, $payment_method);

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

    return $data;
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
   * Creates a Vaulted Shopper in BlueSnap based on the given payment method.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The details of the created Vaulted Shopper.
   */
  protected function createVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    // Prepare the data for the request.
    $data = $this->prepareVaultedShopperBillingInfo($payment_method);

    // ECP data.
    $ecp_data = $this->prepareEcpDetails(
      $payment_method,
      $payment_details
    );
    $data['paymentSources']['ecpDetails'][] = $ecp_data;

    // We pass the Drupal user ID as the merchant shopper ID, only for
    // authenticated users.
    $owner = $payment_method->getOwner();
    if ($owner->isAuthenticated()) {
      $data['merchantShopperId'] = $payment_method->getOwner()->id();
    }

    // Create and return the vaulted shopper.
    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    return $client->create($data);
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

}
