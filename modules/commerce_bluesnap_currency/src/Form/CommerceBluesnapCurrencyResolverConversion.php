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

    // Store whether we have an existing BlueSnap API password set in the form
    // state so that we know in the validation callback.
    $form_state->set(
      'bluesnap_api_password_exists',
      $config->get('bluesnap.password') ? TRUE : FALSE
    );

    // Call the parent form.
    $form = parent::buildForm($form, $form_state);

    // BlueSnap exchange rate config.
    $form['bluesnap'] = [
      '#type' => 'details',
      '#title' => t('BlueSnap Settings'),
      '#open' => TRUE,
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
        Leave this field empty if you have already entered your password before
        - unless you want to change the existing password.
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

    // Move the submit button to the end of the form.
    $form['submit']['#weight'] = 1000;

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
      $form_state->setErrorByName(
        'bluesnap][username',
        t('BlueSnap username field is required')
      );
    }

    // User might have left the field blank to use the previously stored
    // password.
    $password_exists = $form_state->get('bluesnap_api_password_exists');
    if (empty($bluesnap_config['password']) && !$password_exists) {
      $form_state->setErrorByName(
        'bluesnap][password',
        t('BlueSnap password field is required')
      );
    }

    if (empty($bluesnap_config['mode'])) {
      $form_state->setErrorByName(
        'bluesnap][mode',
        t('BlueSnap mode field is required')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_currency_resolver.currency_conversion');

    // Clear BlueSnap settings if the source selected is not BlueSnap.
    $source = $form_state->getValue('source');
    if ($source !== 'exchange_rate_bluesnap') {
      $config->clear('bluesnap')->save();
      parent::submitForm($form, $form_state);
      return;
    }

    // Save the submitted BlueSnap settings.
    $bluesnap_config = $form_state->getValue('bluesnap');

    // User might have left the field blank to use the previously stored
    // password, hence store the current password if the submitted field is
    // empty.
    if (empty($bluesnap_config['password'])) {
      $bluesnap_config['password'] = $config->get('bluesnap.password');
    }

    $config
      ->set('bluesnap', $bluesnap_config)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
