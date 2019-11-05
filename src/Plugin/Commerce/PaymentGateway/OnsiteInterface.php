<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Interface for all BlueSnap onsite payment gateways.
 *
 * Currently:
 * - Hosted payment fields.
 * - ECP.
 */
interface OnsiteInterface extends
  OnsitePaymentGatewayInterface,
  SupportsRefundsInterface,
  SupportsNotificationsInterface {

  /**
   * Indicates the remote payment method name for Card transactions.
   */
  const REMOTE_PAYMENT_METHOD_NAME_CC = 'CC';

  /**
   * Indicates the remote payment method name for ECP transactions.
   */
  const REMOTE_PAYMENT_METHOD_NAME_ECP = 'ECP';

}
