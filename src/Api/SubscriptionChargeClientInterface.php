<?php

namespace Drupal\commerce_bluesnap\Api;

/**
 * Defines the interface for all Card/Wallet Transactions API clients.
 */
interface SubscriptionChargeClientInterface extends ClientInterface {
  /**
   * The identifier for the Merchant managed supscription.
   */
  const API_ID = 'recurring/ondemand/subscription_id';

  /**
   * Creates a new payment transaction on the BlueSnap gateway.
   *
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When creating the transaction fails.
   */
  public function create(array $data);

}
