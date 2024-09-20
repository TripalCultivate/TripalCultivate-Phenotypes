<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Tests the Empty Cell validator
 *
 * @group trpcultivate_phenotypes
 * @group validators
 * @group row_validators
 */
class ValidatorEmptyCellTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration
   *
   * @var config_entity
   */
  private $config;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'file',
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test Empty Cell Plugin Validator.
   */
  public function testValidatorEmptyCell() {

    // Create a plugin instance for this validator
    $validator_id = 'empty_cell';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Simulates a row within the Trait Importer
    // Includes 3 types of empty cells
    $file_row = [
      'My trait',
      '',  // No whitespace
      'My method',
      ' ', // Single whitespace
      'My unit',
      '  ' // Double whitespace
    ];

    // Case #1: Provide a list of indices for cells that are not empty
    $expected_valid = TRUE;
    $expected_case = 'No empty values found in required column(s)';
    $indices = [ 0, 2, 4 ];
    $expected_failedItems = [];
    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Empty cell validation was expected to pass when provided only non-empty cells to check."
    );
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Empty cell validation case message did not match the expected when provided only non-empty cells to check."
    );
    $this->assertEquals(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Empty cell validation failed items should have been empty since validation passed."
    );

    // Case #2: Provide a list of indices that includes only empty cells
    $expected_valid = FALSE;
    $expected_case = 'Empty value found in required column(s)';
    $indices = [ 1, 3, 5 ];
    $expected_failedItems = ['empty_indices' => $indices];
    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Empty cell validation was expected to fail when provided a list of 3 empty cells to check."
    );
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Empty cell validation case message did not match the expected when provided a list of 3 empty cells to check."
    );
    $this->assertEquals(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Empty cell validation failed items did not contain the correct list of indices with empty cells."
    );

    // Case #3: Provide a list of indices for the entire row (mixture of 3 empty and 3 non-empty cells)
    $expected_valid = FALSE;
    $expected_case = 'Empty value found in required column(s)';
    $indices = [ 0, 1, 2, 3, 4, 5 ];
    $expected_failedItems = ['empty_indices' => [ 1, 3, 5 ]];
    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
       "Empty cell validation was expected to fail when provided a mixture of 3 empty and 3 non-empty cells to check."
    );
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Empty cell validation case message did not match the expected when provided a mixture of 3 empty and 3 non-empty cells to check."
    );
    $this->assertEquals(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Empty cell validation failed items list did not contain the correct list of indices with empty cells."
    );
  }
}
