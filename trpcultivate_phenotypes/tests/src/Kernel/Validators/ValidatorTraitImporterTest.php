<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating the Trait Importer
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Plugins that are specific to
  * the Trait Importer (ie. they are not also used by other importers)
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class ValidatorTraitImporterTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $connection;

  /**
   * Configuration
   *
   * @var config_entity
   */
  private $config;

  /**
   * Saves details regarding the config.
   */
  protected $cvdbon;

  /**
   * The terms required by this module mapped to the cvterm_ids they are set to.
   */
  protected $terms;

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
//    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test Duplicate Traits Plugin Validator at the file level
   * -- ONLY tests with the context of traits that are being imported and
   *    compared to other traits that exist within the same input file
   */
  public function testValidatorDuplicateTraitsFile() {

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

  /**
   * Test Duplicate Traits Plugin Validator at the database
   * -- ONLY tests with the context of traits that are being imported and
   *    compared to other traits that exist in the database
   */
  public function testValidatorDuplicateTraitsDatabase() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_duplicate_traits';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $genus = 'Tripalus';
    // Create our organism and configure it.
    $organism_id = $this->connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");
    $this->cvdbon = $this->setOntologyConfig($genus);
    $this->terms = $this->setTermConfig();

    // Grab our traits service
    $service_traits = \Drupal::service('trpcultivate_phenotypes.traits');
    $service_traits->setTraitGenus($genus);

    // Simulates a row in the input file for the Trait Importer
    // with the column headers as keys
    $file_row_with_keys = [
      'Trait Name' => 'My trait',
      'Trait Description' => 'My trait description',
      'Method Short Name' => 'My method',
      'Collection Method' => 'My method description',
      'Unit' => 'My unit',
      'Type' => 'Quantitative'
    ];

    // Create a simplified array without assigning the column headers as keys
    // for use with our validator directly
    $file_row = array_values($file_row_with_keys);

    // Default case: Validate a single row and check against an empty database
    $expected_status = 'pass';
    $context['indices'] = [ 'trait' => 0, 'method' => 2, 'unit' => 4 ];
    $validation_status = $instance->validateRow($file_row, $context);
    $this->assertEquals($expected_status, $validation_status['status'], "Duplicate Trait validation was expected to pass when provided the first row of values to validate and an empty database.");
    // Verify this trait isn't in the database
    $my_trait_id = $service_traits->getTrait(['name' => 'My trait']);
    $expected_trait_id = 0;
    $this->assertEquals($expected_trait_id, $my_trait_id, "Duplicate Trait validation did not fail, yet a trait ID was queried from the database for the same trait name.");

    // Case #1: Enter a trait into the database and then try to validate the
    // same trait details

    // Inserts our trait into the database, and returns the IDs for each of
    // trait, method, unit in an array
    // @TODO: Change this to use the test Trait ("Trait" in the testing sense,
    // not phenotypic) once it's been developed.
    $combo_ids = $service_traits->insertTrait($file_row_with_keys);


    //print_r($combo_ids);

    // Case #2: Validate trait details where trait name and method name already
    // exist in the database, but unit is unique
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
