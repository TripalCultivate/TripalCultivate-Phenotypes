<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldFormatter;
use Drupal\tripal_chado\TripalField\ChadoFormatterBase;

/**
 * Plugin implementation of the 'chado_methodunit_formatter_default' formatter.
 */
#[TripalFieldFormatter(
  id: 'chado_methodunit_formatter_default',
  label: new TranslatableMarkup('Chado Method Unit Formatter'),
  description: new TranslatableMarkup('Displays a method and its associated units in a collapsible summary.'),
  field_types: [
    'chado_methodunit_type_default',
  ],
)]
class ChadoMethodUnitFormatterDefault extends ChadoFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $item_values = $item->getValue();
      $storage_settings = $items->getFieldDefinition()->getSetting('storage_plugin_settings');
      $method_id_column = !empty($storage_settings['object_column'])
        ? $storage_settings['object_column']
        : 'cvterm_id';
      $method_id = (int) ($item_values[$method_id_column] ?? $item_values['cvterm_id'] ?? 0);
      $method_name = $item_values['cvterm_name'] ?? '';
      $definition = $item_values['cvterm_definition'] ?? '';
      $units = $method_id > 0
        ? \Drupal::service('trpcultivate_phenotypes.traits')->getMethodUnit($method_id)
        : [];
      $unit_names = array_map(static fn($unit) => $unit->name, $units);

      $elements[$delta] = [
        '#type' => 'details',
        '#title' => $method_name ?: $this->t('Method'),
        '#open' => FALSE,
        'definition' => [
          '#type' => 'inline_template',
          '#template' => '<p>{{ definition }}</p>',
          '#context' => ['definition' => $definition],
        ],
      ];

      if ($unit_names) {
        $elements[$delta]['units'] = [
          '#type' => 'inline_template',
          '#template' => '<p><em>{{ label }} {{ units|join(", ") }}</em></p>',
          '#context' => [
            'label' => $this->t('Measured in'),
            'units' => $unit_names,
          ],
        ];
      }
    }

    return $elements;
  }

}
