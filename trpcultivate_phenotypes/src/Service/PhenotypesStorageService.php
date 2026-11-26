<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;
use Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;

/**
 * Phenotypes Storage Service.
 *
 * This service handles operations create, read, update and delete on phenotypes
 * and phenotypesprop table in Drupal and Chado database schema.
 *
 * Definitions:
 * - raw - refers to unpublished phenotypes.
 * - analyzed - refers to sanitized and published phenotypes.
 */
class PhenotypesStorageService {

  /**
   * Phenotypes data type context.
   *
   * Operations on phenotypes will be restricted to data type context.
   *
   * @var string|null
   */
  protected string|null $phenotype_context = NULL;

  /**
   * Database connection reference based on phenotype context.
   *
   * Phenotype Context - database connection:
   * - raw: public (Drupal database connection).
   * - analyzed: Chado database connection.
   *
   * @var object
   */
  private object $database_connection;

  /**
   * The table name that contains phenotypes.
   *
   * @var string
   */
  public const PHENOTYPES_TABLE = 'phenotype';

  /**
   * The table name that contains phenotypes property (passport data).
   *
   * @var string
   */
  public const PHENOTYPESPROP_TABLE = 'phenotypeprop';

  /**
   * Context key to alias mapping array.
   *
   * @var array
   */
  public const DATA_TYPE_MODULE_MAP = [
    'raw' => 'trpcultivate_phenocollect',
    'analyzed' => 'trpculitvate_phenoshare',
  ];

  /**
   * Passport data terms.
   *
   * This is a list of passport data configuration names that corresponds to the
   * first 7 columns in a data file.
   *
   * In a data file row, the passport data columns are indexed (0-based) 0 for
   * name, 1 for entry, 2 for block, 3 for Sample Type, 4 for replicate, 5 for
   * Timepoint, and 6 for treatment.
   *
   * - name (config name: germplasm_name) aka: Germplasm Name
   * - Entry (sample_entry) aka: Sample Name
   * - block (experiment_group) aka: Group
   * - Sample Type (experiment_container) aka: Experimental Unit
   * - replicate (replicate) aka: Replicate
   * - Timepoint (experiment_timepoint) aka: Timepoint
   * - treatment (experiment_treatment) aka: Treatment
   *
   * Exactly seven items make up the passport data columns. The order of the
   * items is critical and must be preserved exactly as specified.
   *
   * @var array
   *
  public const PASSPORT_DATA_CONFIG_NAMES = [
    0 => 'germplasm_name',
    1 => 'sample_entry',
    2 => 'experiment_group',
    3 => 'experiment_container',
    4 => 'replicate',
    5 => 'experiment_timepoint',
    6 => 'experiment_treatment'
  ];

  /**
   * Passport data configuration name to configuration value mapping.
   *
   * @var array
   */
  protected array $passport_data_config_values = [];

  /**
   * Phenotypic trait (label) to trait-method-unit id mapping.
   *
   * @var array
   */
  public array $phenotypic_trait_ids = [];

  /**
   * Phenotypes Storage Service class constructor.
   *
   * @param \Drupal\Core\Database\Connection $drupaldb_connection
   *   Drupal database connection.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\tripal\Services\TripalLogger $tripal_logger
   *   Tripal logger service.
   */
  public function __construct(
    protected UuidInterface $service_uuid,
    protected Connection $drupaldb_connection,
    protected ModuleHandlerInterface $module_handler,
    protected ChadoConnection $chado_connection,
    protected TripalLogger $tripal_logger,
    protected ExperimentPhenoComboService $service_PhenoCombo,
    protected TripalCultivatePhenotypesTermsService $service_Terms,
  ) {

    $this->passport_data_config_values = $this->resolvePassportDataConfigurationNames();
  }

  /**
   * Set the phenotype context and associated database connection.
   *
   * This context is used to restrict all operations so that only
   * records belonging to the specified phenotype context can be accessed,
   * retrieved or modified. In addition, it determines which database housing
   * relevant phenotype relations to use.
   *
   * Phenotype Context - database connection:
   * - raw: public (Drupal database connection).
   * - analyzed: Chado database connection.
   *
   * @throws \Exception
   *   - If Phenotype context key is not 'raw' nor 'analyzed'.
   *   - If corresponding module of a data type is not installed/enabled module.
   */
  public function setPhenotypeContext(string $phenotype_context): void {

    $data_types = array_keys(self::DATA_TYPE_MODULE_MAP);
    if (!in_array($phenotype_context, $data_types)) {
      throw new \Exception(
        sprintf(
          'Unable to set phenotype context: %s is not a supported context key. Use [%s].',
          $phenotype_context, implode(', ', $data_types)
        )
      );
    }

    $module_name = self::DATA_TYPE_MODULE_MAP[$phenotype_context];
    if (!$this->module_handler->moduleExists($module_name)) {
      throw new \Exception(
        sprintf(
          'Unable to set phenotype context: %s is not installed/enabled module.
           Please install this module and try again.',
          $module_name
        )
      );
    }

    switch ($phenotype_context) {

      case 'raw':
        $this->database_connection = $this->drupaldb_connection;
        break;

      case 'analyzed':
        $this->database_connection = $this->chado_connection;
        break;
    }

    $this->phenotype_context = $phenotype_context;
  }

