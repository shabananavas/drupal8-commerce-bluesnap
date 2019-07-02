<?php

namespace Drupal\commerce_bluesnap\Plugin\Commerce\EntityTrait;

use Drupal\commerce_bluesnap\ConfigHelper;
use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;

/**
 * Trait for configuring Enhanced Data at the purchasable entity level.
 *
 * @CommerceEntityTrait(
 *   id = "purchasable_entity_bluesnap_enhanced_data",
 *   label = @Translation("BlueSnap enhanced data"),
 *   entity_types = {"commerce_product_variation"}
 * )
 */
class PurchasableEntityEnhancedData extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $field_definition = ConfigHelper::fieldDefinition('BlueSnap settings')
      ->setDisplayOptions('form', [
        'type' => 'bluesnap_config_enhanced_data',
        'region' => 'hidden',
      ]);

    return ['bluesnap_config_enhanced_data' => $field_definition];
  }

}
