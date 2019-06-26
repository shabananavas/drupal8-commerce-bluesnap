<?php

namespace Drupal\commerce_bluesnap\Plugin\Field\FieldWidget;

use Drupal\commerce_bluesnap\EnhancedData\DataInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Widget for collecting and storing Enhanced Data configuration.
 *
 * @FieldWidget(
 *   id = "bluesnap_config_enhanced_data",
 *   label = @Translation("BlueSnap enhanced data configuration"),
 *   field_types = {
 *     "bluesnap_config"
 *   }
 * )
 */
class EnhancedDataConfigWidget extends WidgetBase {

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
      '#title' => $this->t('BlueSnap enhanced data'),
      '#open' => TRUE,
    ];

    $element['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable enhanced data level processing'),
      '#default_value' => $settings ? $settings['status'] : FALSE,
    ];

    // The level setting might be NULL if the status is set to FALSE.
    $level_default_value = DataInterface::LEVEL_2_ID;
    if (isset($settings['level'])) {
      $level_default_value = $settings['level'];
    }
    $element['level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Level'),
      '#options' => [
        DataInterface::LEVEL_2_ID => $this->t('Level 2'),
        DataInterface::LEVEL_3_ID => $this->t('Level 3'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="bluesnap_config_enhanced_data[0][status]"]' => [
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
    return [
      'value' => [
        'status' => $values[0]['status'],
        // Only store the level if enhanced data is enabled.
        'level' => $values[0]['status'] ? $values[0]['level'] : NULL,
      ],
    ];
  }

}
