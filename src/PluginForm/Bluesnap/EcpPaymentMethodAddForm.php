<?php

namespace Drupal\commerce_bluesnap\PluginForm\Bluesnap;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentMethodType\Ecp;

/**
 * Provides ACH/ECP payment add form for the Bluesnap ACH/ECP gateway.
 *
 * @package Drupal\commerce_bluesnap\PluginForm
 */
class EcpPaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['payment_details'] = $this->buildEcpForm(
      $form['payment_details'],
      $form_state
    );

    return $form;
  }

  /**
   * Builds the ACH/ECP form.
   */
  public function buildEcpForm(array $element, FormStateInterface $form_state) {
    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="payment-errors"></div>',
      '#weight' => -200,
    ];

    $element['routing_number'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Routing number'),
      '#description' => $this->t("The bank's routing number."),
    ];
    $element['account_number'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Bank account'),
      '#description' => $this->t('The bank account number.'),
    ];
    $element['account_type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Account type'),
      '#description' => $this->t('The type of bank account.'),
      '#options' => Ecp::getAccountTypes(),
    ];
    $element['authorized_by_shopper'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t('I authorize this Electronic Check (ACH) transaction and agree to this debit of my account'),
    ];
    return $element;
  }

}
