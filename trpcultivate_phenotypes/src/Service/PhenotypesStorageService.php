<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;

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
  ) {

  }

  /**
   * Set the phenotype context.
   *
   * This context is used to restrict all operations so that only
   * records belonging to the specified phenotype context can be accessed,
   * retrieved or modified.
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

    $this->phenotype_context = $phenotype_context;
  }

  /**
   * Insert phenotype.
   *
   * @param array $phenotypes
   *   An array containing data to be inserted.
   */
  public function insertPhenotype(array $phenotypes): void {

    $this->ensureDataTypeContextIsSet();

    if (empty($phenotypes)) {
      throw new \Exception('Unable to insert phenotypes. The phenotypes data is empty.');
    }

    // Perform basic validation check of columns not empty.
    $sanitized_passport_data = $this->sanitizePassportData($phenotypes);
    $sanitized_measurement_data = $this->sanitizeMeasurements($phenotypes);

    // Insert phenotype.
    $phenotype_id = $this->insertPassportData($sanitized_passport_data);
    $this->insertMeasurements($phenotype_id, $sanitized_measurement_data);
  }

  /**
   * Validate and sanitize phenotype passport data.
   *
   * Columns:
   *  - Name
   *  - Entry
   *  - Block
   *  - Sample Type
   *  - Replicate
   *  - Timepoint
   *  - Treatment
   *
   * @return array
   */
  protected function sanitizePassportData(): array {

  }

  /**
   * Validate and sanitize phenotype measurement data.
   *
   * Columns:
   *  - PhenoCombo Label
   *  - Value
   *  - Project ID
   *  - Stock ID
   *
   * @return array
   */
  protected function sanitizeMeasurement(): array {

  }

  /**
   * Insert phenotype passport data.
   *
   * @return int
   *   The phenotype_id assigned to the last inserted row in phenotype table.
   */
  protected function insertPassportData(): int {

    return $phenotype_id;
  }

  /**
   * Insert phenotype measurement data.
   *
   * @return void
   */
  protected function insertMeasurement(): void {

  }

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

}
