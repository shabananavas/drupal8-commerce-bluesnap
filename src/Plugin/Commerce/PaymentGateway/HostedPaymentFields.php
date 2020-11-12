<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\Api\TransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;
use Drupal\commerce_bluesnap\EnhancedData\DataInterface;
use Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface;
use Drupal\commerce_bluesnap\Ipn\HandlerInterface as IpnHandlerInterface;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Bluesnap Hosted Payment Fields payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "bluesnap_hosted_payment_fields",
 *   label = "BlueSnap (Hosted Payment Fields)",
 *   display_label = "BlueSnap",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_bluesnap\PluginForm\Bluesnap\HostedPaymentFieldsPaymentMethodAddForm",
 *     "refund-payment" = "Drupal\commerce_bluesnap\PluginForm\Bluesnap\PaymentRefundForm",
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
   * The Bluesnap enhanced data service.
   *
   * @var \Drupal\commerce_bluesnap\EnhancedData\DataInterface
   */
  protected $enhancedData;

  /**
   * The Bluesnap fraud session process.
   *
   * @var \Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface
   */
  protected $fraudSession;

  /**
   * Constructs a new HostedPaymentFields object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce_bluesnap\Api\ClientFactory $client_factory
   *   The Bluesnap API client factory.
   * @param \Drupal\commerce_bluesnap\Ipn\HandlerInterface $ipn_handler
   *   The BlueSnap IPN handler.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\commerce_bluesnap\EnhancedData\DataInterface $enhanced_data
   *   The Bluesnap enhanced data service.
   * @param \Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface $fraud_session
   *   The Bluesnap fraud session process.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PaymentTypeManager $payment_type_manager,
    PaymentMethodTypeManager $payment_method_type_manager,
    TimeInterface $time,
    ModuleHandlerInterface $module_handler,
    RounderInterface $rounder,
    ClientFactory $client_factory,
    IpnHandlerInterface $ipn_handler,
    Token $token,
    DataInterface $enhanced_data,
    FraudSessionInterface $fraud_session
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $payment_type_manager,
      $payment_method_type_manager,
      $time,
      $module_handler,
      $rounder,
      $client_factory,
      $ipn_handler,
      $token
    );

    $this->enhancedData = $enhanced_data;
    $this->fraudSession = $fraud_session;
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
      $container->get('module_handler'),
      $container->get('commerce_price.rounder'),
      $container->get('commerce_bluesnap.client_factory'),
      $container->get('commerce_bluesnap.ipn_handler'),
      $container->get('token'),
      $container->get('commerce_bluesnap.enhanced_data'),
      $container->get('commerce_bluesnap.fraud_session')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    $remote_id = NULL;

    // Check whether the order is a recurring order, if yes perform the
    // recurring transaction.
    $remote_id = $this->doCreatePaymentForSubscription($payment);

    // If order is not a recurring order, continue with default credit card
    // transaction.
    if (!$remote_id) {
      $data = $this->prepareTransactionData(
        $payment,
        $payment_method,
        $capture
      );
      $client = $this->clientFactory->get(
        TransactionsClientInterface::API_ID,
        $this->getBluesnapConfig()
      );
      $transaction = $client->create($data);
      $remote_id = $transaction->id;
    }

    // Mark the payment as completed.
    $next_state = $capture ? 'completed' : 'authorization';
    $payment->setState($next_state);
    $payment->setRemoteId($remote_id);
    $payment->save();

    // Fraud session IDs are specific to a payment. Remove the current ID so
    // that a new one is generated for the next payment.
    $this->fraudSession->remove();
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

    $remote_payment_method = NULL;

    // Authenticated user.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $remote_payment_method = $this->doCreatePaymentMethodForAuthenticatedUser(
        $payment_method,
        $payment_details,
        $this->getRemoteCustomerId($owner)
      );
    }
    // Anonymous user.
    else {
      $remote_payment_method = $this->createVaultedShopper(
        $payment_method,
        $payment_details
      );
    }

    // Save the payment method.
    // We get the card details from the payment method; it should always be the
    // last card in the vaulted shopper's payment sources as we just added it.
    $card = end($remote_payment_method->paymentSources->creditCardInfo);
    $card = $card->creditCard;
    $payment_method->card_type = $this->mapCreditCardType($card->cardType);
    $payment_method->card_number = $card->cardLastFourDigits;
    $payment_method->card_exp_month = $card->expirationMonth;
    $payment_method->card_exp_year = $card->expirationYear;

    $expires = CreditCard::calculateExpirationTimestamp(
      $payment_method->get('card_exp_month')->value,
      $payment_method->get('card_exp_year')->value
    );
    $payment_method->setExpiresTime($expires);

    // We always store the Vaulted Shopper ID as the payment method's remote ID
    // as we don't get an ID for the Card from BlueSnap. The Vaulted Shopper ID
    // might be useful in case, for whatever reason, the user changes Vaulted
    // Shopper (if authenticated) so that we have a historical record of which
    // Vaulted Shopper really corresponds to this payment method.
    $payment_method->setRemoteId($remote_payment_method->id);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // We always create a Vaulted Shopper ID even for anonymous users, and it's
    // stored as the payment method's remote ID.
    $vaulted_shopper_id = $payment_method->getRemoteId();

    $data = [
      'cardType' => $this->reverseMapCreditCardType($payment_method->card_type->value),
      'cardLastFourDigits' => $payment_method->card_number->value,
    ];

    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $result = $client->deleteCard($vaulted_shopper_id, $data);

    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    $this->ipnHandler->checkRequestAccess($request, $this->getEnvironment());

    // Get the IPN data and type.
    $ipn_data = $this->ipnHandler->parseRequestData(
      $request,
      [
        IpnHandlerInterface::IPN_TYPE_CHARGE,
        IpnHandlerInterface::IPN_TYPE_REFUND,
      ]
    );

    // If the IPN was not intended for our gateway, don't do anything.
    $payment_method_is_valid = $this->ipnHandler->validatePaymentMethod(
      $ipn_data,
      self::REMOTE_PAYMENT_METHOD_NAME_CC
    );
    if (!$payment_method_is_valid) {
      return;
    }

    // Delegate to the appropriate method based on type.
    $ipn_type = $this->ipnHandler->getType($ipn_data);
    switch ($ipn_type) {
      case IpnHandlerInterface::IPN_TYPE_CHARGE:
        $this->ipnCharge($ipn_data);
        break;

      case IpnHandlerInterface::IPN_TYPE_REFUND:
        $this->ipnRefund($ipn_data);
        break;
    }
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
    $map = $this->creditCardTypeMap();
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * Maps the Commerce credit card type to a BlueSnap credit card type.
   *
   * @param string $card_type
   *   The Commerce credit card type.
   *
   * @return string
   *   The BlueSnap credit card type.
   */
  protected function reverseMapCreditCardType($card_type) {
    // https://developers.bluesnap.com/docs/credit-card-codes.
    $map = array_flip($this->creditCardTypeMap());
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * Returns the map of supported credit cards.
   *
   * @return array
   *   A associative array of all supported credit card types, keyed by their
   *   BlueSnap IDs with their Commerce IDs as their values.
   */
  protected function creditCardTypeMap() {
    return [
      'AMEX' => 'amex',
      'DINERS' => 'dinersclub',
      'DISCOVER' => 'discover',
      'JCB' => 'jcb',
      'MASTERCARD' => 'mastercard',
      'VISA' => 'visa',
    ];
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
   *   The details of the created or updated Vaulted Shopper.
   */
  protected function doCreatePaymentMethodForAuthenticatedUser(
    PaymentMethodInterface $payment_method,
    array $payment_details,
    $customer_id = NULL
  ) {
    if ($customer_id) {
      return $this->doCreatePaymentMethodForExistingVaultedShopper(
        $payment_method,
        $payment_details,
        $customer_id
      );
    }

    return $this->doCreatePaymentMethodForNewVaultedShopper(
      $payment_method,
      $payment_details
    );
  }

  /**
   * Creates the payment method for a user with an existing Vaulted Shopper.
   *
   * Adds a new Card payment source to the associated Vaulted Shopper in
   * BlueSnap.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   * @param string $customer_id
   *   The remote customer ID i.e. BlueSnap's Vaulted Shopper ID.
   *
   * @return array
   *   The details of the updated Vaulted Shopper.
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
    return $client->addCard(
      $customer_id,
      $this->prepareCardDetails($payment_method, $payment_details)
    );
  }

  /**
   * Creates the payment method for a user without an existing Vaulted Shopper.
   *
   * Creates a new Vaulted Shopper with the Card payment source in BlueSnap and
   * it stores its ID as the user's gateway remote ID.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The details of the created Vaulted Shopper.
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

    return $vaulted_shopper;
  }

  /**
   * {@inheritdoc}
   *
   * Adds the fraud info that is always required when creating a Vaulted Shopper
   * using the Hosted Payment Fields gateway.
   */
  protected function createVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details,
    array $additional_data = []
  ) {
    return parent::createVaultedShopper(
      $payment_method,
      $payment_details,
      [
        'transactionFraudInfo' => [
          'fraudSessionId' => $this->fraudSession->get(),
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function preparePaymentSourcesDataForVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    $card_data = $this->prepareCardDetails(
      $payment_method,
      $payment_details
    );

    return ['creditCardInfo' => [$card_data]];
  }

  /**
   * Prepare the data for triggering a Card transaction.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment for which the transaction is being prepared.
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param bool $capture
   *   Whether the created payment should be captured (VS authorized only).
   *   See OnsitePaymentGatewayInterface::createPayment()
   *
   * @return array
   *   An array containing the data required to process a Card transaction.
   */
  protected function prepareTransactionData(
    PaymentInterface $payment,
    PaymentMethodInterface $payment_method,
    $capture
  ) {
    // Prepare the transaction amount.
    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    $data = [
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
      'creditCard' => [
        'cardLastFourDigits' => $payment_method->card_number->value,
        'cardType' => $payment_method->card_type->value,
      ],
    ];

    // Add BlueSnap level2/3 data to transaction.
    $level_2_3_data = $this->enhancedData->getData(
      $payment->getOrder(),
      $payment_method->card_type->value
    );
    $data = $data + $level_2_3_data;

    // We create Vaulted Shoppers for both authenticated and anonymous users
    // and, while for authenticated users we store the Vaulted Shopper ID as the
    // user's remote ID, we store it as the payment method's remote ID as well
    // in both cases; fetch it from there.
    $data['vaultedShopperId'] = $payment_method->getRemoteId();

    // Add statement descriptor.
    $data += $this->prepareDescriptorData($payment);

    return $data;
  }

  /**
   * Prepares the Card data for adding to a vaulted shopper.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The Card data as required by the Vaulted Shoppers API.
   */
  protected function prepareCardDetails(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    return [
      'billingContactInfo' => $this->prepareBillingContactInfo($payment_method),
      'pfToken' => $payment_details['bluesnap_token'],
    ];
  }

  /**
   * Acts when a CHARGE IPN is received.
   *
   * Stores the remote subscription ID to the local entity.
   *
   * @param array $ipn_data
   *   The IPN request data.
   */
  protected function ipnCharge(array $ipn_data) {
    $payment = $this->ipnHandler->getEntity($ipn_data);
    $order = $payment->getOrder();

    $subscription_id = NULL;
    if (!empty($ipn_data['subscriptionId'])) {
      $subscription_id = $ipn_data['subscriptionId'];
    }
    if ($subscription_id && $this->orderIsSubscription($order)) {
      $this->orderStoreSubscriptionRemoteId($order, $subscription_id);
    }
  }

  /**
   * Acts when a REFUND IPN is received.
   *
   * Note: Exactly the same for both Hosted Payment Fields and ECP
   * gateways. Consider reviewing the IPN system and move to the OnsiteBase
   * class.
   *
   * @param array $ipn_data
   *   The IPN request data.
   */
  protected function ipnRefund(array $ipn_data) {
    $payment = $this->ipnHandler->getEntity($ipn_data);
    $payment_amount = $payment->getAmount();

    // Get the refund amount. When getting the refunded amount from
    // `reversalAmount` the currency is not specifically given; it is assumed
    // the currency of the original transaction which should be the payment's
    // currency.
    $refund_amount = new Price(
      $ipn_data['reversalAmount'],
      $payment_amount->getCurrencyCode()
    );

    // Update the payment.
    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($refund_amount);

    // It's a full refund if BlueSnap tells us so or if we calculate so.
    $is_full_refund = FALSE;
    if (isset($ipn_data['fullRefund']) && $ipn_data['fullRefund'] === 'Y') {
      $is_full_refund = TRUE;
    }
    elseif (!$new_refunded_amount->lessThan($payment_amount)) {
      $is_full_refund = TRUE;
    }

    if ($is_full_refund) {
      $payment->setState('refunded');
    }
    else {
      $payment->setState('partially_refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSubscriptionData(PaymentInterface $payment) {
    $payment_method = $payment->getPaymentMethod();

    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Create the payment data.
    $data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      'transactionFraudInfo' => [
        'fraudSessionId' => $this->fraudSession->get(),
      ],
    ];

    // Add bluesnap level2/3 data to transaction.
    $level_2_3_data = $this->enhancedData->getData(
      $payment->getOrder(),
      $payment_method->card_type->value
    );
    $data = $data + $level_2_3_data;

    // We create Vaulted Shoppers for both authenticated and anonymous users
    // and, while for authenticated users we store the Vaulted Shopper ID as the
    // user's remote ID, we store it as the payment method's remote ID as well
    // in both cases; fetch it from there.
    $data['vaultedShopperId'] = $payment_method->getRemoteId();

    // It is marked as optional in the API documentation if a Vaulted Shopper is
    // used, but we pass it so that it is clear which card should be charged in
    // case the Vaulted Shopper has multiple cards.
    $data['paymentSource']['creditCardInfo'] = [
      'billingContactInfo' => $this->prepareBillingContactInfo($payment_method),
      'creditCard' => [
        'cardLastFourDigits' => $payment_method->card_number->value,
        'cardType' => $payment_method->card_type->value,
      ],
    ];

    // Descriptor data are passed only when creating the subscription and not on
    // individual charges.
    $data += $this->prepareDescriptorData($payment);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSubscriptionChargeData(PaymentInterface $payment) {
    $data = parent::prepareSubscriptionChargeData($payment);

    // Add bluesnap level2/3 data to transaction.
    $level_2_3_data = $this->enhancedData->getData(
      $payment->getOrder(),
      $payment->getPaymentMethod()->card_type->value
    );

    return $data + $level_2_3_data;
  }

}
