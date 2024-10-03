<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Tests the Value In List validator
 *
 * @group trpcultivate_phenotypes
 * @group validators
 * @group row_validators
 */
class ValidatorValueInListTest extends ChadoTestKernelBase {
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
    $expected_valid = TRUE;
    $expected_case = 'Values in required column(s) are valid';
    $indices = [ 5 ];
    $valid_values = [ 'Qualitative', 'Quantitative' ];
    $expected_failedItems = [];
    $instance->setIndices($indices);
    $instance->setValidValues($valid_values);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Value in list validation was expected to pass when provided a cell with a valid value in the list."
    );
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Value in list validation case message did not report that values in the specified columns were valid."
    );
    $this->assertSame(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Value in list validation failed items was expected to be empty since validation passed."
    );

    // Case #2: Check for an invalid value in a single column
    $expected_valid = FALSE;
    $expected_case = 'Invalid value(s) in required column(s)';
    $indices = [ 2 ];
    $valid_values = [ 'Qualitative', 'Quantitative' ];
    $expected_failedItems = [ 2 => 'My method' ];
    $instance->setIndices($indices);
    $instance->setValidValues($valid_values);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Value in list validation was expected to fail when provided a cell with a value not in the provided list."
    );
    $this->assertStringEndsWith(
      $expected_case,
      $validation_status['case'],
      "Value in list validation case message did not report that the value in the specified column was invalid"
    );
    $this->assertSame(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Value in list validation failed items was expected to contain one item since validation failed."
    );

    // Case #3: Check for a valid value in multiple columns
    $expected_valid = TRUE;
    $expected_case = 'Values in required column(s) are valid';
    $indices = [ 0, 2, 4 ];
    $valid_values = [ 'My trait', 'My method', 'My unit' ];
    $expected_failedItems = [];
    $instance->setIndices($indices);
    $instance->setValidValues($valid_values);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Value in list validation was expected to pass when provided multiple cells all containing valid values.");
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Value in list validation case message did not report that all indices contained a valid value."
    );
    $this->assertSame(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Value in list validation failed items was expected to be empty since validation passed."
    );

    // Case #4: Check for 1 column with a valid value, 1 with invalid value
    $expected_valid = FALSE;
    $expected_case = 'Invalid value(s) in required column(s)';
    $indices = [ 0, 3 ];
    $valid_values = [ 'My trait description', 'My method description' ];
    $expected_failedItems = [ 0 => 'My trait' ];
    $instance->setIndices($indices);
    $instance->setValidValues($valid_values);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Value in list validation was expected to fail when provided 2 cells; 1 with with valid input and 1 invalid."
    );
    $this->assertStringEndsWith(
      $expected_case,
      $validation_status['case'],
      "Value in list validation case message did not report that there was a cell with an invalid value."
    );
    $this->assertSame(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Value in list validation failed items was expected to contain one item since validation failed."
    );


    // Case #5: Check for 1 column that has the wrong case compared to the valid values
    $expected_valid = FALSE;
    $expected_case = 'Invalid value(s) in required column(s) with >=1 case insensitive match';
    $indices = [ 1 ];
    $valid_values = [ 'My Trait Description' ];
    $instance->setIndices($indices);
    $instance->setValidValues($valid_values);
    $expected_failedItems = [ 1 => 'My trait description' ];
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Value in list validation was expected to fail when provided 1 cell with the same text but wrong case as the one valid value provided."
    );
    $this->assertStringEndsWith(
      $expected_case,
      $validation_status['case'],
      "Value in list validation case message did not specify that a case insensitive match was found."
    );
    $this->assertSame(
      $expected_failedItems,
      $validation_status['failedItems'],
      "Value in list validation failed items was expected to contain one item since validation failed."
    );
  }
}
