<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldWidget;
use Drupal\tripal_chado\TripalField\ChadoWidgetBase;

/**
 * Plugin implementation of the 'chado_methodunit_widget_default' field widget.
 */
#[TripalFieldWidget(
  id: 'chado_methodunit_widget_default',
  label: new TranslatableMarkup('Chado Method Unit Widget'),
  description: new TranslatableMarkup('A chado method unit widget.'),
  field_types: [
    'chado_methodunit_type_default',
  ],
)]
class ChadoMethodUnitWidgetDefault extends ChadoWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Get the field settings.
    $field_definition = $items[$delta]->getFieldDefinition();
    $storage_settings = $field_definition->getSetting('storage_plugin_settings');
    $object_column = ($storage_settings['base_table'] ?? '') === 'cvterm'
      ? 'cvterm_id'
      : (!empty($storage_settings['object_column']) ? $storage_settings['object_column'] : 'cvterm_id');
    $property_definitions = $items[$delta]->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();
    $field_name = $items->getFieldDefinition()->get('field_name');

    $item_vals = $items[$delta]->getValue();
    $record_id = $item_vals['record_id'] ?? 0;
    $linker_id = $item_vals['linker_id'] ?? 0;
    $link = $item_vals['link'] ?? 0;
    $method_name = $item_vals['cvterm_name'] ?? '';
    $definition = $item_vals['cvterm_definition'] ?? '';

    $elements = [];
    $elements['record_id'] = [
      '#type' => 'value',
      '#default_value' => $record_id,
    ];
    $elements['linker_id'] = [
      '#type' => 'value',
      '#default_value' => $linker_id,
    ];
    $elements['link'] = [
      '#type' => 'value',
      '#default_value' => $link,
    ];
    // Pass the method column through the form for massageFormValues().
    $elements['linker_fkey_column'] = [
      '#type' => 'value',
      '#default_value' => $object_column,
    ];
    // Pass the field machine name through the form for massageFormValues().
    $elements['field_name'] = [
      '#type' => 'value',
      '#default_value' => $field_name,
    ];

    $elements['cvterm_name'] = $element + [
      '#type' => 'textfield',
      '#title' => $this->t('Method'),
      '#default_value' => $method_name,
      '#maxlength' => 255,
      '#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      '#autocomplete_route_parameters' => [
        'count' => 10,
        'cv_id' => 0,
      ],
      '#description' => $this->t('Select an existing method.'),
    ];
    $elements['cvterm_definition'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Method definition'),
      '#default_value' => $definition,
      '#rows' => 4,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
      '#description' => $this->t('Definition from the selected method in Chado.'),
    ];

    // If there is a type_id and the value is not already set, then we want to
    // use the cvterm of the field as the default.
    if (array_key_exists('linker_type_id', $property_definitions)) {

      if (empty($item_vals['linker_type_id'])) {
        $termIdSpace = $this->getFieldSetting('termIdSpace');
        $termAccession = $this->getFieldSetting('termAccession');

        $idSpace_manager = \Drupal::service('tripal.collection_plugin_manager.idspace');
        $idSpace = $idSpace_manager->loadCollection($termIdSpace);
        $term = $idSpace->getTerm($termAccession);

        $item_vals['linker_type_id'] = $term->getInternalId();
      }

      $elements['linker_type_id'] = [
        '#type' => 'value',
        '#default_value' => $item_vals['linker_type_id'],
      ];
    }

    // If there is a rank and it is not already set,
    // then we want to use 0 as the default.
    if (array_key_exists('linker_rank', $property_definitions)) {
      $default_value = $item_vals['linker_rank'] ?? 0;
      $elements['linker_rank'] = [
        '#type' => 'value',
        '#default_value' => $default_value,
      ];
    }

    // Save some initial values to allow later handling of the "Remove" button.
    $this->saveInitialValues($delta, $field_name, $linker_id, $form_state);

    return $elements;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (!$values) {
      return $values;
    }

    $first_delta = array_key_first($values);
    $object_column = $values[$first_delta]['linker_fkey_column'] ?? 'cvterm_id';
    $field_name = $values[$first_delta]['field_name'] ?? 'method';
    $connection = \Drupal::service('tripal_chado.database');

    foreach ($values as $delta => $value) {
      $method_name = trim($value['cvterm_name'] ?? '');
      if ($method_name === '') {
        $values[$delta][$object_column] = 0;
        continue;
      }

      // Chado autocomplete labels may include additional term details in
      // parentheses; the cvterm name itself is the part before those details.
      $method_name = trim(preg_replace('/\s*\(.*?\)/', '', $method_name));
      $method_ids = $connection->select('1:cvterm', 'cvterm')
        ->fields('cvterm', ['cvterm_id'])
        ->condition('cvterm.name', $method_name, '=')
        ->range(0, 2)
        ->execute()
        ->fetchCol();

      if (count($method_ids) !== 1) {
        $message = count($method_ids) === 0
          ? $this->t('No existing method has this name.')
          : $this->t('More than one method has this name. Select a method with a unique name.');
        $form_state->setErrorByName($field_name, $message);
        $values[$delta][$object_column] = 0;
        continue;
      }

      $values[$delta][$object_column] = (int) $method_ids[0];
    }

    return $this->massageLinkingFormValues($object_column, $values, $form_state);
  }

}
