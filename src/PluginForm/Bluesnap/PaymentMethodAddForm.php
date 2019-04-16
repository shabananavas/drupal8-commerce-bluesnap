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
 * The BlueSnap PaymentMethodAddForm.
 *
 * @package Drupal\commerce_bluesnap\PluginForm\Bluesnap
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

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
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    // The BlueSnap unique Hosted Payment Fields Token that will be sent in the
    // drupalSettings to the JS.
    // First, initialize BlueSnap.
    if (empty($form_state->getValue('bluesnap_token'))) {
      $this->apiService->initializeBlueSnap($this->entity->getPaymentGateway()->getPlugin());
      $bluesnap_token = $this->apiService->getHostedPaymentFieldsToken();
    }
    else {
      $bluesnap_token = $form_state->getValue('bluesnap_token');
    }

    // Alter the form with Bluesnap specific needs.
    $element['#attributes']['class'][] = 'bluesnap-form';
    $element['#attached']['library'][] = 'commerce_bluesnap/form';
    $element['#attached']['drupalSettings']['bluesnap_token'] = $bluesnap_token;

    // Hidden fields which will be populated by the js.
    $element['bluesnap_token'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'bluesnap-token',
      ],
    ];

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
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    // The JS library performs its own validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the submitted payment details.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Add the bluesnap attribute to the postal code field.
    $form['billing_information']['address']['widget'][0]['address_line1']['#attributes']['data-bluesnap'] = 'address_line1';
    $form['billing_information']['address']['widget'][0]['address_line2']['#attributes']['data-bluesnap'] = 'address_line2';
    $form['billing_information']['address']['widget'][0]['locality']['#attributes']['data-bluesnap'] = 'address_city';
    $form['billing_information']['address']['widget'][0]['postal_code']['#attributes']['data-bluesnap'] = 'address_zip';
    $form['billing_information']['address']['widget'][0]['country_code']['#attributes']['data-bluesnap'] = 'address_country';

    return $form;
  }

}
