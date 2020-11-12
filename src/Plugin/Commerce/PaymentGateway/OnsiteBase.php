<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\Api\TransactionsClientInterface;
use Drupal\commerce_bluesnap\Api\SubscriptionsClientInterface;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClientInterface;
use Drupal\commerce_bluesnap\Ipn\HandlerInterface as IpnHandlerInterface;

use Drupal\commerce_order\Entity\OrderInterface;
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
use Drupal\Core\Utility\Token;
use Drupal\user\UserInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class providing common functionality to all BlueSnap onsite gateways.
 */
abstract class OnsiteBase extends OnsitePaymentGatewayBase implements OnsiteInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
   * The BlueSnap IPN handler.
   *
   * @var \Drupal\commerce_bluesnap\Ipn\HandlerInterface
   */
  protected $ipnHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new OnsiteBase object.
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
    Token $token
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

    $this->moduleHandler = $module_handler;
    $this->rounder = $rounder;
    $this->clientFactory = $client_factory;
    $this->ipnHandler = $ipn_handler;
    $this->token = $token;
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
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'username' => '',
      'password' => '',
      'statement_descriptors' => [
        'descriptor' => '',
        'phone_number' => '',
      ],
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

    $form += $this->buildAuthenticationFormElement();
    $form += $this->buildDescriptorFormElement();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::validateConfigurationForm($form, $form_state);

    $this->validateDescriptorFormElement($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::submitConfigurationForm($form, $form_state);

    if ($form_state->getErrors()) {
      return;
    }

    $values = $form_state->getValue($form['#parents']);
    $config = &$this->configuration;

    $config['username'] = $values['authentication']['username'];
    $config['password'] = $values['authentication']['password'];
    $config['statement_descriptors'] = $values['statement_descriptors'];
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

    // Don't update the payment now, just refund the payment transaction on
    // BlueSnap because it will trigger a refund IPN from BlueSnap. The IPN
    // handler will then take care of the actual updating of this payment in
    // Drupal.
    $data = ['amount' => $amount->getNumber()];
    $client = $this->clientFactory->get(
      TransactionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    $client->refund($payment->getRemoteId(), $data);
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
   * Creates a Vaulted Shopper in BlueSnap based on the given payment method.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   * @param array $additional_data
   *   An associative array with additional data that may depend on the gateway.
   *
   * @return array
   *   The details of the created Vaulted Shopper.
   */
  protected function createVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details,
    array $additional_data = []
  ) {
    // Prepare the data for the request.
    $data = $this->prepareVaultedShopperBillingInfo($payment_method);
    $data['paymentSources'] = $this->preparePaymentSourcesDataForVaultedShopper(
      $payment_method,
      $payment_details
    );
    $data += $additional_data;

    // We pass the Drupal user UUID as the merchant shopper ID, only for
    // authenticated users.
    $owner = $payment_method->getOwner();
    if ($owner->isAuthenticated()) {
      $data['merchantShopperId'] = $payment_method->getOwner()->uuid();
    }

    // Create and return the vaulted shopper.
    $client = $this->clientFactory->get(
      VaultedShoppersClientInterface::API_ID,
      $this->getBluesnapConfig()
    );
    return $client->create($data);
  }

  /**
   * Prepares the payment sources data required for creating a Vaulted Shopper.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The details of the payment sources.
   */
  abstract protected function preparePaymentSourcesDataForVaultedShopper(
    PaymentMethodInterface $payment_method,
    array $payment_details
  );

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
   * Create a subscription charge on BlueSnap.
   *
   * Check whether the order is a subscription order (initial or recurring) and
   * create a charge on BlueSnap.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return int
   *   The BlueSnap transaction ID for the charge.
   */
  protected function doCreatePaymentForSubscription(PaymentInterface $payment) {
    $order = $payment->getOrder();

    $client = $this->clientFactory->get(
      SubscriptionsClientInterface::API_ID,
      $this->getBluesnapConfig()
    );

    // If recurring order, use merchant-managed subscription charge API.
    if ($this->orderIsRecurringSubscription($order)) {
      // Data required for a merchant managed subscription charge.
      $data = $this->prepareSubscriptionChargeData($payment);
      $data['subscription_id'] = $this->subscriptionIdForOrder($order);

      $result = $client->createCharge($data);

      return $result->transactionId;
    }

    // If initial recurring order, use merchant-managed subscription create API.
    elseif ($this->orderIsInitialSubscription($order)) {
      // Data required for a merchant-managed subscription.
      $data = $this->prepareSubscriptionData($payment);

      $result = $client->create($data);

      // The Commerce Subscription entity where we want to store the remote
      // subscription ID is not create yet at this point. We will store it when
      // we receive the CHARGE IPN.
      return $result->transactionId;
    }
  }

  /**
   * Returns the BlueSnap subscription ID for a recurring order.
   *
   * A BlueSnap subscription is created for the initial order. If the order
   * contains multiple Commerce subscriptions and depending on their billing
   * schedules the renewal orders might be combining subscriptions or not
   * i.e. we may have only one or many separate renewal orders. The BlueSnap
   * subscription for all Commerce subscriptions/renewal orders will still be
   * the same. We therefore fetch it from the fetch subscription available.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity whose subscription ID needs to be fetched.
   *
   * @return int|null
   *   The subscription ID if it exists, else NULL.
   */
  protected function subscriptionIdForOrder(OrderInterface $order) {
    $subscriptions = [];
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    // Loop through each order item to fetch the subscription entity.
    // The subscription ID is the remote ID of the initial subscription Order.
    foreach ($order->getItems() as $order_item) {
      if (!$order_item->hasField('subscription')) {
        continue;
      }

      $subscription_field = $order_item->get('subscription');

      // A recurring order item without a subscription ID is malformed.
      if ($subscription_field->isEmpty()) {
        continue;
      }

      /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
      $subscription = $subscription_field->entity;

      // Guard against deleted subscription entities.
      if (!$subscription) {
        continue;
      }

      if (!$subscription->hasField('remote_id')) {
        continue;
      }

      $remote_id_field = $subscription->get('remote_id');
      if ($remote_id_field->isEmpty()) {
        continue;
      }

      return $remote_id_field->value;
    }
  }

  /**
   * Stores the BlueSnap subscription ID to all subscriptions for the order.
   *
   * Updates all subscriptions that reference the given order as their initial
   * order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $remote_id
   *   The remote BlueSnap ID for the subscription.
   */
  protected function orderStoreSubscriptionRemoteId(
    OrderInterface $order,
    $remote_id
  ) {
    // The local subscription ID is only created when creating the subscription
    // for the first time. That transaction would always correspond to an order
    // that is not of `recurring` type.
    if ($order->bundle() === 'recurring') {
      return;
    }

    // Load all subscriptions that reference the order as their initial order.
    $subscription_storage = $this->entityTypeManager
      ->getStorage('commerce_subscription');
    $subscription_ids = $subscription_storage->getQuery()
      ->condition('initial_order', $order->id())
      ->execute();
    if (!$subscription_ids) {
      return;
    }

    $subscriptions = $subscription_storage->loadMultiple($subscription_ids);

    foreach ($subscriptions as $subscription) {
      $subscription->set('remote_id', $remote_id);
      $subscription->save();
    }
  }

  /**
   * Prepares the transaction data required for BlueSnap subscription API.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return array
   *   The subscription transaction data array as required by BlueSnap.
   */
  protected function prepareSubscriptionChargeData(PaymentInterface $payment) {
    $amount = $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // Create the subscription charge data.
    return [
      'currency' => $amount->getCurrencyCode(),
      'amount' => $amount->getNumber(),
    ];
  }

  /**
   * Checks whether an order is a subscription order (initial or recurring).
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity to be checked.
   *
   * @return bool
   *   True if order is a subscription order, False if not.
   */
  protected function orderIsSubscription(OrderInterface $order) {
    if ($this->orderIsRecurringSubscription($order)) {
      return TRUE;
    }

    return $this->orderIsInitialSubscription($order);
  }

  /**
   * Checks whether an order is recurring or not.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity to be checked for recurring or not.
   *
   * @return bool
   *   True if order is recurring, False if not.
   */
  protected function orderIsRecurringSubscription(OrderInterface $order) {
    // If commerce recurring module exists and if the order type is recuring
    // we assume that it is a recurring order.
    $module_exists = $this->moduleHandler->moduleExists('commerce_recurring');
    if ($module_exists && $order->bundle() === 'recurring') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks whether an order is initial recurring order or not.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity to be checked for recurring or not.
   *
   * @return bool
   *   TRUE if order is initial recurring, FALSE if not.
   */
  protected function orderIsInitialSubscription(OrderInterface $order) {
    // If commerce recurring module is not installed exit early.
    if (!$this->moduleHandler->moduleExists('commerce_recurring')) {
      return FALSE;
    }

    // Renewal orders are of type `recurring`; it's not the initial order in
    // that case.
    if ($order->bundle() === 'recurring') {
      return FALSE;
    }

    // Can be considered an initial subscription order if it has at least one
    // product which has subscription enabled.
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity) {
        continue;
      }
      if (!$purchased_entity->hasField('subscription_type')) {
        continue;
      }
      if (!$purchased_entity->get('subscription_type')->isEmpty()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function setRemoteCustomerId(UserInterface $account, $remote_id) {
    if (!$account->isAuthenticated()) {
      return;
    }

    /** @var \Drupal\commerce\Plugin\Field\FieldType\RemoteIdFieldItemListInterface $remote_ids */
    $remote_ids = $account->get('commerce_remote_id');
    $remote_ids->setByProvider(
      $this->remoteCustomerIdProviderKey(),
      $remote_id
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRemoteCustomerId(UserInterface $account) {
    if (!$account->isAuthenticated()) {
      return;
    }

    return $account
      ->get('commerce_remote_id')
      ->getByProvider($this->remoteCustomerIdProviderKey());
  }

  /**
   * Returns the provider key to be used for setting remote ID.
   *
   * @return string
   *   The provider key string for setting remote ID.
   */
  protected function remoteCustomerIdProviderKey() {
    return 'bluesnap_' . $this->configuration['username'] . '|' . $this->getMode();
  }

  /**
   * Returns the form element that contains the authentication fields.
   *
   * @return array
   *   The authentication form element render array.
   */
  protected function buildAuthenticationFormElement() {
    $form = [];

    $form['authentication'] = [
      '#type' => 'details',
      '#title' => $this->t('Authentication'),
      '#open' => TRUE,
    ];
    $form['authentication']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->configuration['username'],
      '#required' => TRUE,
    ];
    $form['authentication']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $this->configuration['password'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Returns the form element that contains the statement descriptors fields.
   *
   * @return array
   *   The statement descriptors form element render array.
   */
  protected function buildDescriptorFormElement() {
    $form = [];
    $config = &$this->configuration['statement_descriptors'];
    $token_types = ['commerce_order', 'commerce_payment', 'commerce_store'];

    $form['statement_descriptors'] = [
      '#type' => 'details',
      '#title' => $this->t('Statement descriptors'),
    ];

    $form['statement_descriptors']['help'] = [
      '#markup' => $this->t(
        'You can override the statement descriptor and the support phone number
        appended in the descriptor here. If left empty, the default descriptor
        and phone number configured in the BlueSnap account will be used.
        <a href="@link" target="_blank">Learn more</a>',
        ['@link' => 'https://support.bluesnap.com/docs/statement-descriptor']
      ),
    ];
    $form['statement_descriptors']['help_2'] = [
      '#markup' => '<p>' . $this->t(
        'If you use tokens in statement descriptors or support phone numbers,
        make sure that the selected values that will be replaced at the moment
        the payment is made will always comply with the
        <a href="@link" target="_blank">allowed characters</a>; payments for
        which statement descriptors or support phone numbers contain illegal
        characters or are longer than the maximum length permitted by BlueSnap
        may fail and it may not be possible for the customers to continue and
        place their orders.',
        ['@link' => 'https://support.bluesnap.com/docs/statement-descriptor#statement-descriptor-format-and-content']
      ) . '</p>',
    ];
    $form['statement_descriptors']['descriptor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Soft descriptor'),
      '#default_value' => $config['descriptor'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['statement_descriptors']['phone_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Support phone number'),
      '#default_value' => $config['phone_number'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => $token_types,
    ];
    $form['statement_descriptors']['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $token_types,
      '#show_restricted' => TRUE,
      '#global_types' => FALSE,
      '#weight' => 90,
    ];

    return $form;
  }

  /**
   * Validates the form element(s) that contain statement descriptors.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function validateDescriptorFormElement(
    array &$form,
    FormStateInterface $form_state
  ) {
    $values = $form_state->getValue($form['#parents']);
    if (!$values['statement_descriptors']['descriptor']) {
      return;
    }

    $descriptors = &$values['statement_descriptors'];
    $result = $this->validateDescriptor($descriptors['descriptor']);
    if (!$result['valid']) {
      $form_state->setError(
        $form['statement_descriptors']['descriptor'],
        $result['error']
      );
    }
    $result = $this->validateDescriptorPhoneNumber($descriptors['phone_number']);
    if (!$result['valid']) {
      $form_state->setError(
        $form['statement_descriptors']['phone_number'],
        $result['error']
      );
    }
  }

  /**
   * Validates that the given descriptor contains only allowed characters.
   *
   * @param string $descriptor
   *   The descriptor to validate.
   *
   * @return bool
   *   TRUE if the descriptor is valid, FALSE otherwise.
   */
  protected function validateDescriptor($descriptor) {
    // We validate after removing tokens which contain not allowed characters
    // and also make the string longer than the maximum length.
    $token_free_descriptor = $this->removeTokensFromString($descriptor);

    if ($token_free_descriptor === '') {
      return ['valid' => TRUE];
    }

    // Allowed characters and length described at
    // https://support.bluesnap.com/docs/statement-descriptor.
    $regex = '/\A[A-Za-z0-9 ' . preg_quote('&,.-#') . ']+\z/';
    if (!preg_match($regex, $token_free_descriptor)) {
      return [
        'valid' => FALSE,
        'error' => $this->t(
          'Statement descriptors may only contain alphanumeric characters,
          spaces, and these special characters: & , . - #'
        ),
      ];
    }

    if (strlen($token_free_descriptor) > 20) {
      return [
        'valid' => FALSE,
        'error' => $this->t(
          'Statement descriptors cannot be longer than 20 characters.'
        ),
      ];
    }

    return ['valid' => TRUE];
  }

  /**
   * Validates that the given phone number contains only allowed characters.
   *
   * @param string $phone
   *   The descriptor phone number to validate.
   *
   * @return bool
   *   TRUE if the phone number is valid, FALSE otherwise.
   */
  protected function validateDescriptorPhoneNumber($phone) {
    // We validate after removing tokens which contain not allowed characters
    // and also make the string longer than the maximum length.
    $token_free_phone = $this->removeTokensFromString($phone);

    if ($token_free_phone === '') {
      return ['valid' => TRUE];
    }

    // Allowed characters and length described at
    // https://support.bluesnap.com/docs/statement-descriptor.
    $regex = '/\A[0-9]+\z/';
    if (!preg_match($regex, $token_free_phone)) {
      return [
        'valid' => FALSE,
        'error' => $this->t(
          'Statement descriptor support phone numbers may only contain numeric
          characters.'
        ),
      ];
    }

    if (strlen($token_free_phone) > 12) {
      return [
        'valid' => FALSE,
        'error' => $this->t(
          'Statement descriptor phone numbers cannot be longer than 12
          characters.'
        ),
      ];
    }

    return ['valid' => TRUE];
  }

  /**
   * Remove tokens from the given string.
   *
   * @param string $string
   *   The string to remove tokens from.
   *
   * @return string
   *   The string with the tokens removed.
   */
  protected function removeTokensFromString($string) {
    return preg_replace(
      '(\[[A-Za-z0-9_\-]+:[A-Za-z0-9_\-]+\])',
      '',
      $string
    );
  }

  /**
   * Prepares the statement descriptor data that will be sent to BlueSnap.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return array
   *   An array containing the descriptor data.
   */
  protected function prepareDescriptorData(PaymentInterface $payment) {
    $data = [];
    $descriptors = &$this->configuration['statement_descriptors'];

    if ($descriptors['descriptor']) {
      $data['softDescriptor'] = $this->replaceDescriptorDataTokens(
        $descriptors['descriptor'],
        $payment
      );
    }
    if ($descriptors['phone_number']) {
      $data['descriptorPhoneNumber'] = $this->replaceDescriptorDataTokens(
        $descriptors['phone_number'],
        $payment
      );
    }

    return $data;
  }

  /**
   * Replaces the tokens available for statement descriptor values.
   *
   * @param string $value
   *   The value to replace the tokens for.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return string
   *   The value with the replaced tokens.
   */
  protected function replaceDescriptorDataTokens(
    $value,
    PaymentInterface $payment
  ) {
    $order = $payment->getOrder();

    return $this->token->replace(
      $value,
      [
        'commerce_order' => $order,
        'commerce_payment' => $payment,
        'commerce_store' => $order->getStore(),
      ]
    );
  }

}
