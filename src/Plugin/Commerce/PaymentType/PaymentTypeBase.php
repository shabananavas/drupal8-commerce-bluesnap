<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentType;

use Drupal\commerce_bluesnap\FieldService;
use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeBase as Base;

/**
 * Provides the base payment type class for BlueSnap payment types.
 */
class PaymentTypeBase extends Base {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [
      'bluesnap_merchant_transaction_id' => FieldService::paymentMerchantTransactionIdFieldDefinition(),
    ];
  }

}
