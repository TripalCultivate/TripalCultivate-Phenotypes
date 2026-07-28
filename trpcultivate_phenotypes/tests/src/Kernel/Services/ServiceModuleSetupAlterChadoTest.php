<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the setup of the trpcultivate_phenotypes module.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceModuleSetupAlterChadoTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'path',
    'path_alias',
    'system',
    'user',
    'views',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The columns the service is expected to add to chado.
   *
   * @var array
   *   Each element is a column to add to chado with the following values:
   *    - table: the table the column should be added to.
   *    - column: the column to add.
   *    - schema: the Drupal schema array describing the column to create.
   *    - references: describes the foreign key this new column represents
   *      with table/column keys. If this column is not a foreign key then
   *      this should be FALSE.
   *
   * @see Drupal\trpcultivate_phenotypes\Service\SetupModuleService::chado_columns
   */
  public static $expected_chado_columns = [
    'phenotype.project_id' => [
      'table' => 'phenotype',
      'column' => 'project_id',
      'schema' => [
        'description' => '',
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
        'description' => '',
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
        'description' => '',
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
        'description' => '',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

  }

  /**
   * Call the method we want to test.
   */
  public function testAlterChadoTables() {
    // Call the method we want to test.
    \Drupal::service('trpcultivate_phenotypes.setup_module_service')
      ->alterChadoTables();

    // Confirm that all the columns the service should have added are there.
    foreach (self::$expected_chado_columns as $label => $spec) {
      $field_exists = $this->chado_connection->schema()->fieldExists($spec['table'], $spec['column']);
      $this->assertTrue($field_exists, "The $label column should exist after running the setup module.");

      $fkexists = $this->chado_connection->schema()->foreignKeyConstraintExists($spec['table'], $spec['column']);
      $this->assertTrue($fkexists, "The foreign key constraint for $label should exist after running the setup module.");
    }
  }

}
