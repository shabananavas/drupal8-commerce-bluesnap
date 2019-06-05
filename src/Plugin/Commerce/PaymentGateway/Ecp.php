<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\AltTransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;

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

    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $payment_method->getBillingProfile()->get('address')->first();

    // Information required to process an ACH/ECP transaction.
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
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
    ];

    // If this is an authenticated user, use the BlueSnap vaulted shopper ID in
    // the payment data. The payment method is already added to the shopper,
    // hence we only have to send the last 5 digits of account and routing
    // number.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $transaction_data['vaultedShopperId'] = $this->getRemoteCustomerId($owner);
      $transaction_data['ecpTransaction'] = [
        'publicAccountNumber' => $this->truncateEcpNumber(
          $payment_method->account_number->value
        ),
        'publicRoutingNumber' => $this->truncateEcpNumber(
          $payment_method->routing_number->value
        ),
        'accountType' => $payment_method->account_type->value,
      ];
    }
    // If this is an anonymous user, pass the full ECP details and the payer's
    // info as we haven't created a vaulted shopper for the user.
    else {
      $transaction_data['ecpTransaction'] = [
        'accountNumber' => $payment_method->account_number->value,
        'routingNumber' => $payment_method->routing_number->value,
        'accountType' => $payment_method->account_type->value,
      ];
      $transaction_data['payerInfo'] = [
        'firstName' => $address->getGivenName(),
        'lastName' => $address->getFamilyName(),
      ];
    }

    // Create the payment transaction on BlueSnap.
    $client = $this->clientFactory->get(
      AltTransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->create($transaction_data);

    // Mark the payment as completed.
    $payment->setState('completed');
    $payment->setRemoteId($result->id);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   *
   * @todo Needs kernel test
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

    // If authenticated user, create or update the vaulted shopper details.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $this->doCreatePaymentMethodForAuthenticatedUser(
        $payment_method,
        $payment_details,
        $this->getRemoteCustomerId($owner)
      );
    }

    // Save the payment method.
    $payment_method->routing_number = $this->truncateEcpNumber($payment_details['routing_number']);
    $payment_method->account_number = $this->truncateEcpNumber($payment_details['account_number']);
    $payment_method->account_type = $payment_details['account_type'];
    // TODO: Do we have a remote ID?
    $payment_method->save();
  }

  /**
   * Creates the payment method for an authenticated user.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   * @param string $customer_id
   *   The BlueSnap customer ID.
   *
   * @return array
   *   The details of the entered credit card.
   *
   * @throws \Exception
   */
  protected function doCreatePaymentMethodForAuthenticatedUser(
    PaymentMethodInterface $payment_method,
    array $payment_details,
    $customer_id = NULL
  ) {
    // Get the Vaulted Shopper API client.
    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );

    if ($customer_id) {
      $ecp_data = $this->prepareEcpDetails($payment_method, $payment_details);

      // Add the card to the existing vaulted shopper.
      $client->addEcp($customer_id, $ecp_data);
    }
    else {
      $shopper_data = $this->prepareVaultedShopperBillingInfo($payment_method);

      // Create a new vaulted shopper.
      $vaulted_shopper = $client->create($shopper_data);

      // Save the new customer ID.
      $owner = $payment_method->getOwner();
      $this->setRemoteCustomerId($owner, $vaulted_shopper->id);
      $owner->save();

      return $vaulted_shopper;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo Needs kernel test
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

}
