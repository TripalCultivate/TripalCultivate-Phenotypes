<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating the Trait Importer
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Plugins that are specific to
  * the Trait Importer (ie. they are not also used by other importers)
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class ValidatorTraitImporterTest extends ChadoTestKernelBase {
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
   * Test Duplicate Traits Plugin Validator.
   */
  public function testDuplicateTraitsPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_duplicate_traits';
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

    // Default case: Enter a single row of data
    $expected_status = 'pass';
    $context['indices'] = [ 'trait' => 0, 'method' => 2, 'unit' => 4 ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Duplicate Trait validation was expected to pass when provided the first row of values to validate.");
    $unique_traits = $instance->getUniqueTraits();
    $this->assertArrayHasUniqueCombo('my trait', 'my method', 'my unit', $unique_traits, 'Failed to find expected key within the global $unique_traits array for combo #1.');

    // Case #1: Re-renter the same details of the default case, should fail since it's a duplicate of the previous row
    $expected_status = 'fail';
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Validation was expected to fail when passed in a duplicate trait name + method + unit combination.");

    // Case #2: Provide an incorrect key to $context['indices']
    $context['indices'] = [ 'trait' => 0, 'method name' => 2, 'unit' => 3 ];
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row, $context);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in the wrong index key "method name" to $context[\'indices\'].');
    $this->assertStringContainsString('The method name (key: method) was not set', $e->getMessage(), "Did not get the expected exception message when providing the wrong index key \"method name\".");

    // Case #3: Enter a second unique row and check our global $unique_traits array
    // Note: unit is at a different index
    $file_row_2 = [
      'My trait 2',
      'My trait description',
      'My method 2',
      'My method description',
      'Qualitative',
      'My unit 2'
    ];

    $expected_status = 'pass';
    $context['indices'] = [ 'trait' => 0, 'method' => 2, 'unit' => 5 ];
    $validation_status = $instance->validateRow($file_row_2, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Validation was expected to pass for row #2 which contains a unique trait name + method + unit combination.");
    $unique_traits = $instance->getUniqueTraits();
    $this->assertArrayHasUniqueCombo('my trait 2', 'my method 2', 'my unit 2', $unique_traits, 'Failed to find expected key within the global $unique_traits array for combo #2.');

    // Case #4: Enter a third row that has same trait name and method name as row #1, and same unit as row #2.
    // Technically this combo is considered unique and should pass
    $file_row_3 = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit 2',
      'Qualitative'
    ];

    $expected_status = 'pass';
    $context['indices'] = [ 'trait' => 0, 'method' => 2, 'unit' => 4 ];
    $validation_status = $instance->validateRow($file_row_3, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Validation was expected to pass for row #3 which contains a unique trait name + method + unit combination.");
    $unique_traits = $instance->getUniqueTraits();
    $this->assertArrayHasUniqueCombo('my trait', 'my method', 'my unit 2', $unique_traits, 'Failed to find expected key within the global $unique_traits array for combo #3.');

  }

  /*
  *  Custom assert function to traverse the $unique_traits global array and
  *  check for expected keys
  */
  public function assertArrayHasUniqueCombo(string $trait, string $method, string $unit, array $unique_traits, string $message) {

    // Check all 3 keys at their appropriate array depths
    $this->assertArrayHasKey($trait, $unique_traits, "Missing key: '" . $trait . "'. " . $message);
    $this->assertArrayHasKey($method, $unique_traits[$trait], "Missing key: '" . $method . "'. " . $message);
    $this->assertArrayHasKey($unit, $unique_traits[$trait][$method], "Missing key: '" . $unit . "'. " . $message);
  }
}
