<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Plugins that apply to a single row
  * of the input file - the "FILE ROW" scope
  *
  * @group trpcultivate_phenotypes
  * @group validators
  * @group file_row_scope
  */
class ValidatorFileRowScopeTest extends ChadoTestKernelBase {
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
   * Test Value In List Plugin Validator.
   */
  public function testValidatorValueInList() {

    // Create a plugin instance for this validator
    $validator_id = 'value_in_list';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Simulates a row within the Trait Importer
    $file_row = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit',
      'Quantitative'
    ];

    // Case #1: Check for a valid value in a single column
    $expected_status = 'pass';
    $context['indices'] = [ 5 ];
    $context['valid_values'] = [ 'Qualitative', 'Quantitative' ];
    $instance->context = $context;
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to pass when provided a cell with a valid value in the list.");
    $this->assertStringContainsString('Value at index 5 was one of:', $validation_status['details'], "Value in list validation details did not report that index 5 contained a valid value.");

    // Case #2: Check for an invalid value in a single column
    $expected_status = 'fail';
    $context['indices'] = [ 2 ];
    $context['valid_values'] = [ 'Qualitative', 'Quantitative' ];
    $instance->context = $context;
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to fail when provided a cell with a value not in the provided list.");
    $this->assertStringEndsWith(': 2', $validation_status['details'], "Value in list validation details did not contain the index of the cell with an invalid value.");

    // Case #3: Check for a valid value in multiple columns
    $expected_status = 'pass';
    $context['indices'] = [ 0, 2, 4 ];
    $context['valid_values'] = [ 'My trait', 'My method', 'My unit' ];
    $instance->context = $context;
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to pass when provided a cell with a valid value in the list.");
    $this->assertStringContainsString('Value at index 0, 2, 4 was one of:', $validation_status['details'], "Value in list validation details did not report that indices 0, 2, 4 all contained a valid value.");

    // Case #4: Check for 1 column with a valid value, 1 with invalid value
    $expected_status = 'fail';
    $context['indices'] = [ 0, 3 ];
    $context['valid_values'] = [ 'My trait description', 'My method description' ];
    $instance->context = $context;
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to fail when provided 2 cells; 1 with with valid input and 1 invalid.");
    $this->assertStringEndsWith(': 0', $validation_status['details'], "Value in list validation details did not contain the index of the cell with an invalid value.");

    // Case #5: Check for 1 column that has the wrong case compared to the valid values
    $expected_status = 'fail';
    $context['indices'] = [ 1 ];
    $context['valid_values'] = [ 'My Trait Description' ];
    $instance->context = $context;
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to fail when provided 1 cell with the same text but wrong case as the one valid value provided.");
    $this->assertStringEndsWith('with >=1 case insensitive match', $validation_status['title'], "Value in list validation title did not specify that a case insensitive match was found.");
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
    $expected_status = 'pass';
    $indices = [ 0, 2, 4 ];
    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to pass when provided only non-empty cells to check.");

    // Case #2: Provide a list of indices that includes only empty cells
    $expected_status = 'fail';
    $indices = [ 1, 3, 5 ];
    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to fail when provided 3 empty cells to check.");
    $this->assertStringEndsWith(': 1, 3, 5', $validation_status['details'], "Empty cell validation details did not contain the index of the empty cell.");

    // Case #3: Provide a list of indices for the entire row (mixture of 3 empty and 3 non-empty cells)
    $expected_status = 'fail';
    $indices = [ 0, 1, 2, 3, 4, 5 ];
    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($file_row);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to fail when provided a mixture of 3 empty and 3 non-empty cells to check.");
    $this->assertStringEndsWith(': 1, 3, 5', $validation_status['details'], "Empty cell validation details did not contain the indices of the empty cells.");
  }
}
