<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\entity\BundleFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the bluesnap ACH/ECP payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "bluesnap_ecp",
 *   label = @Translation("ACH/ECP"),
 *   create_label = @Translation("ACH/ECP"),
 * )
 */
class Ecp extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $account_types = self::getAccountTypes();
    $args = [
      '@account_type' => $account_types[$payment_method->account_type->value],
      '@account_number' => $payment_method->account_number->value,
    ];
    return $this->t('@account_type ending in @account_number', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['routing_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Routing number'))
      ->setDescription(t("The bank's routing number."))
      ->setRequired(TRUE);

    $fields['account_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Account number'))
      ->setDescription(t('The bank account number.'))
      ->setRequired(TRUE);

    $fields['account_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel(t('Account type'))
      ->setDescription(t('The type of bank account.'))
      ->setRequired(TRUE)
      ->setSetting(
        'allowed_values_function',
        [
          '\Drupal\commerce_bluesnap\Plugin\Commerce\PaymentMethodType\Ecp',
          'getAccountTypes',
        ]
      );

    return $fields;
  }

  /**
   * The Bluesnap ACH/ECP account types.
   */
  public static function getAccountTypes() {
    return [
      'CONSUMER_CHECKING' => new TranslatableMarkup('Consumer checking'),
      'CONSUMER_SAVINGS' => new TranslatableMarkup('Consumer savings'),
      'CORPORATE_CHECKING' => new TranslatableMarkup('Corporate checking'),
      'CORPORATE_SAVINGS' => new TranslatableMarkup('Corporate savings'),
    ];
  }

}
