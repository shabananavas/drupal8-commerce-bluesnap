<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeBase;

/**
 * Provides the payment type for ACH/ECP transactions.
 *
 * @CommercePaymentType(
 *   id = "bluesnap_ecp",
 *   label = @Translation("ACH/ECP"),
 *   workflow = "payment_bluesnap_ecp",
 * )
 */
class Ecp extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

}
