<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorColumnIndices;

 /**
  * Tests the ColumnIndices validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitColumnIndicesTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

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
   * The validator instance to use for testing.
   *
   * @var ValidatorColumnIndices
   */
  protected ValidatorColumnIndices $instance;

  /**
   * An array of indices which are valid
   *
   * @var array
   */
  protected array $valid_indices;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Setup our valid indices array
    $valid_indices = [
      [ 1, 2, 3 ],
      ['Trait', 'Method', 'Unit']
    ];
    $this->valid_indices = $valid_indices;

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_column_indices';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using ColumnIndices Trait',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorColumnIndices(
      $configuration,
      $validator_id,
      $plugin_definition
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the ColumnIndices trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the ColumnIndices::setIndices() setter
   *   and the ColumnIndices::getIndices() getter
   *
   * @return void
   */
  public function testColumnIndicesSetterGetter() {

    // Try to get indices before any have been set
    // Exception message should trigger
    $expected_message = 'Cannot retrieve an array of indices as one has not been set by the setIndices() method.';

    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getIndices();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Calling getIndices() when no indices have been set should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'The exception thrown does not have the message we expected when trying to get indices but none have been set yet.'
    );

    // Set indices and then check that they've been set
    foreach($this->valid_indices as $indices) {
      $exception_caught = FALSE;
      $exception_message = 'NONE';
      try {
        $this->instance->setIndices($indices);
      }
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
      $this->assertFalse(
        $exception_caught,
        "Calling setIndices() with a valid set of indices should not have thrown an exception but it threw '$exception_message'"
      );

      // Check that we can get the indices we just set
      $grabbed_indices = $this->instance->getIndices();
      $this->assertEquals(
        $indices,
        $grabbed_indices,
        'Could not grab the set of valid indices using getIndices() despite having called setIndices() on it.'
      );
    }
  }
}
