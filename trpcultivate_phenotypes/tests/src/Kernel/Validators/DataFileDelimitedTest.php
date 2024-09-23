<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating data file.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

 /**
  * Tests Tripal Cultivate Phenotypes Data File Delimited Validator Plugins.
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class DataFileDelimitedTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * An instance of the data file delimiter validator.
   * 
   * @var object
   */
  protected $validator_instance;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a plugin instance for this validator
    $validator_id = 'valid_delimited_file';
    $this->validator_instance = \Drupal::service('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Set the supported mime types for this test.
    $this->validator_instance->setSupportedMimeTypes([
      'tsv', // text/tab-separated-values
      'txt'  // text/plain
    ]);

    // Set the input file MIME type.
    $this->validator_instance->setFileMimeType('text/tab-separated-values');
  }

  /**
   * Data provider: provides test data file raw row.
   * 
   * @return array
   *   Each scenario/element is an array with the following values.
   *   
   *   - A string, human-readable short description of the test scenario.
   *   - A single line of string value.
   *   - Validator configuration:
   *    - number_of_columns: number of column headers to expect after splitting the line.
   *    - strict: indicates if columns numbers must be the exact number configured.
   *   - Expected validation response for using either parameters.
   *    - case: validation test case.
   *    - valid: true or false whether validation passed or failed.
   */
  public function provideRawRowToDelimitedFileValidator() {
    return [
      
      // # 0: Raw row line is an empty string.
      [
        'empty raw row',
        '',
        [
          'number_of_columns' => 1,
          'strict' => FALSE
        ],
        [
          'case' => 'Raw row is empty',
          'valid' => FALSE,
          'failedItems' => ['raw_row' => 'is an empty string value']

        ]
      ],

      // #1: None of the supported delimiter for the file type was used.
      [
        'no delimiter',
        'Data Value One - Data Value 2 - Data Value 3',
        [
          'number_of_columns' => 2,
          'strict' => FALSE
        ],
        [
          'case' => 'None of the delimiters supported by the file type was used',
          'valid' => FALSE,
          'failedItems' => ['raw_row' => 'Data Value One - Data Value 2 - Data Value 3']
        ]
      ],

      // #2: Not the expected number of column number (strict comparison).
      [
        'column number mismatch',
        "Data Value One\tData Value Two\tData Value Three",
        [
          'number_of_columns' => 4,
          'strict' => TRUE
        ],
        [
          'case' => 'Raw row is not delimited',
          'valid' => FALSE,
          'failedItems' => ['raw_row' => "Data Value One\tData Value Two\tData Value Three"]
        ]
      ],

      // #3: Not the expected number of column number (not strict comparison).
      [
        'column number failed minimum',
        "Data Value One\tData Value Two\tData Value Three",
        [
          'number_of_columns' => 4,
          'strict' => FALSE
        ],
        [
          'case' => 'Raw row is not delimited',
          'valid' => FALSE,
          'failedItems' => ['raw_row' => "Data Value One\tData Value Two\tData Value Three"]
        ]
      ],

      // #4: Line has 2 delimiters where one is used to delimit values and the other
      // is within the values.
      [
        'two delimiters used',
        "Data Value One\tData Value Two\tData Value Three\t\"Data\tValue, Four\"",
        [
          'number_of_columns' => 4,
          'strict' => FALSE
        ],
        [
          'case' => 'Data file raw row is delimited',
          'valid' => TRUE,
          'failedItems' => []
        ]
      ],

      // #5: Valid raw row and expecting exactly 4.
      [
        'valid raw row with exact columns',
        "Data Value One\tData Value Two\tData Value Three\tData Value Four",
        [
          'number_of_columns' => 4,
          'strict' => TRUE
        ],
        [
          'case' => 'Data file raw row is delimited',
          'valid' => TRUE,
          'failedItems' => []
        ]
      ],

      // #6: Valid raw row and expecting at least 3.
      [
        'valid raw row with minimum columns',
        "Data Value One\tData Value Two\tData Value Three\tData Value Four",
        [
          'number_of_columns' => 3,
          'strict' => FALSE
        ],
        [
          'case' => 'Data file raw row is delimited',
          'valid' => TRUE,
          'failedItems' => []
        ]
      ],

      // #7: Raw row just has one column.
      [
        'one column',
        "Data Value One",
        [
          'number_of_columns' => 1,
          'strict' => FALSE
        ],
        [
          'case' => 'Data file raw row is delimited',
          'valid' => TRUE,
          'failedItems' => []
        ]
      ],
    ];
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
   *     - number_of_columns: the number of columns to expect.
   *     - strict: strict comparison flag.
   *   - Expected exception message for both setter and getter:
   *    - setter: setter exception message.
   *    - getter: getter exception message.
   */
  public function provideValidatorConfigurationValues() {
    return [
      // #0: A zero number of expected column.
      [
        'zero columns',
        0,
        FALSE,
        NULL,
        [
          'setter' => 'The setter method in ValidDelimitedFile requires an integer value greater than zero.',
          'getter' => 'Cannot retrieve the values set by the ValidDelimitedFile setter method.'
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
      $this->validator_instance->getExpectedColumns();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'ValidDelimitedFile::getExpectedColumns() method should throw an exception when trying to get expected number of columns before setting them.');
    $this->assertEquals(
      $exception_message,
      'Cannot retrieve the values set by the ValidDelimitedFile setter method.',
      'Exception message does not match the expected message when trying to get expected number of columns before setting them.'
    );
  }

  /**
   * Test getter method to get expected columns.
   * 
   * @dataProvider provideValidatorConfigurationValues
   */
  public function testValidatorSetterAndGetter($scenario, $column_numbers_input, $strict_input, $expected, $exception) {
    
    // Test the setter method.
    $exception_caught = FALSE;
    $exception_message = '';

    try {
      $this->validator_instance->setExpectedColumns($column_numbers_input, $strict_input);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertEquals(
      $exception_message,
      $exception['setter'],
      'Exception message does not match the expected message when trying to call ValidDelimitedFile::setExpectedColumns() in scenario ' . $scenario
    );

    // Test getter method.
    $exception_caught = FALSE;
    $exception_message = '';
    $validator_config = NULL;

    try {
      $validator_config = $this->validator_instance->getExpectedColumns();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertEquals(
      $exception_message,
      $exception['getter'],
      'Exception message does not match the expected message when trying to get expected number of columns in scenario ' . $scenario
    );

    $this->assertEquals(
      $validator_config,
      $expected,
      'The values set do not match the expected values in scenario ' . $scenario
    );
  }

  /**
   * Test data file row is properly delimited.
   * 
   * @dataProvider provideRawRowToDelimitedFileValidator
   */
  public function testDataFileRowIsDelimited($scenario, $raw_row_input, $validator_config, $expected) {
    // Set validator configuration.
    $this->validator_instance->setExpectedColumns($validator_config['number_of_columns'], $validator_config['strict']);

    $validation_status = $this->validator_instance->validateRawRow($raw_row_input);
    foreach($validation_status as $key => $value) {
      $this->assertEquals($value, $expected[ $key ],
        'The validation status key ' . $key . ' does not match the same status key in scenario: ' . $scenario);
    } 
  }
}