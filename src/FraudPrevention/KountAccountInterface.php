<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Interface to process fraud prevention in bluesnap transactions.
 */
interface KountAccountInterface {

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

}
