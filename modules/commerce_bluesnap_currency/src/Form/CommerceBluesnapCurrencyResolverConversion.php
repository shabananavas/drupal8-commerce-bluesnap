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
      '#default_value' => $config->get('bluesnap')['password'],
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
    if (empty($bluesnap_config['password'])) {
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
    $config = $this->config('commerce_currency_resolver.currency_conversion');
    $config->set('bluesnap', $form_state->getValue('bluesnap'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
