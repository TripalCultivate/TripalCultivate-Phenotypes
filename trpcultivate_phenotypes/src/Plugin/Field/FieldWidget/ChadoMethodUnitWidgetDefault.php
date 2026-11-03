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

    $linker_fkey_column = ($storage_settings['base_table'] ?? '') === 'cvterm'
      ? 'cvterm_id'
      : (!empty($storage_settings['object_column']) ? $storage_settings['object_column'] : 'cvterm_id');
    $property_definitions = $items[$delta]->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();
    $field_name = $items->getFieldDefinition()->get('field_name');

    $item_vals = $items[$delta]->getValue();
    $record_id = $item_vals['record_id'] ?? 0;
    $linker_id = $item_vals['linker_id'] ?? 0;
    $link = $item_vals['link'] ?? 0;
    $cvterm_id = $item_vals[$linker_fkey_column] ?? 0;
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
      '#default_value' => $linker_fkey_column,
    ];
    // Pass the field machine name through the form for massageFormValues().
    $elements['field_name'] = [
      '#type' => 'value',
      '#default_value' => $field_name,
    ];

    $options = [
      'base_table' => 'cvterm',
      'column_name' => 'name',
      'type_column' => 'x',
      'property_table' => 'cvterm_relationship',
    ];

    $select_element = $this->genericSelectElement('cvterm_id', $cvterm_id, $options);
    $wrapper_id = 'method-definition-' . $field_name . '-' . $delta;

    // Insert the select element, either a select or an autocomplete depending
    // on the number of options.
    $elements[$linker_fkey_column] = $element + $select_element;
    $elements[$linker_fkey_column]['#weight'] = 10;
    $elements[$linker_fkey_column]['#ajax'] = [
      'callback' => [$this, 'updateMethodDefinition'],
      'event' => 'change',
      'wrapper' => $wrapper_id,
    ];
    $elements[$linker_fkey_column]['#element_validate'][] = [static::class, 'validateUniqueMethods'];

    $elements['method_definition_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
      'cvterm_definition' => [
        '#type' => 'textarea',
        '#title' => $this->t('Method definition'),
        '#default_value' => $definition,
        '#rows' => 4,
        '#attributes' => ['readonly' => 'readonly'],
        '#description' => $this->t('Definition from the selected method in Chado.'),
      ],
      '#weight' => 20,
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
    $values = $this->genericSelectMassageFormValues('cvterm_id', $values);
    return $this->massageLinkingFormValues('cvterm_id', $values, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::defaultSelectSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return $this->selectSettingsForm($form, $form_state) + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->selectSettingsSummary() + parent::settingsSummary();
  }

  /**
   * Form element validation handler ensuring methods are unique.
   *
   * @param array $element
   *   The method form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the entire entity form.
   */
  public static function validateUniqueMethods(array $element, FormStateInterface $form_state) {
    $parents = $element['#parents'];
    if (count($parents) < 3) {
      return;
    }

    // Get the parent elements of the field to access all values for this field.
    $field_parents = array_slice($parents, 0, -2);
    $current_delta = $parents[count($parents) - 2];
    $field_values = $form_state->getValue($field_parents, []);
    if (!is_array($field_values)) {
      return;
    }

    // Count the occurrences of each method ID in the field values.
    $method_counts = [];
    foreach ($field_values as $item) {
      if (!is_array($item)) {
        continue;
      }

      $method_id = self::getMethodId($item['cvterm_id'] ?? '');
      if ($method_id > 0) {
        $method_counts[$method_id] = ($method_counts[$method_id] ?? 0) + 1;
      }
    }

    // Get the current method value and check if it is unique.
    $current_values = $field_values[$current_delta] ?? [];
    if (!is_array($current_values)) {
      return;
    }
    $current_method_id = self::getMethodId($current_values['cvterm_id'] ?? '');
    if ($current_method_id > 0 && ($method_counts[$current_method_id] ?? 0) > 1) {
      $form_state->setErrorByName(
        implode('][', $parents),
        t("The method selected is not unique. Please select a different method.")
      );
    }
  }

  /**
   * AJAX callback to update method definition field when a method is selected.
   */
  public function updateMethodDefinition(array &$form, FormStateInterface $form_state) {
    // Get the triggering element and its value (Method name).
    $trigger = $form_state->getTriggeringElement();
    $method_value = $form_state->getValue($trigger['#parents']);
    $method_id = self::getMethodId($method_value);

    // Get the definition of the selected method from the database.
    $definition = '';
    if ($method_id > 0) {
      $definition = \Drupal::service('tripal_chado.database')->query(
      'SELECT definition FROM {1:cvterm} WHERE cvterm_id = :id',
      [':id' => $method_id],
      )->fetchField() ?: '';
    }

    // Find the wrapper element in the form to update its value.
    $wrapper_parents = $trigger['#array_parents'];
    array_pop($wrapper_parents);

    $widget = &$form;
    foreach ($wrapper_parents as $parent) {
      $widget = &$widget[$parent];
    }

    $widget['method_definition_wrapper']['cvterm_definition']['#value'] = $definition;

    return $widget['method_definition_wrapper'];
  }

  /**
   * Gets a method ID from a select value or autocomplete label.
   *
   * @param mixed $value
   *   The submitted method value.
   *
   * @return int
   *   The method ID, or zero when no ID is present.
   */
  private static function getMethodId(mixed $value): int {
    // If the value is numeric, return it as an integer.
    if (is_numeric($value)) {
      return (int) $value;
    }
    // If the value is a string and matches the pattern "(ID)",
    // extract and return the ID.
    if (is_string($value) && preg_match('/\((\d+)\)$/', $value, $matches)) {
      return (int) $matches[1];
    }
    return 0;
  }

}
