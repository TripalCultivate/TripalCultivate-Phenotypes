<?php

namespace Drupal\trpcultivate_phenotypes\Service;

/**
 * Service class for setup module functions.
 */
class SetupModuleService {

  /**
   * The columns to add to chado.
   *
   * @var array
   *   Each element is a column to add to chado with the following values:
   *    - table: the table the column should be added to.
   *    - column: the column to add.
   *    - schema: the Drupal schema array describing the column to create.
   *    - references: describes the foreign key this new column represents
   *      with table/column keys. If this column is not a foreign key then
   *      this should be FALSE.
   */
  public const CHADO_COLUMNS = [
    'phenotype.project_id' => [
      'table' => 'phenotype',
      'column' => 'project_id',
      'schema' => [
        'description' => 'The project or experiment associated with this measurement.',
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ],
      'references' => [
        'table' => 'project',
        'column' => 'project_id',
      ],
    ],
    'phenotype.stock_id' => [
      'table' => 'phenotype',
      'column' => 'stock_id',
      'schema' => [
        'description' => 'The germplasm associated with this measurement.',
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ],
      'references' => [
        'table' => 'stock',
        'column' => 'stock_id',
      ],
    ],
    'phenotype.unit_id' => [
      'table' => 'phenotype',
      'column' => 'unit_id',
      'schema' => [
        'description' => 'The unit of measurement used in this measurement.',
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ],
      'references' => [
        'table' => 'cvterm',
        'column' => 'cvterm_id',
      ],
    ],
    'phenotypeprop.cvalue_id' => [
      'table' => 'phenotypeprop',
      'column' => 'cvalue_id',
      'schema' => [
        'description' => 'The scale values used in this measurement.',
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ],
      'references' => [
        'table' => 'cvterm',
        'column' => 'cvterm_id',
      ],
    ],
  ];

  /**
   * Install the ontology terms.
   *
   * Expected to be run by a Tripal Job.
   */
  public static function installOntologyTerms() {
    // We need the schema to ensure we are inserting into the right one.
    $connection = \Drupal::service('tripal_chado.database');
    $schema = $connection->getSchemaName();

    // Create terms defined in config entity.
    \Drupal::service('trpcultivate_phenotypes.terms')
      ->loadTerms($schema);

    // Create genus-ontology configuration.
    \Drupal::service('trpcultivate_phenotypes.genus_ontology')
      ->loadGenusOntology();
  }

  /**
   * Alters chado tables for use with this module.
   *
   * More specifically, we add the following columns:
   * - phenotype.project_id
   * - phenotype.stock_id
   * - phenotype.unit_id
   * - phenotypeprop.cvalue_id
   * This provides a backwards compatible change to better manage phenotypic
   * data points.
   */
  public static function alterChadoTables() {

    $connection = \Drupal::service('tripal_chado.database');
    $schema = $connection->schema();
    foreach (self::CHADO_COLUMNS as $spec) {

      // Add the column.
      if (!$schema->fieldExists($spec['table'], $spec['column'])) {
        $schema->addField($spec['table'], $spec['column'], $spec['schema']);
      }

      // We don't have a specific foreign key method right now in TripalDBX
      // so let's add the constraint separately here.
      // Note: If this column should not be a foreign key then references
      // is expected to be FALSE.
      if (!$schema->foreignKeyConstraintExists($spec['table'], $spec['column']) && is_array($spec['references'])) {
        $connection->query('ALTER TABLE {1:' . $spec['table'] . '}
          ADD CONSTRAINT ' . $spec['table'] . '_' . $spec['column'] . '_fkey
          FOREIGN key(' . $spec['column'] . ')
          REFERENCES {1:' . $spec['references']['table'] . '} (' . $spec['references']['column'] . ')'
        );
      }
    }
  }

  /**
   * Setup the following passport data terms:
   *
   * - name (config name: germplasm_name)
   * - Entry (sample_entry)
   * - block (experiment_group)
   * - Sample Type (experiment_container)
   * - replicate (replicate)
   * - Timepoint (experiment_timepoint)
   * - treatment (experiment_treatment)
   *
   * @see trpcultivate_phenotypes/config/trpcultivate_phenotypes.settings.yml
   * @see trpcultivate_phenotypes.install
   */
  public static function setupPassportDataTerms(): void {

    $term_service = \Drupal::service('trpcultivate_phenotypes.terms');

    // The following are non-passport data terms used by the module to establish
    // relationships between data. Set value for each term must be maintained to
    // preserve existing data relationships after the re-install ruotine below.
    // Save the current value set to each term.
    $cache_config_value = [];

    foreach ([
      'genus',
      'unit_type',
      'data_collector',
      'unit_to_method_relationship_type',
      'method_to_trait_relationship_type',
      'trait_to_synonym_relationship_type',
    ] as $term) {

      $cache_config_value[$term] = $term_service->getTermId($term);
    }

    // Re-install terms and restore set configuration values for each
    // non-passport term.
    $term_service->loadTerms();
    $term_service->saveTermConfigValues($cache_config_value);
  }

}
