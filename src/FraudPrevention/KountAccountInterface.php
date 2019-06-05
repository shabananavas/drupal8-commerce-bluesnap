<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Interface for service providing functionality related to the Kount account.
 */
interface KountAccountInterface {

  /**
   * Build the BlueSnap Kount settings form fields for the given store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store for which we are building the settings for.
   *
   * @return array
   *   An array of form elements.
   *
   * @see \commerce_bluesnap_form_alter()
   */
  public function buildSettingsForm(StoreInterface $store);

  /**
   * Returns the BlueSnap Kount settings for the given store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store for which to get the settings.
   *
   * @return array
   *   The BlueSnap Kount merchant id.
   */
  public function getSettings(StoreInterface $store);

  /**
   * Returns the BlueSnap Kount merchant ID for the given store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store for which to get the Kount merchant ID.
   *
   * @return string
   *   The BlueSnap Kount merchant id.
   */
  public function getMerchantId(StoreInterface $store);

}
