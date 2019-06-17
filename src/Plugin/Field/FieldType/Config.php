<?php

namespace Drupal\commerce_bluesnap\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines a field type for storing BlueSnap configuration in JSON format.
 *
 * @FieldType(
 *   id = "bluesnap_config",
 *   label = @Translation("BlueSnap configuration"),
 *   description = @Translation("A field containing BlueSnap configuration settings."),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\Core\Field\MapFieldItemList",
 * )
 */
class Config extends MapItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(
    FieldStorageDefinitionInterface $field_definition
  ) {
    $properties['value'] = MapDataDefinition::create()
      ->setLabel(t('BlueSnap configuration'));

    return $properties;
  }

}
