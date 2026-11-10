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
      $method_id = $item->get('cvterm_id')->getString();
      $method_name = $item->get('cvterm_name')->getString();
      $definition = $item->get('cvterm_definition')->getString();

      $entity = $item->getEntity();

      if ($entity->hasField('trait_cv')) {
        $trait_cv_id = $entity->get('trait_cv')->value ?? 0;
      }

      $trait_service = \Drupal::service('trpcultivate_phenotypes.traits');
      $genus_service = \Drupal::service('trpcultivate_phenotypes.genus_ontology');

      $genus = NULL;
      $unit_names = [];

      foreach ($genus_service->getConfiguredGenusList() as $candidate) {
        $config = $genus_service->getGenusOntologyConfigValues($candidate);
        if ((int) ($config['trait'] ?? 0) === (int) $trait_cv_id) {
          $genus = $candidate;
          break;
        }
      }

      if ($genus !== NULL) {
        $trait_service->setTraitGenus($genus);
        $units = $trait_service->getMethodUnit($method_id);
        $unit_names = array_map(static fn($unit) => $unit->name, $units);
      }

      $elements[$delta] = [
        '#type' => 'details',
        '#title' => $method_name,
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
