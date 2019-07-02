<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\Api\SubscriptionClientInterface;
use Drupal\commerce_bluesnap\Api\SubscriptionChargeClientInterface;
use Drupal\commerce_bluesnap\Api\TransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;
use Drupal\commerce_bluesnap\EnhancedData\DataInterface;
use Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_price\Price;

/**
 * Provides the Bluesnap Hosted Payment Fields payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "bluesnap_hosted_payment_fields",
 *   label = "BlueSnap (Hosted Payment Fields)",
 *   display_label = "BlueSnap",
 *   forms = {
 *     "add-payment-method" =
 *   "Drupal\commerce_bluesnap\PluginForm\Bluesnap\HostedPaymentFieldsPaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "mastercard", "visa",
 *   },
 *   js_library = "commerce_bluesnap/form",
 * )
 */
class HostedPaymentFields extends OnsiteBase implements HostedPaymentFieldsInterface {

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

    // If order is not a recurring order, continue with default
    // credit card transaction.
    if (empty($result_id)) {
      $client = $this->clientFactory->get(
        TransactionsClientInterface::API_ID,
        $this->getBluesnapConfig()
      );
      $result = $client->create($this->cardTransactionData($payment, $capture));
      $result_id = $result->id;
    }

    $next_state = $capture ? 'completed' : 'authorization';
    $payment->setState($next_state);
    $payment->setRemoteId($result_id);
    $payment->save();

    // Fraud session IDs are specific to a payment. Remove the current ID so
    // that a new one is generated for the next payment.
    $this->fraudSession->remove();

    // TODO: update transaction to store payment ID as `merchantTransactionId`.
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(
    PaymentInterface $payment,
    Price $amount = NULL
  ) {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Capture the payment transaction on BlueSnap.
    $remote_id = $payment->getRemoteId();
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      'cardTransactionType' => 'CAPTURE',
      'transactionId' => $remote_id,
    ];

    $client = $this->clientFactory(
      TransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->update($remote_id, $transaction_data);

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);

    // Void the payment transaction on BlueSnap.
    $remote_id = $payment->getRemoteId();
    $transaction_data = [
      'cardTransactionType' => 'AUTH_REVERSAL',
      'transactionId' => $remote_id,
    ];

