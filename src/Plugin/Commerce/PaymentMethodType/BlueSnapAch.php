<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the bluesnap ach payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "bluesnap_ach",
 *   label = @Translation("ACH/ECP"),
 *   create_label = @Translation("ACH/ECP"),
 * )
 */
class BlueSnapAch extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    // Ach are not reused, so use a generic label.
    return $this->t('ACH/ECP');
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['routing_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Routing Number'))
      ->setDescription(t("The bank's routing number."))
      ->setRequired(TRUE);

    $fields['account_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Account Number'))
      ->setDescription(t('The bank account number.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', ['\Drupal\commerce_bluesnap\Plugin\Commerce\PaymentMethodType\BlueSnapAch',
        'getAccountTypes',
      ]);

    $fields['account_type'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card expiration month'))
      ->setDescription(t('The type of bank account.'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * The Bluesnap ACH/ECP account types.
   */
  public static function getAccountTypes() {
    return [
      'CONSUMER_CHECKING' => t('Consumer checking'),
      'CONSUMER_SAVINGS' => t('Consumer savings'),
      'CORPORATE_CHECKING' => t('Corporate checking'),
      'CORPORATE_SAVINGS' => t('Corporate savings'),
    ];
  }

}
