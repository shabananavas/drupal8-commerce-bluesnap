<?php

namespace Drupal\commerce_bluesnap;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Interface to process level 2/3 data in bluesnap transaction.
 */
interface DataLevelServiceInterface {

  /**
   * Build the form fields for bluesnap datalevel settings.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   A store entity, if the settings are for a store.
   *
   * @return array
   *   An array of form fields.
   *
   * @see \commerce_bluesnap_form_alter()
   */
  public function buildDataLevelSettingsForm(StoreInterface $store);

  /**
   * Provides bluesnap data level settings.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   Store entity.
   *
   * @return array
   *   Bluesnap data level settings
   */
  public function getDataLevelSetting(StoreInterface $store);

  /**
   * Provides level 2/3 data.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Store entity.
   * @param string $card_type
   *   The card type.
   *
   * @return array
   *   Level 2/3 data.
   */
  public function getData(OrderInterface $order, $card_type);

}
