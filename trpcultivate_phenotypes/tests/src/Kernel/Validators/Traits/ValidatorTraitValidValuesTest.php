<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorValidValues;

 /**
  * Tests the ValidValues validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitValidValuesTest extends ChadoTestKernelBase {

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
   * @var ValidatorValidValues
   */
  protected ValidatorValidValues $instance;

  /**
   * An array of values which are invalid
   *
   * @var array
   */
  protected array $invalid_values;

  /**
   * An array of values which are valid
   *
   * @var array
   */
  protected array $valid_values;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create a new object for the purpose of testing
    $my_array = ['key' => 'value'];
    $my_object = (object) $my_array;

    // Setup our invalid values array
    $invalid_values = [
      [ 1, 2, [ 3, 4, 5] ],
      [ 1, $my_object, 3 ],
      [ 0.5, -7.3, 6.6 ]
    ];
    $this->invalid_values = $invalid_values;

    // Setup our valid values array
    $valid_values = [
      [ 1, 2, 3 ],
      ['Trait', 'Method', 'Unit']
    ];
    $this->valid_values = $valid_values;

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_valid_values';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using ValidValues Trait',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorValidValues(
      $configuration,
      $validator_id,
      $plugin_definition
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the ValidValues trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the ValidValues::setValidValues() setter
   *   and the ValidValues::getValidValues() getter
   *
   * @return void
   */
  public function testValidValuesSetterGetter() {

    // Try to get valid values before any have been set
    // Exception message should trigger
    $expected_message = 'Cannot retrieve an array of valid values as one has not been set by the setValidValues() method.';

    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getValidValues();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Calling getValidValues() when no array has been set should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'The exception thrown does not have the message we expected when trying to get valid values but none have been set yet.'
    );

    // Try to set an empty array of valid values
    // Exception message should trigger
    $empty_values = [];
    $expected_message = 'The ValidValues Trait requires a non-empty array to set valid values.';

    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setValidValues($empty_values);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue($exception_caught, 'Calling setValidValues() with an empty array should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'The exception thrown does not have the message we expected when trying to set valid values with an empty array.'
    );

    // Try to set a multi-dimensional array (only 1-dimensional allowed)
    // Exception message should trigger
    foreach($this->invalid_values as $values) {
      $expected_message = 'The ValidValues Trait requires a one-dimensional array with values that are of type integer or string only.';

      $exception_caught = FALSE;
      $exception_message = 'NONE';
      try {
        $this->instance->setValidValues($values);
      }
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }

      $this->assertTrue($exception_caught, 'Calling setValidValues() with an array of values that are not of type integer or string should have thrown an exception but did not.');
      $this->assertStringContainsString(
        $expected_message,
        $exception_message,
        'The exception thrown does not have the message we expected when trying to set indices with an array that has a value which is not a string or integer.'
      );
    }

    // Set arrays of valid values and then check that they've been set
    foreach($this->valid_values as $values) {
      $exception_caught = FALSE;
      $exception_message = 'NONE';
      try {
        $this->instance->setValidValues($values);
      }
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
      $this->assertFalse(
        $exception_caught,
        "Calling setValidValues() with a valid array of values should not have thrown an exception but it threw '$exception_message'"
      );

      // Check that we can get the values we just set
      $grabbed_values = $this->instance->getValidValues();
      $this->assertEquals(
        $values,
        $grabbed_values,
        'Could not grab the set of valid values using getValidValues() despite having called setValidValues() on it.'
      );
    }
  }
}
