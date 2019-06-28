<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Provides the interface for the BlueSnap ACH/ECP payment gateway.
 */
interface OnsiteInterface extends
  OnsitePaymentGatewayInterface,
  SupportsRefundsInterface,
  SupportsNotificationsInterface {}
