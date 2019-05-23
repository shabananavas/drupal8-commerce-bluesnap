<?php

namespace Drupal\commerce_bluesnap;

/**
 * Interface to process payments.
 */
interface FraudSessionInterface {

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
   *
   * @return array
   *   Render array which has bluesnap device datacollector iframe markup.
   */
  public function iframe($mode);

}
