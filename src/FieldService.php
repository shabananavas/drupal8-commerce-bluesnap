<?php

namespace Drupal\commerce_bluesnap;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Functionality related to fields provided by the module.
 *
 * @todo Rename to field manager.
 */
class FieldService {

  /**
   * Provides the field definition for the subscription remote ID field.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition.
   */
  public static function subscriptionRemoteIdFieldDefinition() {
    return BaseFieldDefinition::create('string')
      ->setLabel(t('Remote ID'))
      ->setDescription(t("The remote ID on the subscription's payment gateway."))
      ->setCardinality(1)
      ->setReadOnly(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);
  }

}
