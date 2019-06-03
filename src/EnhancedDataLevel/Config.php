<?php

namespace Drupal\commerce_bluesnap\EnhancedDataLevel;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Bluesnap enhanced data level config class.
 *
 * Bluesnap's Enhanced data levels, such as Level 2 and Level 3,
 * require extra information to process the transaction .
 * This service provides a settings form to configure the data
 * level in content entity (Store/Product Variation).
 */
class Config implements ConfigInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(FormStateInterface $form_state) {
    $entity = $form_state->getFormObject()->getEntity();

    $form = [
      '#type' => 'details',
      '#title' => $this->t('Data level settings'),
      '#open' => TRUE,
    ];

    // Build the form elements.
    $settings = $this->getSettings($entity);

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable BlueSnap level 2/3 data processing'),
      '#default_value' => $settings ? $settings->status : FALSE,
    ];
    $form['level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Data processing level'),
      '#options' => [
        self::LEVEL_2_ID => $this->t('Level 2'),
        self::LEVEL_3_ID => $this->t('Level 3'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="bluesnap[data_level][status]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      '#default_value' => $settings ? $settings->level : self::LEVEL_2_ID,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(ContentEntityInterface $entity) {
    $settings = $entity->get('bluesnap_settings')->value;
    $settings = json_decode($settings);

    return $settings->data_level;
  }

}
