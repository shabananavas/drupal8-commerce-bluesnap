<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;

/**
 * Provides the interface for the Hosted Payment Fields payment gateway.
 */
interface HostedPaymentFieldsInterface extends
  OnsiteInterface,
  SupportsAuthorizationsInterface {}
