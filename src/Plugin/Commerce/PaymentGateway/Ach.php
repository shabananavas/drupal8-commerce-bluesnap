<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_price\Price;
use Exception;

/**
 * Provides the Bluesnap ACH/ECP payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "bluesnap_ach",
 *   label = "Bluesnap (ACH/ECP)",
 *   display_label = "Bluesnap (ACH/ECP)",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_bluesnap\PluginForm\AchAddForm",
 *   },
 *   payment_type = "payment_manual",
 *   payment_method_types = {"bluesnap_ach"},
 * )
 */
class Ach extends OnsiteBase implements AchInterface {

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    $amount = $payment->getAmount();
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $payment_method->getBillingProfile()->get('address')->first();

    // Information required to process an ECP/ACH transaction.
    $transaction_data = [
      'ecpTransaction' => [
        'accountNumber' => $payment_method->account_number->value,
        'routingNumber' => $payment_method->routing_number->value,
        'accountType' => $payment_method->account_type->value,
      ],
      'merchantTransactionId' => $payment->getOrderId(),
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      'payerInfo' => [
        'firstName' => $address->getGivenName(),
        'lastName' => $address->getFamilyName(),
      ],
      'authorizedByShopper' => TRUE,
    ];

    // If this is an authenticated user, use the BlueSnap vaulted shopper ID in
    // the payment data.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $transaction_data['vaultedShopperId'] = $this->getRemoteCustomerId($owner);
    }

    // Create the payment transaction on BlueSnap.
    try {
      $result = $this->apiService->createAltTransaction($this, $transaction_data);
    }
    catch (Exception $e) {
      throw new HardDeclineException('Could not charge the payment method. Message: ' . $e->getMessage());
    }
    // Mark the payment as pending as we await for transaction details from
    // Bluesnap.
    $payment->setState('pending');
    $payment->setRemoteId($result->id);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    $this->assertPaymentState($payment, ['pending']);
    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['pending']);
    $payment->setState('voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   *
   * @todo Needs kernel test
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      'routing_number', 'account_number', 'account_type',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // If authenticated user, create or update the vaulted shopper details.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);
      $shopper_data = $this->vaultedShopper->getEcpVaultedShopperData($payment_method, $payment_details);
      $remote_payment = $this->vaultedShopper->vaultedShopper(
        $this,
        $payment_method,
        $payment_details,
        $shopper_data,
        $customer_id
      );

      if (!empty($customer_id)) {
        // Save the new customer ID.
        $this->setRemoteCustomerId($owner, $remote_payment->id);
        $owner->save();
      }
    }

    // Save the payment method.
    $payment_method->routing_number = $payment_details['routing_number'];
    $payment_method->account_number = $payment_details['account_number'];
    $payment_method->account_type = $payment_details['account_type'];
    $payment_method->setReusable(FALSE);
    $payment_method->save();
  }

}
