<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\ApiService;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

use stdClass;
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
   * BlueSnap test API URL.
   */
  const BLUESNAP_API_TEST_URL = 'https://sandbox.bluesnap.com';

  /**
   * BlueSnap production API URL.
   */
  const BLUESNAP_API_URL = 'https://ws.bluesnap.com';

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
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $this->rounder->round($amount)->getNumber(),
      'cardTransactionType' => $capture ? 'AUTH_CAPTURE' : 'AUTH_ONLY',
    ];

    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $transaction_data['vaultedShopperId'] = $this->getRemoteCustomerId($owner);
      $transaction_data['creditCard'] = [
        'cardLastFourDigits' => $payment_method->card_number->value,
        'cardType' => $payment_method->card_type->value,
      ];
    }
    else {
      $transaction_data['pfToken'] = $payment_method->getRemoteId();
    }

    $this->apiService->initializeBlueSnap($this);
    $result = $this->apiService->createTransaction($transaction_data);

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

    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount,
      'cardTransactionType' => 'CAPTURE',
      'transactionId' => $payment->getRemoteId(),
    ];

    // Capture the payment.
    $this->apiService->initializeBlueSnap($this);
    $result = $this->apiService->createTransaction($transaction_data);

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    // TODO: Implement voidPayment() method.
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    // TODO: Implement refundPayment() method.
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
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

    // Initialize BlueSnap and create the payment method on BlueSnap.
    $this->apiService->initializeBlueSnap($this);
    $remote_payment_method = $this->doCreatePaymentMethod($payment_method, $payment_details);

    // Save the remote details in the payment method.
    if ($remote_payment_method) {
      $card = $remote_payment_method->paymentSources->creditCardInfo[0];
      $payment_method->card_type = $this->mapCreditCardType($card->creditCard->cardType);
      $payment_method->card_number = $card->creditCard->cardLastFourDigits;
      $expiry_month = $card->creditCard->expirationMonth;
      $expiry_year = $card->creditCard->expirationYear;
      $payment_method->card_exp_month = $expiry_month;
      $payment_method->card_exp_year = $expiry_year;
      $expires = CreditCard::calculateExpirationTimestamp($expiry_month, $expiry_year);
      $payment_method->setExpiresTime($expires);
    }
    $payment_method->setRemoteId($payment_details['bluesnap_token']);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // TODO: Implement deletePaymentMethod() method.
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

    // If this is an existing BlueSnap customer, use the token to create the new
    // card.
    if ($customer_id) {
      $vaulted_shopper = new stdClass();
      $vaulted_shopper->paymentSources->creditCardInfo = [
        ['pfToken' => $payment_details['bluesnap_token']],
      ];
      return $this->apiService->updateVaultedShopper($customer_id, $vaulted_shopper);
    }
    // New customer.
    else {
      $vaulted_shopper = new stdClass();
      $vaulted_shopper->paymentSources->creditCardInfo = [
        ['pfToken' => $payment_details['bluesnap_token']],
      ];
      $vaulted_shopper->firstName = $address->getGivenName();
      $vaulted_shopper->lastName = $address->getFamilyName();
      $vaulted_shopper->email = $owner->getEmail();
      $vaulted_shopper->address1 = $address->getAddressLine1();
      $vaulted_shopper->address2 = $address->getAddressLine2();
      $vaulted_shopper->city = $address->getLocality();
      $vaulted_shopper->state = $address->getAdministrativeArea();
      $vaulted_shopper->zip = $address->getPostalCode();
      $vaulted_shopper->country = $address->getCountryCode();
      $remote_payment_method = $this->apiService->createVaultedShopper($vaulted_shopper);

      // Save the new customer ID.
      $this->setRemoteCustomerId($owner, $remote_payment_method->id);
      $owner->save();

      return $remote_payment_method;
    }
  }

  /**
   * Creates the payment method for an anonymous user.
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
  protected function doCreatePaymentMethodForAnonymousUser(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    $payment_method->setRemoteId($payment_details['bluesnap_token']);
    $payment_method->save();

    return [];
  }

}
