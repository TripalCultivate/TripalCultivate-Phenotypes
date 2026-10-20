<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Field\FieldType;

use Drupal\core\Field\FieldDefinitionInterface;
use Drupal\core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldType;
use Drupal\tripal\Entity\TripalEntityType;
use Drupal\tripal_chado\TripalField\ChadoFieldItemBase;
use Drupal\tripal_chado\TripalStorage\ChadoIntStoragePropertyType;
// Make sure to include the Property type class you are going to create
// in your addTypes() method below.
use Drupal\tripal_chado\TripalStorage\ChadoVarCharStoragePropertyType;
use Drupal\tripal_chado\TripalStorage\ChadoTextStoragePropertyType;

/**
 * Plugin implementation of the 'chado_methodunit_type_default' field type.
 */
#[TripalFieldType(
  id: 'chado_methodunit_type_default',
  category: 'tripal_chado',
  label: new TranslatableMarkup('Chado Method Unit'),
  description: new TranslatableMarkup('Add a mehtod and a unit associated with the trait.'),
  default_widget: 'chado_methodunit_widget_default',
  default_formatter: 'chado_methodunit_formatter_default',
)]
class ChadoMethodUnitTypeDefault extends ChadoFieldItemBase {

  /**
   * The unique identifier of this field.
   *
   * NOTE: must match the id in the Attribute.
   *
   * @var string
   */
  public static $id = "chado_methodunit_type_default";

  /**
   * Flag to indicate a base column selector should be shown.
   *
   * @var bool
   *   When TRUE the ChadoFieldItemBase parent class will add a base column
   *   select list on the field configuration form. When FALSE, no selector
   *   will be available.
   */
  protected static $select_base_column = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    // The property that indicates if this field is empty.
    return 'linker_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function mainDisplayPropertyName() {
    // The property to use in the entity title/url.
    return 'value';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    // If this field needs to set a fixed value, set this to TRUE.
    // It indicates to the publishing step to include this field.
    // If not set, then the publishing step may not be able to find matches
    // for this field based on the fixed value.
    $settings['fixed_value'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $storage_settings = parent::defaultStorageSettings();
    $storage_settings['storage_plugin_settings']['base_column'] = '';
    $storage_settings['storage_plugin_settings']['linker_table'] = '';
    $storage_settings['storage_plugin_settings']['subject_column'] = '';
    $storage_settings['storage_plugin_settings']['object_column'] = '';

    return $storage_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = parent::storageSettingsForm($form, $form_state, $has_data);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function tripalTypes($field_definition) {

    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $storage_settings = $field_definition->getSetting('storage_plugin_settings');
    $base_table = $storage_settings['base_table'];

    if (empty($base_table)) {
      return;
    }

    $terms = [
      'record_id' => self::$record_id_term,
    ];

    $schemaObj = \Drupal::service('tripal_chado.database')->schema();
    $mappingObj = \Drupal::entityTypeManager()
      ->getStorage('chado_term_mapping')
      ->load('core_mapping');

    // Base table.
    $base_schema_def = $schemaObj->getTableDef($base_table, ['format' => 'Drupal']);
    $base_pkey_col = $base_schema_def['primary key'];
    $terms['base_pkey'] = $terms['record_id'];

    // Cvterm object table.
    $object_table = 'cvterm';
    $object_id = 'cvterm_id';
    $object_schema_def = $schemaObj->getTableDef($object_table, ['format' => 'Drupal']);
    $object_pkey_col = $object_schema_def['primary key'];
    $terms['object_pkey'] = $terms['record_id'];

    $terms['name'] = $mappingObj->getColumnTermId($object_table, 'name') ?: 'schema:name';
    $terms['definition'] = $mappingObj->getColumnTermId($object_table, 'definition') ?: 'schema:description';

    // Linking table.
    [$linker_table, $linker_fkey_column] = self::get_linker_table_and_column(
      $storage_settings,
      $base_table,
      $object_pkey_col
    );
    if ($base_table === 'cvterm') {
      $linker_table = 'cvterm_relationship';
      $linker_fkey_column = 'object_id';
      $linker_left_col = 'subject_id';
    }
    else {
      $linker_left_col = self::getChadoForeignKeyColumn($linker_table, $base_table, $schemaObj);
    }
    $linker_schema_def = $schemaObj->getTableDef($linker_table, ['format' => 'Drupal']);
    $linker_pkey_col = $linker_schema_def['primary key'];
    $terms['linker_pkey'] = $terms['record_id'];

    $terms['linker_left'] = $mappingObj->getColumnTermId($linker_table, $linker_left_col)
      ?: self::$record_id_term;
    $terms['linker_right'] = $mappingObj->getColumnTermId($linker_table, $linker_fkey_column)
      ?: self::$record_id_term;
    $terms['linker_type_id'] = $mappingObj->getColumnTermId($linker_table, 'type_id')
      ?: 'schema:additionalType';
    $terms['linker_rank'] = $mappingObj->getColumnTermId($linker_table, 'rank')
      ?: 'OBCS:0000117';

    // Keep records for different linker roles separate.
    $field_settings = $field_definition->getSettings();
    $term = $field_settings['termIdSpace'] . ':' . $field_settings['termAccession'];
    $table_alias = $linker_table . '_' . preg_replace('/[^a-z0-9]+/', '', strtolower($term));
    $table_mapping = [$table_alias => $linker_table];

    $properties = [];

    $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'record_id', $terms['base_pkey'], [
      'action' => 'store_id',
      'drupal_store' => TRUE,
      'path' => $base_table . '.' . $base_pkey_col,
    ]);

    $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'entity_id', self::$drupal_entity_term, [
      'action' => 'function',
      'drupal_store' => TRUE,
      'namespace' => self::$chadostorage_namespace,
      'function' => self::$drupal_entity_callback,
      'ftable' => $object_table,
      'fkey' => $object_id,
    ]);

