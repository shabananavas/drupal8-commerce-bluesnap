<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\Api\TransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;

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

use Symfony\Component\DependencyInjection\ContainerInterface;

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
class HostedPaymentFields extends OnsitePaymentGatewayBase implements HostedPaymentFieldsInterface {

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The Bluesnap API client factory.
   *
   * @var \Drupal\commerce_bluesnap\Api\ClientFactory
   */
  protected $clientFactory;

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
    ClientFactory $client_factory
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
    $this->clientFactory = $client_factory;
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
      $container->get('commerce_bluesnap.client_factory')
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
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
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
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
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
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
      $address = $payment_method->getBillingProfile()->get('address')->first();
      // First and last name are required.
      $transaction_data['cardHolderInfo'] = [
        'firstName' => $address->getGivenName(),
        'lastName' => $address->getFamilyName(),
      ];
    }

    // Create the payment transaction on BlueSnap.
    $client = $this->clientFactory->get(
      TransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->create($transaction_data);

    $next_state = $capture ? 'completed' : 'authorization';
    $payment->setState($next_state);
    $payment->setRemoteId($result->id);
    $payment->save();

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
  public function refundPayment(
    PaymentInterface $payment,
    Price $amount = NULL
  ) {
    ksm($amount);
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $amount = $this->rounder->round($amount);
    $this->assertRefundAmount($payment, $amount);

    // Refund the payment transaction on BlueSnap.
    $transaction_data = [
      'amount' => $amount->getNumber(),
    ];
    $client = $this->clientFactory->get(
      TransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $client->refund($payment->getRemoteId(), $transaction_data);

    // Update the payment.
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
   * Returns the BlueSnap configuration for this payment gateway.
   *
   * @return array
   *   An array holding the BlueSnap configuration as required by the Client
   *   Factory.
   *   See \Drupal\commerce_bluesnap\Api\ClientFactory::init() for details.
   */
  public function getBluesnapConfig() {
    return [
      'env' => $this->getEnvironment(),
      'username' => $this->getUsername(),
      'password' => $this->getPassword(),
    ];
  }

  /**
   * Returns the environment for BlueSnap.
   */
  protected function getEnvironment() {
    return $this->getMode() === 'live' ? 'production' : 'sandbox';
  }

  /**
   * Returns the username.
   */
  protected function getUsername() {
    return $this->configuration['username'] ?: '';
  }

  /**
   * Returns the password.
   */
  protected function getPassword() {
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

    // Get the Vaulted Shopper API client.
    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );

    // If this is an existing BlueSnap customer, use the token to create the new
    // card.
    if ($customer_id) {
      $credit_card_data = [
        'billingContactInfo' => $billing_info,
        'pfToken' => $payment_details['bluesnap_token'],
      ];

      // Add the card to the existing vaulted shopper.
      $client->addCard($customer_id, $credit_card_data);
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
      $vaulted_shopper = $client->create($data);

      // Save the new customer ID.
      $this->setRemoteCustomerId($owner, $vaulted_shopper->id);
      $owner->save();

      return $vaulted_shopper;
    }
  }

}
