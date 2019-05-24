<?php

namespace Drupal\commerce_bluesnap;

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
   *
   * @return array
   *   Level 2/3 data.
   */
  public function getData(OrderInterface $order);

  /**
   * Provides bluesnap level2 data for transaction processing.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return array
   *   Array of level 2 data.
   */
  public function level2Data(OrderInterface $order);

  /**
   * Provides bluesnap level3 data for transaction processing.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return array
   *   Array of level 3 data.
   */
  public function level3Data(OrderInterface $order);

  /**
   * Provides customer reference number of an order.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return string
   *   Order customer reference number.
   */
  public function getCustomerReferenceNumber(OrderInterface $order);

  /**
   * Provides order adjustment total amount.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   * @param string $type
   *   Adjustment type.
   *
   * @return int
   *   Adjusment total amount.
   */
  public function getAdjustment(OrderInterface $order, $type);

  /**
   * Provides sum of tax rates associated with an order.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return int
   *   Sum of tax rates.
   */
  public function getTaxRate(OrderInterface $order);

  /**
   * Provides shipping country code and zip code.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return array|null
   *   Array of shipping country and zip code, null if no shipping info.
   */
  public function getShippingInfo(OrderInterface $order);

}
