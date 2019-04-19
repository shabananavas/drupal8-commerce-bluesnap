<?php

namespace Drupal\commerce_bluesnap\PluginForm\Bluesnap;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_bluesnap\ApiService;
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
   * The Bluesnap API helper service.
   *
   * @var \Drupal\commerce_bluesnap\ApiService
   */
  protected $apiService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    InlineFormManager $inline_form_manager,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    ApiService $api_service
  ) {
    parent::__construct(
      $inline_form_manager,
      $route_match,
      $entity_type_manager,
      $logger
    );

    $this->apiService = $api_service;
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
      $container->get('commerce_bluesnap.api_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(
    array $element,
    FormStateInterface $form_state
  ) {
    // The BlueSnap unique Hosted Payment Fields Token that will be sent in the
    // drupalSettings to the JS.
    // First, initialize BlueSnap.
    if (empty($form_state->getValue('bluesnap_token'))) {
      $plugin = $this->entity->getPaymentGateway()->getPlugin();
      $bluesnap_token = $this->apiService->getHostedPaymentFieldsToken($plugin);
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

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(
    array $element,
    FormStateInterface $form_state
  ) {
    // The payment gateway plugin will process the submitted payment details.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Add the bluesnap attribute to address form elements.
    $form['billing_information']['address']['widget'][0]['address_line1']['#attributes']['data-bluesnap'] = 'address_line1';
    $form['billing_information']['address']['widget'][0]['address_line2']['#attributes']['data-bluesnap'] = 'address_line2';
    $form['billing_information']['address']['widget'][0]['locality']['#attributes']['data-bluesnap'] = 'address_city';
    $form['billing_information']['address']['widget'][0]['postal_code']['#attributes']['data-bluesnap'] = 'address_zip';
    $form['billing_information']['address']['widget'][0]['country_code']['#attributes']['data-bluesnap'] = 'address_country';

    return $form;
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

}