    $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'linker_id', $terms['linker_pkey'], [
      'action' => 'store_pkey',
      'drupal_store' => TRUE,
      'path' => $table_alias . '.' . $linker_pkey_col,
      'table_alias_mapping' => $table_mapping,
    ]);

    $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'link', $terms['linker_left'], [
      'action' => 'store_link',
      'drupal_store' => TRUE,
      'path' => $base_table . '.' . $base_pkey_col . '>' . $table_alias . '.' . $linker_left_col,
      'table_alias_mapping' => $table_mapping,
    ]);

    $object_property = $base_table === 'cvterm' ? 'cvterm_id' : $linker_fkey_column;
    $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, $object_property, $terms['linker_right'], [
      'action' => 'store',
      'drupal_store' => TRUE,
      'path' => $table_alias . '.' . $linker_fkey_column,
      'table_alias_mapping' => $table_mapping,
      'delete_if_empty' => TRUE,
      'empty_value' => 0,
    ]);

    $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'linker_type_id', $terms['linker_type_id'], [
      'action' => 'store',
      'drupal_store' => FALSE,
      'path' => $table_alias . '.type_id',
      'table_alias_mapping' => $table_mapping,
      'as' => 'linker_type_id',
    ]);

    // cvterm_relationship.rank defaults to 0, so don't include it in the
    // cvterm-backed method lookup query.
    if ($base_table !== 'cvterm') {
      $properties[] = new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'linker_rank', $terms['linker_rank'], [
        'action' => 'store',
        'drupal_store' => FALSE,
        'path' => $table_alias . '.rank',
        'table_alias_mapping' => $table_mapping,
        'as' => 'linker_rank',
      ]);
    }

    $properties[] = new ChadoVarCharStoragePropertyType($entity_type_id, self::$id, 'cvterm_name', $terms['name'], 255, [
      'action' => 'read_value',
      'drupal_store' => FALSE,
      'path' => $table_alias . '.' . $linker_fkey_column . '>' . $object_table . '.' . $object_pkey_col . ';name',
      'table_alias_mapping' => $table_mapping,
      'as' => 'cvterm_name',
    ]);

    $properties[] = new ChadoTextStoragePropertyType($entity_type_id, self::$id, 'cvterm_definition', $terms['definition'], [
      'action' => 'read_value',
      'drupal_store' => FALSE,
      'path' => $table_alias . '.' . $linker_fkey_column . '>' . $object_table . '.' . $object_pkey_col . ';definition',
      'table_alias_mapping' => $table_mapping,
      'as' => 'cvterm_definition',
    ]);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = [];
    $max_length = 100;

    // Generate a random value to use as a sample.
    $values['record_id'] = 0;
    $values['entity_id'] = 0;
    $values['linker_id'] = 0;
    $values['link'] = 0;
    $values['linker_type_id'] = mt_rand(1, $max_length);
    $values['linker_rank'] = 0;
    $values['cvterm_name'] = '';
    $values['cvterm_definition'] = '';

    return [$values];
  }

  /**
   * We need to set the type_id property value to match the cvterm_id.
   *
   * To do this we'll override the tripalValuesTemplate() and give the
   * `type_id` property a default value.
   *
   * {@inheritDoc}
   *
   * @see \Drupal\tripal\TripalField\TripalFieldItemBase::tripalValuesTemplate()
   */
  public function tripalValuesTemplate($field_definition, $default_value = NULL) {
    $prop_values = parent::tripalValuesTemplate($field_definition, $default_value);

    $settings = $field_definition->getSettings();

    $termIdSpace = $settings['termIdSpace'];
    $termAccession = $settings['termAccession'];

    /** @var \Drupal\tripal\TripalVocabTerms\PluginManagers\TripalIdSpaceManager $idSpace_manager **/
    /** @var \Drupal\tripal\TripalVocabTerms\TripalIdSpaceBase $idSpace **/
    /** @var \Drupal\tripal\TripalVocabTerms\TripalTerm $term **/
    $idSpace_manager = \Drupal::service('tripal.collection_plugin_manager.idspace');
    $idSpace = $idSpace_manager->loadCollection($termIdSpace);
    $term = $idSpace->getTerm($termAccession);

    foreach ($prop_values as $index => $prop_value) {
      if ($prop_value->getKey() == 'linker_type_id') {
        $prop_values[$index]->setValue($term->getInternalId());
      }
    }

    return $prop_values;
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\tripal_chado\TripalField\ChadoFieldItemBase::isCompatible()
   */
  public function isCompatible(TripalEntityType $entity_type) : bool {
    // Get the base table for the content type.
    $has_linker = FALSE;
    $base_table = $entity_type->getThirdPartySetting('tripal', 'chado_base_table');
    $chado = \Drupal::service('tripal_chado.database');
    $schema = $chado->schema();
    $relationship_table = $base_table . '_relationship';
    $table_exists = $schema->tableExists($relationship_table);
    if ($table_exists) {
      $has_linker = TRUE;
    }

    // Ensure that the relationship table has a type_id.
    $has_type_id = FALSE;

    // Only check for the type_id column if the relationship table exists,
    // otherwise checking for the field would throw a database exception.
    if ($has_linker) {
      $has_type_id = $schema->fieldExists($relationship_table, 'type_id');
      if (!$has_type_id) {
        \Drupal::messenger()->addError('The Chado Method Unit Type field requires a type_id in the linking table. This is not present in Chado 1.31 but will likely be added in subsequent versions.');
      }
    }

    // Only compatible if there is a linker and it has a type_id.
    if ($has_linker and $has_type_id) {
      return TRUE;
    }
    return FALSE;
  }

}
