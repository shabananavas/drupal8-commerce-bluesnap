<?php

namespace Drupal\commerce_bluesnap\Plugin\Field\FieldWidget;

use Drupal\commerce_bluesnap\EnhancedData\DataInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Widget for collecting and storing Store configuration.
 *
 * The store configuration field currently holds the following settings:
 * - Enhanced data settings.
 *
 * @FieldWidget(
 *   id = "bluesnap_config_store",
 *   label = @Translation("BlueSnap per-store configuration"),
 *   field_types = {
 *     "bluesnap_config"
 *   }
 * )
 */
class StoreConfigWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    $settings = $items[$delta]->value;

    $element = [
      '#type' => 'details',
      '#title' => $this->t('BlueSnap configuration'),
      '#open' => TRUE,
    ];

    $element['enhanced_data']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable enhanced data level processing'),
      '#default_value' => $settings ? $settings['enhanced_data']['status'] : FALSE,
    ];

    // The level setting might be NULL if the status is set to FALSE.
    $level_default_value = DataInterface::LEVEL_2_ID;
    if (isset($settings['enhanced_data']['level'])) {
      $level_default_value = $settings['enhanced_data']['level'];
    }
    $element['enhanced_data']['level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Level'),
      '#options' => [
        DataInterface::LEVEL_2_ID => $this->t('Level 2'),
        DataInterface::LEVEL_3_ID => $this->t('Level 3'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="bluesnap_config[0][enhanced_data][status]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
      '#default_value' => $level_default_value,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state
  ) {
    // Only store the level if enhanced data is enabled.
    $level = NULL;
    if ($values[0]['enhanced_data']['status']) {
      $level = $values[0]['enhanced_data']['level'];
    }

    return [
      'value' => [
        'enhanced_data' => [
          'status' => $values[0]['enhanced_data']['status'],
          'level' => $level,
        ],
      ],
    ];
  }

}
