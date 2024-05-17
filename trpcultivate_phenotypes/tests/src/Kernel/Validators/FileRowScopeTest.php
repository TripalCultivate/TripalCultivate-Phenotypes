<?php

/**
 * @file
 * Kernel tests for validator plugins that operate within the scope of "FILE ROW"
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Plugins that apply to a single row
  * of the input file - the "FILE ROW" scope
  *
  * @group trpcultivate_phenotypes
  * @group validators
  * @group file_row_scope
  */
class FileRowScopeTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $chado;

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
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test Value In List Plugin Validator.
   */
  public function testValueInListPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_value_in_list';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // A list of valid inputs
    $valid_inputs = ['Quantitative', 'Qualitative'];

    // A list of invalid inputs
    $invalid_inputs = ['Collective', 'Type', ' '];

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
    $context['valid_values'] = [ 'qualitative', 'quantitative' ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to pass when provided a cell with a valid value in the list.");

    // Case #2: Check for an invalid value in a single column
    $expected_status = 'fail';
    $context['indices'] = [ 2 ];
    $context['valid_values'] = [ 'qualitative', 'quantitative' ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Value in list validation was expected to fail when provided a cell with a value not in the provided list.");

    // // Case #3: Provide a list of indices that includes 1 cell that is empty
    // $expected_status = 'fail';
    // $context['indices'] = [ 0, 1, 4 ];
    // $validation_status = $instance->validateRow($file_row, $context);
    // $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to fail when provided a mixture of 1 empty and 2 non-empty cells to check.");
    // $this->assertStringEndsWith(': 1', $validation_status['details'], "Empty cell validation details did not contain the index of the empty cell.");

    // // Case #4: Provide a list of indices for the entire row (3 empty cells)
    // $expected_status = 'fail';
    // $context['indices'] = [ 0, 1, 2, 3, 4, 5 ];
    // $validation_status = $instance->validateRow($file_row, $context);
    // $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to fail when provided a mixture of 3 empty and 3 non-empty cells to check.");
    // $this->assertStringEndsWith(': 1, 3, 5', $validation_status['details'], "Empty cell validation details did not contain the indices of the empty cells.");

  }

  /**
   * Test Empty Cell Plugin Validator.
   */
  public function testEmptyCellPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_empty_cell';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Simulates a row within the Trait Importer
    $file_row = [
      'My trait',
      '',
      'My method',
      '',
      'My unit',
      ''
    ];

    // Case #1: Don't provide a list of indices to check if cells are empty
    $expected_status = 'pass';
    $context['indices'] = [];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to pass when provided no cells to check.");

    // Case #2: Provide a list of indices for cells that are not empty
    $expected_status = 'pass';
    $context['indices'] = [ 0, 2, 4 ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to pass when provided only non-empty cells to check.");

    // Case #3: Provide a list of indices that includes 1 cell that is empty
    $expected_status = 'fail';
    $context['indices'] = [ 0, 1, 4 ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to fail when provided a mixture of 1 empty and 2 non-empty cells to check.");
    $this->assertStringEndsWith(': 1', $validation_status['details'], "Empty cell validation details did not contain the index of the empty cell.");

    // Case #4: Provide a list of indices for the entire row (3 empty cells)
    $expected_status = 'fail';
    $context['indices'] = [ 0, 1, 2, 3, 4, 5 ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Empty cell validation was expected to fail when provided a mixture of 3 empty and 3 non-empty cells to check.");
    $this->assertStringEndsWith(': 1, 3, 5', $validation_status['details'], "Empty cell validation details did not contain the indices of the empty cells.");
  }

  /**
   * Test Duplicate Traits Plugin Validator.
   */
  public function testDuplicateTraitsPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_duplicate_traits';
    $instance = $this->plugin_manager->createInstance($validator_id);
  }
}
