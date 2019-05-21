<?php

namespace Drupal\commerce_bluesnap\Api;

/**
 * Defines the interface for all Hosted Payment Fields API clients.
 */
interface HostedPaymentFieldsClientInterface extends ClientInterface {

  /**
   * The identifier for the Hosted Payment Fields API.
   */
  const API_ID = 'payment-fields-tokens';

  /**
   * Creates and returns a BlueSnap Hosted Payment Fields token.
   *
   * @return string
   *   The unique token from BlueSnap.
   */
  public function createToken();

}
