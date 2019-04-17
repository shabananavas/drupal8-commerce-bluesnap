<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\ApiService;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Bluesnap Hosted Payment Fields payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "bluesnap_hosted_payment_fields",
 *   label = "Bluesnap (Hosted Payment Fields)",
 *   display_label = "Bluesnap",
 *   forms = {
 *     "add-payment-method" =
 *   "Drupal\commerce_bluesnap\PluginForm\Bluesnap\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "mastercard", "visa",
 *   },
 *   js_library = "commerce_bluesnap/form",
 * )
 */
class HostedPaymentFields extends OnsitePaymentGatewayBase implements HostePaymentFieldsInterface {

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The Bluesnap API helper service.
   *
   * @var \Drupal\commerce_bluesnap\ApiService
   */
  protected $apiService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PaymentTypeManager $payment_type_manager,
    PaymentMethodTypeManager $payment_method_type_manager,
    TimeInterface $time,
    RounderInterface $rounder,
    ApiService $api_service
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $payment_type_manager,
      $payment_method_type_manager,
      $time
    );

    $this->rounder = $rounder;
    $this->apiService = $api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('commerce_price.rounder'),
      $container->get('commerce_bluesnap.api_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'username' => '',
      'password' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->configuration['username'],
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $this->configuration['password'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['username'] = $values['username'];
      $this->configuration['password'] = $values['password'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Create the payment data.
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      'cardTransactionType' => $capture ? 'AUTH_CAPTURE' : 'AUTH_ONLY',
      'merchantTransactionId' => $payment->getOrderId(),
    ];
    // If this is an authenticated user, use the BlueSnap vaulted shopper ID in
    // the payment data.
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
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
      $address = $payment_method->getBillingProfile()->get('address')->first();
      // First and last name are required.
      $transaction_data['cardHolderInfo'] = [
        'firstName' => $address->getGivenName(),
        'lastName' => $address->getFamilyName(),
      ];
    }

    // Create the payment transaction on BlueSnap.
    try {
      $result = $this->apiService->createTransaction($this, $transaction_data);
    }
    catch (Exception $e) {
      throw new HardDeclineException('Could not charge the payment method. Message: ' . $e->getMessage());
    }

    $next_state = $capture ? 'completed' : 'authorization';
    $payment->setState($next_state);
    $payment->setRemoteId($result->id);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
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
    try {
      $result = $this->apiService->updateTransaction($this, $remote_id, $transaction_data);
    }
    catch (Exception $e) {
      throw new PaymentGatewayException('Could not capture the payment. Message: ' . $e->getMessage());
    }

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
    try {
      $result = $this->apiService->updateTransaction($this, $remote_id, $transaction_data);
    }
    catch (Exception $e) {
      throw new PaymentGatewayException('Could not void the payment. Message: ' . $e->getMessage());
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $amount = $this->rounder->round($amount);
    $this->assertRefundAmount($payment, $amount);

    // Refund the payment transaction on BlueSnap.
    $transaction_data = [
      'amount' => $amount->getNumber(),
    ];
    try {
      $result = $this->apiService->refundTransaction($this, $payment->getRemoteId(), $transaction_data);
    }
    catch (Exception $e) {
      throw new InvalidRequestException('Could not refund the payment. Message: ' . $e->getMessage());
    }

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    // The expected token and card details must always be present.
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
    $remote_payment_method = $this->doCreatePaymentMethod($payment_method, $payment_details);

    // Save the remote details in the payment method.
    // For auth users, get the card details from the remote payment method.
    if ($remote_payment_method) {
      // The card we added should be the last in the array.
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
    $expires = CreditCard::calculateExpirationTimestamp($card_expiry_month, $card_expiry_year);
    $payment_method->setExpiresTime($expires);
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
    try {
      $result = $this->apiService->deleteCardFromVaultedShopper($this, $customer_id, $data);
    }
    catch (Exception $e) {
      throw new InvalidRequestException('Could not delete the payment method. Message: ' . $e->getMessage());
    }

    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * Returns the environment for BlueSnap.
   */
  public function getEnvironment() {
    return $this->getMode() === 'live' ? 'production' : 'sandbox';
  }

  /**
   * Returns the username.
   */
  public function getUsername() {
    return $this->configuration['username'] ?: '';
  }

  /**
   * Returns the password.
   */
  public function getPassword() {
    return $this->configuration['password'] ?: '';
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
    $customer_id = NULL;
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);

      return $this->doCreatePaymentMethodForAuthenticatedUser(
        $payment_method,
        $payment_details,
        $customer_id
      );
    }

    // Anonymous user.
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
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
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

    // If this is an existing BlueSnap customer, use the token to create the new
    // card.
    if ($customer_id) {
      $credit_card_data = [
        'billingContactInfo' => $billing_info,
        'pfToken' => $payment_details['bluesnap_token'],
      ];

      // Add the card to the existing vaulted shopper.
      try {
        return $this->apiService->addCardToVaultedShopper($this, $customer_id, $credit_card_data);
      }
      catch (Exception $e) {
        throw new HardDeclineException('Unable to verify the credit card: ' . $e->getMessage());
      }
    }
    // If it's a new customer, create a new vaulted shopper on BlueSnap.
    else {
      $data = [
        'firstName' => $address->getGivenName(),
        'lastName' => $address->getFamilyName(),
        'email' => $owner->getEmail(),
        'address1' => $address->getAddressLine1(),
        'address2' => $address->getAddressLine2(),
        'city' => $address->getLocality(),
        'state' => $address->getAdministrativeArea(),
        'zip' => $address->getPostalCode(),
        'country' => $address->getCountryCode(),
        'paymentSources' => [
          'creditCardInfo' => [
            [
              'pfToken' => $payment_details['bluesnap_token'],
              'billingContactInfo' => $billing_info,
            ],
          ],
        ],
      ];

      // Create a new vaulted shopper.
      try {
        $remote_payment_method = $this->apiService->createVaultedShopper($this, $data);

        // Save the new customer ID.
        $this->setRemoteCustomerId($owner, $remote_payment_method->id);
        $owner->save();

        return $remote_payment_method;
      }
      catch (Exception $e) {
        throw new HardDeclineException('Unable to verify the credit card: ' . $e->getMessage());
      }
    }
  }

}
