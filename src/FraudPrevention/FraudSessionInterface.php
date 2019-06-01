<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

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
   * @param int|null $kount_merchant_id
   *   The Kount merchant Id, if we have one (enterprise accounts); NULL
   *   otherwise.
   *
   * @return array
   *   Render array which has bluesnap device datacollector iframe markup.
   */
  public function iframe($mode, $kount_merchant_id = NULL);

}
