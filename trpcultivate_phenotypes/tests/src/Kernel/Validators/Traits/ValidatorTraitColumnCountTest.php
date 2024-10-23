<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorColumnCount;

 /**
  * Tests the headers validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitColumnCountTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * The validator instance to use for testing.
   *
   * @var ValidatorColumnCount
   */
  protected ValidatorColumnCount $instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_column_count';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Column Count Trait',
      'input_types' => ['header-row'],
    ];

    $instance = new ValidatorColumnCount(
      $configuration,
      $validator_id,
      $plugin_definition
    );

    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Column Count Metadata trait."
    );

    $this->instance = $instance;
  }

  /**
   * Data provider: provides test data validator expected column and strict comparison.
   *
   * @return array
   *   Each scenario/element is an array with the following values.
   *
   *   - A string, human-readable short description of the test scenario.
   *   - Number of expected colum input (first parameter to the setter method).
   *   - Strict condition flag input (second parameter to the setter method).
   *   - Expected values set:
   *    - number_of_columns: the number of columns to expect.
   *    - strict: strict comparison flag.
   *   - Expected exception message for both setter and getter:
   *    - setter: setter exception message.
   *    - getter: getter exception message.
   */
  public function provideExpectedColumnsForSetter() {
    return [
      // #0: A zero number of expected column.
      [
        'zero columns',
        0,
        FALSE,
        NULL,
        [
          'setter' => 'setExpectedColumns() in validator requires an integer value greater than zero.',
          'getter' => 'Cannot retrieve the number of expected columns as one has not been set by setExpectedColumns().'
        ]
      ],

      // #1: A valid number.
      [
        'valid number',
        10,
        TRUE,
        [
          'number_of_columns' => 10,
          'strict' => TRUE
        ],
        [
          'setter' => '',
          'getter' => ''
        ]
      ],
    ];
  }

  /**
   * Test getter method to get expected columns before calling
   * the setter method first.
   */
  public function testGetExpectedColumns() {

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      $this->instance->getExpectedColumns();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'getExpectedColumns() method should throw an exception when trying to get expected number of columns before setting them.');
    $this->assertEquals(
      $exception_message,
      'Cannot retrieve the number of expected columns as one has not been set by setExpectedColumns().',
      'Exception message does not match the expected message when trying to get expected number of columns before setting them.'
    );
  }

  /**
   * Test getter method to get expected columns.
   *
   * @dataProvider provideExpectedColumnsForSetter
   */
  public function testValidatorSetterAndGetter($scenario, $column_numbers_input, $strict_input, $expected, $exception) {

    // Test the setter method.
    $exception_caught = FALSE;
    $exception_message = '';

    try {
      $this->instance->setExpectedColumns($column_numbers_input, $strict_input);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertEquals(
      $exception_message,
      $exception['setter'],
      'Exception message does not match the expected message when trying to call setExpectedColumns() in scenario ' . $scenario
    );

    // Test getter method.
    $exception_caught = FALSE;
    $exception_message = '';
    $validator_config = NULL;

    try {
      $validator_config = $this->instance->getExpectedColumns();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertEquals(
      $exception_message,
      $exception['getter'],
      'Exception message does not match the expected message when trying to call getExpectedColumns() in scenario ' . $scenario
    );

    $this->assertEquals(
      $validator_config,
      $expected,
      'The values set do not match the expected values in scenario ' . $scenario
    );
  }
}
