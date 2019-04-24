<?php

namespace Drupal\commerce_bluesnap\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentMethodType\BlueSnapAch;

/**
 * Provides ACH/ECP payment add form for the Bluesnap ACH gateway.
 *
 * @package Drupal\commerce_bluesnap\PluginForm
 */
class AchAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['payment_details'] = $this->buildAchForm($form['payment_details'], $form_state);
    return $form;
  }

  /**
   * Builds the ACH/ECP form.
   */
  public function buildAchForm(array $element, FormStateInterface $form_state) {
    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="payment-errors"></div>',
      '#weight' => -200,
    ];

    $element['routing_number'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Routing number'),
      '#description' => t("The bank's routing number."),
    ];
    $element['account_number'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Bank account'),
      '#description' => t('The bank account number.'),
    ];
    $element['account_type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => t('Account type'),
      '#description' => t('The type of bank account.'),
      '#options' => BlueSnapAch::getAccountTypes(),
    ];
    $element['authorized_by_shopper'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => t('I authorize this Electronic Check (ACH) transaction and agree to this debit of my account'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateEcheckForm(array &$element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitEcheckForm(array $element, FormStateInterface $form_state) {
    // The payment gateway plugin will process the submitted payment details.
  }

}
