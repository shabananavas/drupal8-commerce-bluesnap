<?php

namespace Drupal\commerce_bluesnap\Api;

/**
 * Defines the interface for all ACH/ECP Transactions API clients.
 */
interface AltTransactionsClientInterface extends ClientInterface {

  /**
   * The identifier for the Card Transaction API.
   */
  const API_ID = 'alt-transactions';

  /**
   * Creates a new ACH/ECP transaction on the BlueSnap gateway.
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
