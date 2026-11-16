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
  public const CONTEXT_KEY_MAP = [
    'raw' => 'unpublished',
    'analyzed' => 'published',
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
   *   - If Phenotype context key is not 'raw' or 'analyzed'.
   */
  public function setPhenotypeContext(string $phenotype_context): void {

    if (!array_key_exists($phenotype_context, self::CONTEXT_KEY_MAP)) {
      throw new \Exception(
        sprintf(
          'Unable to set phenotype context: %s is not a supported context key. Use [%s].',
          $phenotype_context, implode(', ', self::CONTEXT_KEY_MAP)
        )
      );
    }

    // @todo: check bofore setting the context that the module is enabled.

    $this->phenotype_context = $phenotype_context;
  }

  /**
   * Insert phenotype measurement data.
   */
  public function insertPhenotypeMeasurementData(int $project_id, string $genus, int $stock_id, array $measurements): int {

    // Validate context.
    $genus = trim($genus);
    if ($project_id <= 0 && empty($genus) && $stock_id <= 0) {
      // Failed
    }

    $label = $measurements['label'] ?? '';
    if (empty($label)) {
      // Failed
    }

    $value = $measurements['value'];

    $phenotype = [
      'uniquename' => $this->service_uuid->generate(),
      'project_id' => $project_id,
      'stock_id' => $stock_id,
      'attr_id' => $trait['attr_id'],
      'assay_id' => $trait['assay_id'],
      'unit_id' => $trait['unit_id'],
      'value' => $value,
    ];

    $db_connection = $this->phenotype_context == 'raw' ? $this->drupaldb_connection : $this->chado_connection;


  }

}
