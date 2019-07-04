<?php

namespace Drupal\commerce_bluesnap\Api;

/**
 * Defines the interface for all Subscription API clients.
 */
interface SubscriptionsClientInterface extends ClientInterface {
  /**
   * The identifier for the Merchant managed supscription.
   */
  const API_ID = 'recurring/ondemand';

  /**
   * Creates a new merchant-managed subscription.
   *
   * @param array $data
   *   An array of subscription data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When creating the transaction fails.
   */
  public function create(array $data);

  /**
   * Creates a new charge for a merchant-managed subscription.
   *
   * @param array $data
   *   An array of data to pass to BlueSnap for the subscription charge.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When creating the transaction fails.
   */
  public function createCharge(array $data);

}
