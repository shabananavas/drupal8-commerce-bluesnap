<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentType;

/**
 * Provides the payment type for ACH/ECP transactions.
 *
 * @CommercePaymentType(
 *   id = "bluesnap_ecp",
 *   label = @Translation("ACH/ECP"),
 *   workflow = "payment_bluesnap_ecp",
 * )
 */
class Ecp extends PaymentTypeBase {}
