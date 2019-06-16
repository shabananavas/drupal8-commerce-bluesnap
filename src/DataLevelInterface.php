<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Interface to process level 2/3 data in bluesnap transaction.
 *
 * Bluesnap's Enhanced data levels, such as Level 2 and Level 3, require extra
 * information to process transactions. This service provides a settings form to
 * configure the data level in store and methods for preparing the level 2 or
 * level 3 data depending on the card type and store settings.
 */
interface DataLevelInterface {

  /**
   * An identifier for Level 2 data to use throughout the code.
   */
  const LEVEL_2_ID = '2';
  /**
   * An identifier for Level 3 data to use throughout the code.
   */
  const LEVEL_3_ID = '3';

  /**
   * Build the form fields for BlueSnap data level settings for the given store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store for which we are building the settings form.
   *
   * @return array
   *   An array of form fields.
   *
   * @see \commerce_bluesnap_form_alter()
   */
  public function buildSettingsForm(StoreInterface $store);

  /**
   * Returns the BlueSnap data level settings for the given store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store for which to get the settings.
   *
   * @return array
   *   The BlueSnap data level settings.
   */
  public function getSettings(StoreInterface $store);

  /**
   * Returns the level 2/3 data for the given order and card type.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   The order for which to get the data.
   * @param string $card_type
   *   The card type; different card types require different data.
   *
   * @return array
   *   Level 2/3 data.
   */
  public function getData(OrderInterface $order, $card_type);

}
