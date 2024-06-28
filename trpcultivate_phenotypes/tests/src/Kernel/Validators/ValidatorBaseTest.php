<?php

/**
 * @file
 * Kernel tests for the Validator Base class
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Base functions
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class ValidatorBaseTest extends ChadoTestKernelBase {
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
   * Test the checkIndices() function in the Validator Base class
   */
  public function testValidatorBaseCheckIndices() {

    // Create a plugin instance for any validator that uses this function
    $validator_id = 'trpcultivate_phenotypes_validator_value_in_list';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Simulates a row within the Trait Importer
    $file_row = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit',
      'Qualitative'
    ];

    // ERROR CASES

    // Provide an empty array of indices
    $context['indices'] = [];
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row, $context);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in an empty array of indices.');
    $this->assertStringContainsString('An empty indices array was provided.', $e->getMessage(), "Did not get the expected exception message when providing an empty array of indices.");

    // Provide too many indices
    $context['indices'] = [ 0, 1, 2, 3, 4, 5, 6, 7 ];
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row, $context);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in too many indices compared to number of cells in the row.');
    $this->assertStringContainsString('Too many indices were provided (8) compared to the number of cells in the provided row (6)', $e->getMessage(), "Did not get the expected exception message when providing 8 indices compared to 6 values.");

    // Provide invalid indices
    $context['indices'] = [ 1, -4, 77 ];
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row, $context);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in invalid indices.');
    $this->assertStringContainsString('One or more of the indices provided (-4, 77) is not valid when compared to the indices of the provided row', $e->getMessage(), "Did not get the expected exception message when providing 2 different invalid indices.");
  }

  /**
   * Test the basic getters: getValidatorName() and getConfigAllowNew().
   */
  public function testBasicValidatorGetters() {

    // Create a plugin instance for any validator.
    $validator_id = 'trpcultivate_phenotypes_validator_value_in_list';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Check that we can get the name of the validator we requested above.
    // NOTE: this is the validator_name in the annotation.
    $expected_name = 'Value In List Validator';
    $returned_name = $instance->getValidatorName();
    $this->assertEquals($expected_name, $returned_name,
      "We did not recieve the name we expected when using getValidatorName() for $validator_id validator.");

    // Check that we are able to get the configuration for allowing new traits.
    // NOTE: this is set by the admin in the ontology config form and doesn't
    // change between importers.
    $expected_allownew = TRUE;
    $returned_allownew = $instance->getConfigAllowNew();
    $this->assertEquals($expected_allownew, $returned_allownew,
      "We did not get the status for Allowing New configuration that we expected through the $validator_id validator.");
  }

  /**
   * Test the input type focused getters: getSupportedInputTypes()
   * + checkInputTypeSupported().
   */
  public function testInputTypeValidatorGetters() {

    // Create a plugin instance for any validator.
    $validator_id = 'trpcultivate_phenotypes_validator_value_in_list';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Check that we can get the supported inputTypes for this validator.
    // NOTE: use assertEqualsCanonicalizing so that order of arrays does NOT matter.
    $expected_inputTypes = ['data-row', 'header-row'];
    $returned_inputTypes = $instance->getSupportedInputTypes();
    $this->assertEqualsCanonicalizing($expected_inputTypes, $returned_inputTypes,
      "We did not get the expected input types for $validator_id validator when using getSupportedInputTypes().");

    // Check that we rightly get told the data-row is a supported input type.
    $dataRow_supported = $instance->checkInputTypeSupported('data-row');
    $this->assertTrue($dataRow_supported,
      "The data-row input type should be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this.");

    // Check that we rightly get told the data-row is a supported input type.
    $metadata_supported = $instance->checkInputTypeSupported('metadata');
    $this->assertFalse(
      $metadata_supported,
      "The metadata input type should NOT be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this."
    );

    // Check with an invalid inputType.
    $invalid_supported = $instance->checkInputTypeSupported('SARAH');
    $this->assertFalse(
      $invalid_supported,
      "The SARAH input type is invalid and thus should NOT be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this."
    );
  }

  /**
   * Test the validate methods: validateMetadata(), validateFile(), validateRow(), validate().
   *
   * NOTE: These should all thrown an exception in the base class.
   */
  public function testValidatorValidateMethods() {


    $this->markTestIncomplete();

  }
}
