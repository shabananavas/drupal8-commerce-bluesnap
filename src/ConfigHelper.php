<?php

namespace Drupal\commerce_bluesnap;

use Drupal\entity\BundleFieldDefinition;

/**
 * Helper class with functions related to BlueSnap configuration.
 *
 * It is likely that this will be converted to a service containing functions
 * for creating/deleting configuration fields; we'll wait a bit though before
 * doing so to see where the architecture is going.
 */
class ConfigHelper {

  /**
   * Returns the bundle field definition for creating a configuration field.
   *
   * @todo Move to the FieldService.
   *
   * @param string $label
   *   The label for the field.
   *
   * @return \Drupal\entity\BundleFieldDefinition
   *   The field definition.
   */
  public static function fieldDefinition($label) {
    return BundleFieldDefinition::create('bluesnap_config')
      ->setLabel(t($label))
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', ['region' => 'hidden'])
      ->setDisplayOptions('form', ['region' => 'hidden'])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  }

}
