<?php

namespace Drupal\commerce_bluesnap\PluginForm\Bluesnap;

use Drupal\commerce_bluesnap\Api\HostedPaymentFieldsClientInterface;
use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface;
use Drupal\commerce_bluesnap\FraudPrevention\KountAccountInterface;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a credit card payment method form for the HostedPaymentFields gateway.
 *
 * @package Drupal\commerce_bluesnap\PluginForm\Bluesnap
 */
class HostedPaymentFieldsPaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * The Bluesnap API client factory.
   *
   * @var \Drupal\commerce_bluesnap\Api\ClientFactory
   */
  protected $clientFactory;

  /**
   * The fraud session service.
   *
   * @var \Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface
   */
  protected $fraudSession;

   /**
   * The Kount account service.
   *
   * @var \Drupal\commerce_bluesnap\FraudPrevention\KountAccountInterface
   */
  protected $kountAccount;

  /**
   * Constructs a new HostedPaymentFieldsPaymentMethodAddForm.
   *
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\commerce_bluesnap\Api\ClientFactory $client_factory
   *   The BlueSnap API client factory.
   * @param Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface $fraud_session
   *   The fraud session service.
   * @param Drupal\commerce_bluesnap\FraudPrevention\KountAccountInterface $kount_account
   *   The Kount account service.
   */
  public function __construct(
    InlineFormManager $inline_form_manager,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    ClientFactory $client_factory,
    FraudSessionInterface $fraud_session,
    KountAccountInterface $kount_account
  ) {
    parent::__construct(
      $inline_form_manager,
      $route_match,
      $entity_type_manager,
      $logger
    );

    $this->clientFactory = $client_factory;
    $this->fraudSession = $fraud_session;
    $this->kountAccount = $kount_account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('commerce_payment'),
      $container->get('commerce_bluesnap.client_factory'),
      $container->get('commerce_bluesnap.fraud_session'),
      $container->get('commerce_bluesnap.kount_account')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $element = &$form['billing_information']['address']['widget'][0];

    // Add the bluesnap attribute to address form elements.
    $element['address_line1']['#attributes']['data-bluesnap'] = 'address_line1';
    $element['address_line2']['#attributes']['data-bluesnap'] = 'address_line2';
    $element['locality']['#attributes']['data-bluesnap'] = 'address_city';
    $element['postal_code']['#attributes']['data-bluesnap'] = 'address_zip';
    $element['country_code']['#attributes']['data-bluesnap'] = 'address_country';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildCreditCardForm(
    array $element,
    FormStateInterface $form_state
  ) {
    // The BlueSnap unique Hosted Payment Fields Token that will be sent in the
    // drupalSettings to the JS.
    // First, initialize BlueSnap.
    if (empty($form_state->getValue('bluesnap_token'))) {
      $plugin = $this->entity->getPaymentGateway()->getPlugin();
      $client = $this->clientFactory->get(
        HostedPaymentFieldsClientInterface::API_ID,
        $plugin->getBluesnapConfig()
      );
      $bluesnap_token = $client->createToken();
    }
    else {
      $bluesnap_token = $form_state->getValue('bluesnap_token');
    }

    // Alter the form with Bluesnap specific needs.
    $element['#attributes']['class'][] = 'bluesnap-form';
    $element['#attached']['library'][] = 'commerce_bluesnap/hosted_payment_fields_form';
    $element['#attached']['drupalSettings']['commerceBluesnap'] = [
      'hostedPaymentFields' => [
        'token' => $bluesnap_token,
      ],
    ];

    // Hidden fields which will be populated by the js.
    $this->hiddenFields($element);
    // The credit card fields necessary for BlueSnap.
    $this->ccFields($element);

    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="payment-errors"></div>',
      '#weight' => -200,
    ];

    // Add bluesnap device datacollector iframe for fraud prevention.
    $element['fraud_prevention'] = $this->deviceDataCollectorIframe();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(
    array &$element,
    FormStateInterface $form_state
  ) {
    // The JS library performs its own validation.
  }

  /**
   * {@inheritdoc}
   */
  protected function submitCreditCardForm(
    array $element,
    FormStateInterface $form_state
  ) {
    // The payment gateway plugin will process the submitted payment details.
  }

  /**
   * Add the BlueSnap hidden fields.
   *
   * @param array $element
   *   The element array.
   */
  protected function hiddenFields(array &$element) {
    // Hidden fields which will be populated by the js once a card is
    // successfully submitted to BlueSnap.
    $element['bluesnap_token'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'bluesnap-token',
      ],
    ];

    // These fields will be used for the card details for Anonymous users when
    // creating a payment method.
    $element['bluesnap_cc_type'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'bluesnap-cc-type',
      ],
    ];
    $element['bluesnap_cc_last_4'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'bluesnap-cc-last-4',
      ],
    ];
    $element['bluesnap_cc_expiry'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'bluesnap-cc-expiry',
      ],
    ];
  }

  /**
   * Add the BlueSnap credit card fields.
   *
   * @param array $element
   *   The element array.
   */
  protected function ccFields(array &$element) {
    $element['card_number'] = [
      '#type' => 'item',
      '#title' => t('Card number'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div class="form-control" id="card-number" data-bluesnap="ccn"></div>',
    ];

    $element['expiration'] = [
      '#type' => 'item',
      '#title' => t('Expiration date'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div class="form-control" id="exp-date" data-bluesnap="exp"></div>',
    ];

    $element['security_code'] = [
      '#type' => 'item',
      '#title' => t('CVV'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div class="form-control" id="cvv" data-bluesnap="cvv"></div>',
    ];
  }

  /**
   * Provides bluesnap device data collector iframe.
   *
   * @return array
   *  Render array which has bluesnap device datacollector iframe markup.
   */
  protected function deviceDataCollectorIframe() {
    // Get the Kount merchant ID from the store settings, if we have one
    // available for Enterprise accounts. We use the store for the current order,
    // or the default store if we can't determine the order from the route.
    if ($order = $this->routeMatch->getParameter('commerce_order')) {
      $store = $order->getStore();
    }
    else {
      $store = $this->storeStorage->loadDefault();
    }
    $merchant_id = $this->kountAccount->getMerchantId($store);

    $mode = $this->entity
     ->getPaymentGateway()
     ->getPlugin()
     ->getBluesnapConfig()['env'];

    return $this->fraudSession->iframe($mode, $merchant_id);
  }

}
