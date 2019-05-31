<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Interface to process fraud prevention in bluesnap transactions.
 */
interface FraudSessionInterface {

  /**
   * Bluesnap API production URL.
   */
  const API_URL_PRODUCTION = 'https://www.bluesnap.com';

  /**
   * Bluesnap API sandbox URL.
   */
  const API_URL_SANDBOX = 'https://sandbox.bluesnap.com';

  /**
   * BlueSnap's Kount Merchant ID .
   */
  const KOUNT_MERCHANT_ID = '700000';

  /**
   * Returns the fraud session ID.
   *
   * A new ID will be generated if none exists yet.
   *
   * @return string
   *   Bluesnap fraud session ID
   */
  public function get();

  /**
   * Generates bluesnap fraud session ID.
   *
   * @return string
   *   Bluesnap fraud session ID
   */
  public function generate();

  /**
   * Removes fraud session ID from user temp storage.
   */
  public function remove();

  /**
   * Provides bluesnap device datacollector iframe.
   *
   * @param string $mode
   *   The bluesnap exchange rate API mode, test or production.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   Store entity.
   * @param int $kount_merchant_id
   *   Enterprise kount merchant Id.
   *
   * @return array
   *   Render array which has bluesnap device datacollector iframe markup.
   */
  public function iframe($mode, StoreInterface $store, $kount_merchant_id = NULL);

}
