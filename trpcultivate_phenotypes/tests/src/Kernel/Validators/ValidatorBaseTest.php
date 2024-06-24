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

    // Try with a nested array of indices
    //$context['indices'] = ['one' => 1, 'two' => 2, 'three' => 3];
    //$validation_status = $instance->validateRow($file_row, $context);

    // Error cases
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
}
