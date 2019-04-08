<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Provides the interface for the Embedded Checkout payment gateway.
 */
interface EmbeddedCheckoutInterface extends
    OnsitePaymentGatewayInterface,
    SupportsAuthorizationsInterface,
    SupportsRefundsInterface {

}
