<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

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
   * Phenotypes data type context.
   *
   * Operations on phenotypes will be restricted to data type context.
   *
   * @var string|null
   */
  protected string|null $phenotype_context = NULL;

  /**
   * Phenotypes metadata context.
   *
   * An associative array containing metadata used to identify and associate
   * the phenotypic data with its corresponding experiment. The following keys
   * are expected:
   * - project_id: the project_id of the project (experiment) associated with
   *   the phenotypic data.
   * - genus: the specific genus corresponding to the project_id.
   *
   * @var array
   */
  protected array $metadata_context = [];

  /**
   * Passport data configuration name to configuration value (id) mapping.
   *
   * @var array
   */
  public readonly array $resolved_labels;

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
   */
  public const PASSPORT_DATA_CONFIG_NAMES = [
    0 => 'germplasm_name',
    1 => 'sample_entry',
    2 => 'experiment_group',
    3 => 'experiment_container',
    4 => 'experiment_replicate',
    5 => 'experiment_timepoint',
    6 => 'experiment_treatment'
  ];

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
    protected TripalCultivatePhenotypesGenusProjectService $service_GenusProject,
    protected TripalCultivatePhenotypesTermsService $service_Terms,
  ) {

  }

  /**
   * Set the phenotype context and associated database connection.
   *
   * This context is used to restrict all operations so that only
   * records belonging to the specified phenotype and metadata contexts can be
   * accessed, retrieved or modified. In addition, it determines which database
   * housing relevant phenotype relations to use.
   *
   * Phenotype Context - database connection:
   * - raw: public (Drupal database connection).
   * - analyzed: Chado database connection.
   *
   * @param $phenotype_context
   *   Either raw or analyzed, unpublished or published phenotype, respectively.
   * @param int $project_id
   *   The project_id of the project (experiment) source of phenotypic data.
   * @param string $genus
   *   The specific genus corresponding to the project_id.
   *
   * @throws \Exception
   *   - If Phenotype context key is not 'raw' nor 'analyzed'.
   *   - If corresponding module of a data type is not installed/enabled module.
   */
  public function setPhenotypeContext(string $phenotype_context, int|string $experiment, string $genus): void {

    $data_types = array_keys(self::DATA_TYPE_MODULE_MAP);
    if (!in_array($phenotype_context, $data_types)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Unsupported phenotype context: %s is not a supported context key. Use [%s].',
          $phenotype_context, implode(', ', $data_types)
        )
      );
    }

    $module_name = self::DATA_TYPE_MODULE_MAP[$phenotype_context];
    if (!$this->module_handler->moduleExists($module_name)) {
      throw new \Exception(
        sprintf(
          'Unable to configure phenotype context: %s is not installed/enabled module.
           Please install this module and try again.',
          $module_name
        )
      );
    }

    $project_id = ExperimentPhenoComboService::resolveExperimentToProjectId($experiment);
    $project_genus = $this->service_GenusProject->getGenusOfProject($project_id);

    if (empty($project_genus) || !in_array($genus, $project_genus)) {
      throw new \InvalidArgumentException(
        'Invalid phenotype metadata context: Project and genus metadata are not configured for phenotypes.'
      );
    }

    $this->phenotype_context = $phenotype_context;
    $this->metadata_context = ['project_id' => $project_id, 'genus' => $genus];

    // Resolve the database connection for the selected context.
    $this->resolveDatabaseConnection();
  }

  /**
   * Insert a single row of phenotypic data into the database.
   *
   * Data row contains values corresponding to the column headers (label)
   * previously resolved through resolveHeaderRowLabels().
   *
   * This method MUST CALL resolveHeaderRowLabels() to resolve header/label into
   * database id reference number.
   *
   * @param array $data_row
   *   An indexed array of phenotypic/measurement values representing a single
   *   row in the data file, excluding the first row (index 0, column headers).
   */
  public function insertPhenotypeRow(array $data_row): void {

    if (empty($data_row)) {
      return;
    }

    $this->ensurePhenotypeContextIsSet();
    $this->ensureResolvedPhenoComboLabels();

    $labels = array_keys($this->resolved_labels);

    foreach ($data_row as $index => $value) {
      // The first seven items in the data row pertain to passport data.
      if ($index <= count(self::PASSPORT_DATA_CONFIG_NAMES)) {
        $this->metadata_context[$labels[$index]] = $this->valueResolver($labels[$index], $value);

        continue;
      }

      // Create Phenotype.
      // The resolved trait-method-unit of the label.
      $measurement = $this->resolved_labels[$index];
      $phenotype_id = $this->insertMeasurementData($measurement, $value);

      unset($this->metadata_context[$labels[$index]]);

      // Create Phenotype Propperty.
      $this->insertPassportData($phenotype_id, $data_row);
    }
  }

  /**
   * Insert measurement data.
   *
   * @param string $measurement
   * @param int|string $value
   *
   * @return int
   *   The phenotype_id generated from the the insert measurement query.
   */
  protected function insertMeasurementData(string $measurement, int|string $value): int {

    $insert_measurement = $this->database_connection->insert(self::PHENOTYPES_TABLE)
      ->fields([
        'attr_id' => $measurement['attr_id'],
        'assay_id' => $measurement['assay_id'],
        'unit_id' => $measurement['unit_id'],
        'value' => $value,
        'stock_id' => $this->metadata_context['germplasm_name'],
        'project_id' => $this->metadata_context['project_id'],
        'uniquename' => $this->service_uuid->generate(),
      ])
      ->exeecute();

    return $insert_measurement;
  }

  /**
   * Insert passport data.
   *
   * @param int $phenotype_id
   * @param array $data_row
   */
  protected function insertPassportData(int $phenotype_id, array $data_row): void {

    // Ensure there is phenotype_id when calling this method.
    if ($phenotype_id < 1) {
      throw new \InvalidArgumentException(
        'Phenotype id is required'
      );
    }

    // The first seven items are the passport data.
    $passport_data = array_slice($data_row, 0, count(self::PASSPORT_DATA_CONFIG_NAMES) - 1);

    $insert_passportdata = $this->database_connection->insert(self::PHENOTYPESPROP_TABLE)
      ->fields([
        'phenotype_id',
        'type_id',
        'value'
      ]);

    $label_values = array_values($this->resolved_labels);

    foreach ($passport_data as $i => $data) {
      $insert_passportdata->values([
        $phenotype_id,
        $label_values[$i]['attr_id'],
        $data,
      ]);
    }

    $insert_passportdata->execute();
  }

  /**
   * Resolve which database connection to reference for a phenotype context.
   *
   * Phenotype Context - database connection:
   * - raw: public (Drupal database connection).
   * - analyzed: Chado database connection.
   */
  private function resolveDatabaseConnection(): void {

    switch ($this->phenotype_context) {

      case 'raw':
        $this->database_connection = $this->drupaldb_connection;
        break;

      case 'analyzed':
        $this->database_connection = $this->chado_connection;
        break;
    }
  }

  /**
   * Resolve PhenoCombo labels.
   *
   * @return array
   */
  public function resolveHeaderRowLabels(array $header_row): void {

    $this->ensurePhenotypeContextIsSet();

    $this->service_PhenoCombo->setExperiment($this->metadata_context['project_id']);
    // $this->service_PhenoCombo->setGenus($this->metadata_context['genus']);

    $resolved_labels = [];
    $passportdata_count = count(self::PASSPORT_DATA_CONFIG_NAMES);
    $phenocombo_keys = array_values($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP);

    foreach ($header_row as $index => $label) {
      // Passport data column labels.
      if ($index < $passportdata_count) {
        $config_name = self::PASSPORT_DATA_CONFIG_NAMES[$index];

        $resolved_labels[$config_name] = array_combine(
          $phenocombo_keys,
          [$this->service_Terms->getTermId($config_name), '', '']
        );

        continue;
      }

      // PhenoCombo labels.
      $label = trim($label);
      $pheno_combo = $this->service_PhenoCombo->getExperimentPhenoCombo($label);

      if ($pheno_combo) {
        $resolved_labels[$label] = array_combine(
          $phenocombo_keys,
          [$pheno_combo->attr_id, $pheno_combo->assay_id, $pheno_combo->unit_id]
        );
      }
    }

    $this->resolved_labels = $resolved_labels;
  }

  /**
   * Ensures that phenotype context has been set.
   */
  protected function ensurePhenotypeContextIsSet(): void {

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
   * Ensures that PhenoCombo labels have been resolved to individual
   * trait-method-unit cvterm id numbers.
   */
  protected function ensureResolvedHeaderRowLabels(): void {

    if (empty($this->pheno_combo_label_ids)) {
      throw new \Exception(
        'Unresolved pheno combo labels. Run resolvePhenoComboLabels(Header Row)'
      );
    }
  }

  /**
   * Resolve value in passport data columns.
   *
   * @param string $config_name
   * @param int|string $value
   *
   * @return int|string
   */
  protected function valueResolver(string $config_name, int|string $value): int|string {

    $resolved_value = $value;

    switch ($config_name) {

      case 'germplasm_name':

        $resolved_value = $this->chado_connection->select('1:stock', 'tbl')
          ->fields('tbl', ['stock_id'])
          ->condition('tbl.name', $value, '=')
          ->execute()
          ->fetchField();

        break;
    }

    return $resolved_value;
  }

}
