<?php

namespace Drupal\commerce_bluesnap\Api;

/**
 * Defines the interface for all Transactions API clients.
 */
interface TransactionsClientInterface extends ClientInterface {

  /**
   * The identifier for the Card Transaction API.
   */
  const API_ID = 'transactions';

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

  /**
   * Updates an existing payment transaction on the BlueSnap gateway.
   *
   * @param int $transaction_id
   *   The BlueSnap transaction ID.
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When updating the transaction fails.
   */
  public function update($transaction_id, array $data);

  /**
   * Refund an existing transaction on the BlueSnap gateway.
   *
   * @param int $transaction_id
   *   The BlueSnap transaction ID.
   * @param array $data
   *   An array of payment data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   *   When refunding the transaction fails.
   */
  public function refund($transaction_id, array $data);

}
