<?php

namespace Drupal\commerce_bluesnap\EnhancedData;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for blueSnap enhanced data level config.
 */
interface ConfigInterface {

  /**
   * An identifier for Level 2 data to use throughout the code.
   */
  const LEVEL_2_ID = '2';
  /**
   * An identifier for Level 3 data to use throughout the code.
   */
  const LEVEL_3_ID = '3';

  /**
   * Build the form fields for BlueSnap enhanced data settings.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The parent from_state object to which this form is being attached to.
   *
   * @return array
   *   An array of form fields.
   *
   * @see \commerce_bluesnap_form_alter()
   */
  public function buildSettingsForm(FormStateInterface $form_state);

  /**
   * Returns the BlueSnap enhanced data settings for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to get the settings.
   *
   * @return array|null
   *   The BlueSnap enhanced data settings; NULL if no settings are defined for
   *   the given entity.
   */
  public function getSettings(ContentEntityInterface $entity);

}