    $client = $this->clientFactory(
      TransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->update($remote_id, $transaction_data);

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    // The expected token must always be present.
    $required_keys = [
      'bluesnap_token',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf(
          '$payment_details must contain the %s key.',
          $required_key
        ));
      }
    }

    // Create the payment method on BlueSnap.
    $remote_payment_method = $this->doCreatePaymentMethod(
      $payment_method,
      $payment_details
    );

    // Save the remote details in the payment method.
    // For auth users, get the card details from the remote payment method.
    if ($remote_payment_method) {
      // The card we added should be the last in the array.
      // TODO: Ask BlueSnap support to confirm that this is always the case.
      $card = end($remote_payment_method->paymentSources->creditCardInfo);
      $card_type = $this->mapCreditCardType($card->creditCard->cardType);
      $card_number = $card->creditCard->cardLastFourDigits;
      $card_expiry_month = $card->creditCard->expirationMonth;
      $card_expiry_year = $card->creditCard->expirationYear;
    }
    // For anon users, get the card details from the payment details.
    else {
      $card_type = $this->mapCreditCardType($payment_details['bluesnap_cc_type']);
      $card_number = $payment_details['bluesnap_cc_last_4'];
      $card_expiry = explode('/', $payment_details['bluesnap_cc_expiry']);
      $card_expiry_month = $card_expiry[0];
      $card_expiry_year = $card_expiry[1];
    }

    // Save the payment method.
    $payment_method->card_type = $card_type;
    $payment_method->card_number = $card_number;
    $payment_method->card_exp_month = $card_expiry_month;
    $payment_method->card_exp_year = $card_expiry_year;

    $expires = CreditCard::calculateExpirationTimestamp(
      $card_expiry_month,
      $card_expiry_year
    );
    $payment_method->setExpiresTime($expires);

    // TODO: This is not really the payment method's remote ID.
    $payment_method->setRemoteId($payment_details['bluesnap_token']);
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

    // Delete the card from the vaulted shopper on BlueSnap.
    $customer_id = $this->getRemoteCustomerId($owner);
    $data = [
      'cardLastFourDigits' => $payment_method->card_number->value,
      'expirationMonth' => $payment_method->card_exp_month->value,
      'expirationYear' => $payment_method->card_exp_year->value,
    ];

    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->deleteCard($customer_id, $data);

    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * Maps the BlueSnap credit card type to a Commerce credit card type.
   *
   * @param string $card_type
   *   The BlueSnap credit card type.
   *
   * @return string
   *   The Commerce credit card type.
   */
  protected function mapCreditCardType($card_type) {
    // https://developers.bluesnap.com/docs/credit-card-codes.
    $map = [
      'AMEX' => 'amex',
      'DINERS' => 'dinersclub',
      'DISCOVER' => 'discover',
      'JCB' => 'jcb',
      'MASTERCARD' => 'mastercard',
      'VISA' => 'visa',
    ];
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * Creates the payment method on the BlueSnap payment gateway.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The details of the entered credit card.
   *
   * @throws \Exception
   */
  protected function doCreatePaymentMethod(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    $owner = $payment_method->getOwner();

    // Authenticated user.
    if ($owner && $owner->isAuthenticated()) {
      return $this->doCreatePaymentMethodForAuthenticatedUser(
        $payment_method,
        $payment_details,
        $this->getRemoteCustomerId($owner)
      );
    }

    // Anonymous user.
    // TODO: Why don't we create the payment method for anonymous users here?
    return [];
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
    $owner = $payment_method->getOwner();

    $billing_info = $this->prepareBillingContactInfo($payment_method);

    // Get the Vaulted Shopper API client.
    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );

    $credit_card_data = $this->creditCardInfo($payment_method, $payment_details);

    // If this is an existing BlueSnap customer, use the token to create the new
    // card.
    if ($customer_id) {
      // Add the card to the existing vaulted shopper.
      $client->addCard($customer_id, $credit_card_data);
    }
    // If it's a new customer, create a new vaulted shopper on BlueSnap.
    else {
      $vaulted_shopper_billing = $this->prepareVaultedShopperBillingInfo($payment_method);

      $data = $vaulted_shopper_billing + [
        'transactionFraudInfo' => [
          "fraudSessionId" => $this->fraudSession->get(),
        ],
        'paymentSources' => [
          'creditCardInfo' => [
            $credit_card_data,
          ],
        ],
      ];

      // Create a new vaulted shopper.
      $vaulted_shopper = $client->create($data);

      // Save the new customer ID.
      $this->setRemoteCustomerId($owner, $vaulted_shopper->id);
      $owner->save();

      return $vaulted_shopper;
    }
  }

  /**
   * Prepares the transaction data required for blueSnap Card transaction API.
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
  protected function cardTransactionData(PaymentInterface $payment, $capture) {
    $payment_method = $payment->getPaymentMethod();
    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Create the payment data.
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      'cardTransactionType' => $capture ? 'AUTH_CAPTURE' : 'AUTH_ONLY',
      'transactionFraudInfo' => [
        'fraudSessionId' => $this->fraudSession->get(),
      ],
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

    // Add bluesnap level2/3 data to transaction.
    $level_2_3_data = $this->dataLevel->getData(
      $payment->getOrder(),
      $payment_method->card_type->value
    );
    $transaction_data = $transaction_data + $level_2_3_data;

    // If this is an authenticated user, use the BlueSnap vaulted shopper ID in
    // the payment data.
    // TODO: use pfToken instead.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $transaction_data['vaultedShopperId'] = $this->getRemoteCustomerId($owner);
      $transaction_data['creditCard'] = [
        'cardLastFourDigits' => $payment_method->card_number->value,
        'cardType' => $payment_method->card_type->value,
      ];
    }
    // If this is an anonymous user, use the BlueSnap token in the payment data.
    else {
      $transaction_data['pfToken'] = $payment_method->getRemoteId();
      // First and last name are required.
      $transaction_data['cardHolderInfo'] = $this->prepareBillingContactInfo($payment_method);
    }

    return $transaction_data;
  }

  /**
   * Create a recurring payment transaction in bluesnap.
   *
   * Check whether the order is recurring or not and if yes
   * make a recurring create/charge transaction in bluesnap.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return int
   *   The BlueSnap response ID.
   */
  protected function recurringTransaction(PaymentInterface $payment) {
    $order = $payment->getOrder();

    // If recurring order,
    // use merchant managed subscription charge API.
    if ($this->isRecurring($order)) {
      $client = $this->clientFactory->get(
        SubscriptionChargeClientInterface::API_ID,
        $this->getBluesnapConfig()
      );

      // Data required for a merchant managed subscription charge.
      $data = $this->merchantManagedSubscriptionChargeData($payment);
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
      $data = $this->merchantManagedSubscriptionData($payment);

      $result = $client->create($data);

      return $result->subscriptionId;
    }

    return FALSE;
  }

  /**
   * Prepares the transaction data required for blueSnap subscription API.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return array
   *   The subscription transaction data array as required by BlueSnap.
   */
  protected function merchantManagedSubscriptionData(
    PaymentInterface $payment
  ) {
    $payment_method = $payment->getPaymentMethod();

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $payment_method->getBillingProfile()->get('address')->first();

    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Create the payment data.
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
    ];

    // Add bluesnap level2/3 data to transaction.
    $level_2_3_data = $this->dataLevel->getData(
      $payment->getOrder(),
      $payment_method->card_type->value
    );
    $transaction_data = $transaction_data + $level_2_3_data;

    $payer_info = $this->prepareBillingContactInfo($payment_method);

    // If this is an authenticated user, use the BlueSnap vaulted shopper ID in
    // the payment data.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $transaction_data['vaultedShopperId'] = $this->getRemoteCustomerId($owner);
    }

    // For anonymous users use bluesnap token.
    else {
      $payment_details['bluesnap_token'] = $payment_method->getRemoteId();
      $transaction_data['paymentSource']['creditCardInfo'] =
        $this->creditCardInfo($payment_method, $payment_details);

      // Send the payerinfo
      // First and last name are required.
      $transaction_data['payerInfo'] = $payer_info;
    }

    return $transaction_data;
  }

  /**
   * Prepares the credit card info array.
   *
   * @param Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The order payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The credit card info array.
   */
  protected function creditCardInfo(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    return [
      'billingContactInfo' => $this->prepareBillingContactInfo($payment_method),
      'pfToken' => $payment_details['bluesnap_token'],
    ];
  }

}
