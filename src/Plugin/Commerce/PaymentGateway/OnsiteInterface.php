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
   * Indicates the payment_method name for the HPP gateway in the incoming IPN.
   */
  const IPN_HPP_PAYMENT_METHOD_NAME = 'CC';

  /**
   * Indicates the payment_method name for the ECP gateway in the incoming IPN.
   */
  const IPN_ECP_PAYMENT_METHOD_NAME = 'ECP';
}