  /**
   * Insert a single row of phenotypic data.
   *
   * @param array $headers
   *   An array containing phenotypic trait names (column headers) defined by
   *   the phenotypes importer.
   * @param array $pheno_values
   *   An array containing phenotypic values corresponding to the supplied
   *   traits. Values are organized so that it correspond to its respective
   *   trait by column (index) position.
   */
  public function insertPhenotypeRow(array $headers, array $pheno_values): void {

    $this->ensureDataTypeContextIsSet();
    $this->ensureResolvedPhenotypicTraits();

    if (empty($pheno_traits) || empty($pheno_values)) {
      throw new \InvalidArgumentException(
        'Unable to insert phenotypic data. Trait headers or phenotypic values are missing.'
      );
    }

    // Perform basic validation check to ensure no empty entries on required
    // traits are saved.
    $sanitized_passport_data = $this->sanitizePassportData($pheno_values);
    $sanitized_measurement_data = $this->sanitizeMeasurements($headers, $pheno_values);

    // Insert phenotype.
    $phenotype_id = $this->insertPassportData($sanitized_passport_data);
    $this->insertMeasurements($phenotype_id, $sanitized_measurement_data);
  }

  /**
   * Validate and sanitize phenotype passport data.
   *
   * @param array $pheno_value
   *   An array containing phenotypic values corresponding to the supplied
   *   traits.
   *   @see Drupal\trpcultivate_phenotypes\Service\PhenotypeStorageService::insertPhenotypeRow()
   *
   * @return array
   */
  protected function sanitizePassportData(array $pheno_values): array {

    $sanitized_passport_data = [];

    // The first seven values in the data row.
    $passport_data = array_slice($phenotypes, 0, count(self::PASSPORT_DATA_CONFIG_NAMES));
    foreach ($passport_data as $i => $data) {
      $data = trim($data);

      // Ensure each column has a value.
      if (empty($data)) {
        throw new \InvalidArgumentException(
          'Passport data column missing error. A required column in index #' . $i . ' is missing.'
        );
      }

      $sanitized_passport_data[$this->passport_data_config_values[$i]] = $data;
    }

    return $sanitized_passport_data;
  }

  /**
   * Validate and sanitize phenotype measurement data.
   *
   * @param array $headers
   *   An array containing phenotypic trait names (column headers) defined by
   *   the phenotypes importer.
  *  @param array $pheno_value
   *   An array containing phenotypic values corresponding to the supplied
   *   traits.
   *   @see Drupal\trpcultivate_phenotypes\Service\PhenotypeStorageService::insertPhenotypeRow()
   *
   * @return array
   */
  protected function sanitizeMeasurements(array $headers, $pheno_values): array {

    $sanitized_measurement_data = [];

    return $sanitized_measurement_data;
  }

  /**
   * Insert phenotype passport data.
   *
   * @return int
   *   The phenotype_id assigned to the last inserted row in phenotype table.
   */
  protected function insertPassportData(array $passport_data): int {

    $this->database_connection->insert(self::PHENOTYPESPROP_TABLE)
      ->fields(

      )
      ->execute();

    return $phenotype_id;
  }

  /**
   * Insert phenotype measurement data.
   *
   * @return void
   */
  protected function insertMeasurement(): void {

  }

  /**
   * Resolve passport data configuration names to configuration values.
   *
   * Retrieve the configuration value set to each configuration name set in the
   * phenotypes ontology configuration form.
   *
   * @return array
   */
  public function resolvePassportDataConfigurationNames(): array {

    $resolved_config_names = [];

    foreach (self::PASSPORT_DATA_CONFIG_NAMES as $config_name) {
      $resolved_config_names[$config_name] = $this->service_Terms->getTermId($config_name);
    }

    return $resolved_config_names;
  }

  /**
   * Resolve phenotypic trait label into trait-method-unit ids.
   *
   * @param array $headers
   *   An array containing phenotypic trait names (column headers) defined by
   *   the phenotypes importer.
   */
  public function resolvePhenotypicTraitLabels(array $headers, string $genus): void {

    $this->ensureDataTypeContextIsSet();

    $this->service_PhenoCombo->setGenus($genus);

    // Trait columns after the passport data columns.
    $trait_labels = array_slice($headers, count(self::PASSPORT_DATA_CONFIG_NAMES) + 1);

    foreach($trait_labels as $label) {
      $this->phenotypic_trait_ids[$label] = $this->service_PhenoCombo->getExperimentPhenoCombo($label);
    }
  }

  /**
   * Ensures that phenotype context has been set.
   *
   * @return void
   */
  protected function ensureDataTypeContextIsSet(): void {

    if ($this->phenotype_context === NULL || $this->phenotype_context === 0) {
      throw new \Exception(
        sprintf(
          'Phenotype context not set error in %s: phenotype context must be set before performing operations.
           Use setPhenotypeContext(raw|analyzed) to set a phenotype context.',
          get_class($this)
        )
      );
    }
  }

  /**
   * Ensures that phenotypic trais has been resolved.
   *
   * @return void
   */
  protected function ensureResolvedPhenotypicTraits(): void {

    if (empty($phenotypic_trait_ids)) {
      throw new \Exception(
        sprintf(
          'Unresolved phenotypic trait id error: Phenotypic traits.',
          get_class($this)
        )
      );
    }
  }

}
