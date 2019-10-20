<?php

namespace Drupal\commerce_bluesnap;

use Drupal\entity\BundleFieldDefinition;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Functionality related to fields provided by the module.
 *
 * @todo Rename to field manager.
 */
class FieldService {

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $definitionManager;

  /**
   * Constructs a new FieldService object.
   *
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $definition_manager
   *   The entity definition update manager.
   */
  public function __construct(
    EntityDefinitionUpdateManagerInterface $definition_manager
  ) {
    $this->definitionManager = $definition_manager;
  }

  /**
   * Provides the field definition for the merchant transaction ID field.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition.
   */
  public static function paymentMerchantTransactionIdFieldDefinition() {
    return BundleFieldDefinition::create('string')
      ->setLabel(t('Merchant Transaction ID'))
      ->setDescription(t('The transaction ID sent to BlueSnap for a payment.'))
      ->setCardinality(1)
      ->setReadOnly(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('max_length', 36)
      ->setSetting('is_ascii', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  }

  /**
   * Provides the field definition for the subscription remote ID field.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition.
   */
  public static function subscriptionRemoteIdFieldDefinition() {
    return BaseFieldDefinition::create('string')
      ->setLabel(t('Remote ID'))
      ->setDescription(t('The remote ID on the subscription\'s payment gateway.'))
      ->setCardinality(1)
      ->setReadOnly(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);
  }

  /**
   * Installs the remote ID field for the commerce_subscription entity type.
   */
  public function installSubscriptionRemoteIdField() {
    $this->definitionManager->installFieldStorageDefinition(
      'remote_id',
      'commerce_subscription',
      'commerce_bluesnap',
      self::subscriptionRemoteIdFieldDefinition()
    );
  }

}
