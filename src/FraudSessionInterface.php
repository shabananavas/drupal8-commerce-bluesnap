<?php

namespace Drupal\commerce_bluesnap;

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
   * Build the form fields for kount settings.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   A store entity, if the settings are for a store.
   *
   * @return array
   *   An array of form fields.
   *
   * @see \commerce_bluesnap_form_alter()
   */
  public function buildSettingsForm(StoreInterface $store);

  /**
   * Provides bluesnap kount settings.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   Store entity.
   *
   * @return array
   *   Bluesnap kount settings
   */
  public function getSettings(StoreInterface $store);

  /**
   * Provides bluesnap kount merchant ID.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   Store entity.
   *
   * @return string
   *   Bluesnap kount merchant id
   */
  public function getKountMerchantId(StoreInterface $store);

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
   *
   * @return array
   *   Render array which has bluesnap device datacollector iframe markup.
   */
  public function iframe($mode, StoreInterface $store);

}
