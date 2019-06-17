<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\Api\TransactionsClientInterface;
use Drupal\commerce_bluesnap\FraudSessionInterface;
use Drupal\commerce_bluesnap\DataLevelInterface;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the bluesnap payment gateway base class.
 */
abstract class OnsiteBase extends OnsitePaymentGatewayBase implements OnsiteInterface {

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
   * The Bluesnap fraud session process.
   *
   * @var \Drupal\commerce_bluesnap\FraudSessionInterface
   */
  protected $fraudSession;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Bluesnap data level service.
   *
   * @var \Drupal\commerce_bluesnap\DataLevelInterface
   */
  protected $dataLevel;

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
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce_bluesnap\Api\ClientFactory $client_factory
   *   The Bluesnap API client factory.
   * @param \Drupal\commerce_bluesnap\FraudSessionInterface $fraud_session
   *   The Bluesnap fraud session process.
   * @param \Drupal\commerce_bluesnap\DataLevelInterface $data_level
   *   The Bluesnap data level service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
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
    ClientFactory $client_factory,
    FraudSessionInterface $fraud_session,
    DataLevelInterface $data_level,
    ModuleHandlerInterface $module_handler
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
    $this->fraudSession = $fraud_session;
    $this->dataLevel = $data_level;
    $this->moduleHandler = $module_handler;
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
      $container->get('commerce_bluesnap.client_factory'),
      $container->get('commerce_bluesnap.fraud_session'),
      $container->get('commerce_bluesnap.data_level'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(
    PaymentInterface $payment,
    Price $amount = NULL
  ) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    // Round the amount as it can be manually entered via the Refund form.
    $amount = $this->rounder->round($amount);
    $this->assertRefundAmount($payment, $amount);

    // Refund the payment transaction on BlueSnap.
    $data = ['amount' => $amount->getNumber()];
    $client = $this->clientFactory->get(
      TransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $client->refund($payment->getRemoteId(), $data);

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
   * Prepares the billing contact info from the billing profile.
   *
   * This is the format for the billing info added to a payment source.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   *
   * @return array
   *   Billing contact info in an array form as required by the Vaulted Shoppers
   *   API.
   */
  protected function prepareBillingContactInfo(
    PaymentMethodInterface $payment_method
  ) {
    $address = $payment_method->getBillingProfile()->get('address')->first();

    return [
      'firstName' => $address->getGivenName(),
      'lastName' => $address->getFamilyName(),
      'address1' => $address->getAddressLine1(),
      'address2' => $address->getAddressLine2(),
      'city' => $address->getLocality(),
      'state' => $address->getAdministrativeArea(),
      'zip' => $address->getPostalCode(),
      'country' => $address->getCountryCode(),
    ];
  }

  /**
   * Prepares the billing contact info from the billing profile.
   *
   * This is the format for the billing info added to a vaulted shopper.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   *
   * @return array
   *   Billing contact info in an array form as required by the Vaulted Shoppers
   *   API.
   */
  protected function prepareVaultedShopperBillingInfo(
    PaymentMethodInterface $payment_method
  ) {
    $billing_info = $this->prepareBillingContactInfo($payment_method);

    // Correct the difference in the address 1 property.
    $billing_info['address'] = $billing_info['address1'];
    unset($billing_info['address1']);

    // Add the email as well.
    $owner = $payment_method->getOwner();
    if ($owner->isAuthenticated()) {
      $billing_info['email'] = $payment_method->getOwner()->getEmail();
    }

    return $billing_info;
  }

  /**
   * Returns the subscription ID for an recurring order.
   *
   * @param Drupal\commerce_order\Entity\Order $order
   *   The order entity whose subscription Id needs to be fetched.
   *
   * @return int|null
   *   The subscription Id if it exists, else NULL.
   */
  protected function subscriptionId(Order $order) {
    $subscriptions = [];

    // Loop through each order item to fetch the
    // subscription entity.
    // The subscription ID is the remote ID of the initial subscription Order.
    foreach ($order->getItems() as $order_item) {
      if ($order_item->get('subscription')->isEmpty()) {
        // A recurring order item without a subscription ID is malformed.
        continue;
      }
      /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
      $subscription = $order_item->get('subscription')->entity;
      // Guard against deleted subscription entities.
      if ($subscription) {
        $initial_order = $subscription->getInitialOrder();

        // Fetch the payments assosiated with the initial order.
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payments = $payment_storage->loadMultipleByOrder($initial_order);

        // Loop through payments and return the subscription ID.
        // @to-do: Will there be more than one payment associated with
        // single subscription order?
        // Will there be more than one subscription ID
        // associated with a single order?
        foreach ($payments as $payment) {
          return $payment->getRemoteId();
        }
      }
    }

    return NULL;
  }

  /**
   * Checks whether an order is initial recurring order or not.
   *
   * @param Drupal\commerce_order\Entity\Order $order
   *   The order entity to be checked for recurring or not.
   *
   * @return bool
   *   TRUE if order is initial recurring, FALSE if not.
   */
  protected function isInitialRecurring(Order $order) {

    // If commerce recurring module is not installed exit early.
    if (!$this->moduleHandler->moduleExists('commerce_recurring')) {
      return TRUE;
    }

    // We have to loop through
    // each product to see if there exists
    // a product with subscription enabled.
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && !$purchased_entity->hasField('subscription_type')) {
        continue;
      }

      // Can be considered as a subsscripton order if it has atleast one
      // product which has subscription enabled.
      $subscription_type_item = $purchased_entity->get('subscription_type');
      $billing_schedule_item = $purchased_entity->get('billing_schedule');
      if (!($subscription_type_item->isEmpty()) || !($billing_schedule_item->isEmpty())) {
        return TRUE;
      }
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
  protected function merchantManagedSubscriptionChargeData(
    PaymentInterface $payment
  ) {
    $order = $payment->getOrder();

    $payment_method = $payment->getPaymentMethod();

    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Create the payment data.
    $transaction_data = [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
      'merchantTransactionId' => $this->subscriptionId($order),
    ];

    return $transaction_data;
  }

  /**
   * Checks whether an order is recurring or not.
   *
   * @param Drupal\commerce_order\Entity\Order $order
   *   The order entity to be checked for recurring or not.
   *
   * @return bool
   *   True if order is recurring, False if not.
   */
  protected function isRecurring(Order $order) {
    // If commerce recurring module exists and if the order type is recuring
    // we assume that it is a recurring order.
    if ($this->moduleHandler->moduleExists('commerce_recurring')
      && $order->bundle() == 'recurring') {
      return TRUE;
    }

    return FALSE;
  }

}
