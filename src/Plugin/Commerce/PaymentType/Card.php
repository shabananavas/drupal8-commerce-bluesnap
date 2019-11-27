<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentType;

/**
 * Provides the payment type for Card transactions.
 *
 * @CommercePaymentType(
 *   id = "bluesnap_card",
 *   label = @Translation("Card"),
 *   workflow = "payment_default",
 * )
 */
class Card extends PaymentTypeBase {}
