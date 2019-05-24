<?php

namespace Drupal\commerce_bluesnap_currency\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_currency_resolver\Form\CommerceCurrencyResolverConversion;

/**
 * Class CommerceCurrencyResolverConversion.
 *
 * @package Drupal\commerce_currency_resolver\Form
 */
class CommerceBluesnapCurrencyResolverConversion extends CommerceCurrencyResolverConversion {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get current settings.
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Add the bluesnap API password in formstorage, so that
    // we can have the value in validate function.
    $form_state->set('bluesnap_api_password', $config->get('bluesnap')['password']);

    // Call the parent form.
    $form = parent::buildForm($form, $form_state);

    // Unset the form submit coming from parent form as
    // its been added below.
    unset($form['submit']);

    // Bluesnap exchange rate config.
    $form['bluesnap'] = [
      '#type' => 'details',
      '#title' => t('Bluesnap Settings'),
      '#open' => FALSE,
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="source"]' => ['value' => 'exchange_rate_bluesnap'],
        ],
      ],
    ];
    $form['bluesnap']['username'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $config->get('bluesnap')['username'],
    ];
    $form['bluesnap']['password'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#description' => t('
        If you have already entered your password before,
        you should leave this field blank,
        unless you want to change the stored password.
      '),
    ];
    $form['bluesnap']['mode'] = [
      '#type' => 'radios',
      '#title' => t('Mode'),
      '#options' => [
        'sandbox' => t('Sandbox'),
        'production' => t('Production'),
      ],
      '#default_value' => $config->get('bluesnap')['mode'],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $source = $form_state->getValue('source');
    if ($source !== 'exchange_rate_bluesnap') {
      parent::validateForm($form, $form_state);
      return;
    }

    // We are skipping the CommerceCurrencyResolverConversion form validation
    // and executing only the ConfigFormBase validation.
    // The CommerceCurrencyResolverConversion validate function
    // conflicts with bluesnap settings validation.
    ConfigFormBase::validateForm($form, $form_state);

    // Make sure that user enters the bluesnap API details, if bluesnap exchange
    // rate is selected.
    $bluesnap_config = $form_state->getValue('bluesnap');
    if (empty($bluesnap_config['username'])) {
      $form_state->setErrorByName('bluesnap][username', t('Bluesnap username field is required'));
    }

    // User might have left the field blank to use the previously stored pw.
    if (empty($bluesnap_config['password']) && empty($form_state->get('bluesnap_api_password'))) {
      $form_state->setErrorByName('bluesnap][password', t('Bluesnap password field is required'));
    }

    if (empty($bluesnap_config['mode'])) {
      $form_state->setErrorByName('bluesnap][mode', t('Bluesnap mode field is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save bluesnap settings.
    $bluesnap_config = $form_state->getValue('bluesnap');

    // User might have left the field blank to use the previously stored pw,
    // hence store the previous password if pw field is empty.
    if (empty($bluesnap_config['password'])) {
      $bluesnap_config['password'] = $form_state->get('bluesnap_api_password');
    }
    $config = $this->config('commerce_currency_resolver.currency_conversion');
    $config->set('bluesnap', $bluesnap_config)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
