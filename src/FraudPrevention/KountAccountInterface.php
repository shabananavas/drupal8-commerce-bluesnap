<?php

namespace Drupal\commerce_bluesnap\FraudPrevention;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for service providing functionality related to the Kount account.
 */
interface KountAccountInterface {

  /**
   * Build the BlueSnap Kount settings form fields for the given store.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The parent from_state object to which this form is being attached to.
   *
   * @return array
   *   An array of form elements.
   *
   * @see \commerce_bluesnap_form_alter()
   */
  public function buildSettingsForm(FormStateInterface $form_state);

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
