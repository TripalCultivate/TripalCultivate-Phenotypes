<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Core\Database\Connection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests module updates for trpcultivate_phenotypes.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceModuleUpdatesTest extends ChadoTestKernelBase {

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
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $drupaldb_connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Set database connections.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->drupaldb_connection = $this->container->get('database');

    // Load install file.
    \Drupal::moduleHandler()->loadInclude('trpcultivate_phenotypes', 'install');
  }

  /**
   * Test replicate Chado tables.
   *
   * Update: trpcultivate_phenotypes_update_10004()
   *
   * @see trpcultivate_phenotypes.install
   */
  public function testRawPhenotypesStorageHookUpdate() {

    // Invoke the update hook.
    trpcultivate_phenotypes_update_10004();

    $chado_schema = $this->chado_connection->schema();

    $base_table = 'phenotype';
    $prop_table = $base_table . 'prop';

    foreach ([$base_table, $prop_table] as $table) {
      // Chado version.
      $expected_columns = $this->chado_connection
        ->select('information_schema.columns', 'info')
        ->fields('info', ['column_name', 'data_type'])
        ->condition('info.table_name', $table, '=')
        ->execute()
        ->fetchAllKeyed();

      $this->assertNotEmpty(
        $expected_columns,
        'The Chado table ' . $table . ' does not contain any table columns.',
      );

      // Drupal version. Replicated in Drupal database schema.
      $this->assertTrue(
        $this->drupaldb_connection->schema()->tableExists($table, FALSE),
        'The Chado table ' . $table . ' is expected in Drupal DB',
      );

      $actual_columns = $this->drupaldb_connection
        ->select('information_schema.columns', 'info')
        ->fields('info', ['column_name', 'data_type'])
        ->condition('info.table_name', $table, '=')
        ->execute()
        ->fetchAllKeyed();

      $this->assertSame(
        $expected_columns,
        $actual_columns,
        'The Drupal table ' . $table . ' does not contain expected table columns.',
      );
    }
  }

}
