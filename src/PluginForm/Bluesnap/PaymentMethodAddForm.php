<?php

namespace Drupal\commerce_bluesnap\PluginForm\Bluesnap;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;

use Drupal\Core\Form\FormStateInterface;

/**
 * The BlueSnap PaymentMethodAddForm.
 *
 * @package Drupal\commerce_bluesnap\PluginForm\Bluesnap
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    // Alter the form with Bluesnap specific needs.
    $element['#attributes']['class'][] = 'bluesnap-form';

    $element['#attached']['library'][] = 'commerce_bluesnap/form';

    // Populated by the JS library.
    $element['bluesnap_token'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'bluesnap_token'
      ]
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
      '#attributes' => '<div class="form-control" id="exp-date" data-bluesnap="exp"></div>',
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
