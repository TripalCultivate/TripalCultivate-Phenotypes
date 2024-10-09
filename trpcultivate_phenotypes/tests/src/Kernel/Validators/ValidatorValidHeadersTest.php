<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating headers.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

 /**
  * Tests Tripal Cultivate Phenotypes Headers Validator Plugin.
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class ValidatorValidHeadersTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * An instance of the data file validator.
   *
   * @var object
   */
  protected $validator_instance;
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

    // Create a plugin instance for this validator
    $validator_id = 'valid_headers';
    $this->validator_instance = \Drupal::service('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Set the importer headers.
    $this->validator_instance->setHeaders([
      [
        'name' => 'Header 0',
        'type' => 'required'
      ],
      [
        'name' => 'Header 1',
        'type' => 'required'
      ],
      [
        'name' => 'Header 2',
        'type' => 'required'
      ],
      [
        'name' => 'Header 3',
        'type' => 'optional'
      ],
      [
        'name' => 'Header 4',
        'type' => 'optional'
      ],
      [
        'name' => 'Header 5',
        'type' => 'required'
      ]
    ]);
  }

  /**
   * Data provider: provides test headers input.
   *
   * @return array
   *   Each scenario/element is an array with the following values.
   *
   *   - A string, human-readable short description of the test scenario.
   *   - Headers input array.
   *   - Expected validation result.
   *   - An array containing the expected number of columns and strict comparison flag.
   */
  public function provideHeadersToHeadersValidator() {

    return [
      // #0: The headers input is an empty array.
      [
        'empty headers',
        [],
        [
          'case' => 'Header row is an empty value',
          'valid' => FALSE,
          'failedItems' => ['headers' => 'headers array is an empty array']
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #1: Missing a header.
      [
        'missing header by an altered name',
        [
          'Header 0',
          'Header 1',
          'Header 2',
          'Header 3',
          'Header 4',
          'Header !5',
        ],
        [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'Header 0',
            'Header 1',
            'Header 2',
            'Header 3',
            'Header 4',
            'Header !5',
          ]
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #2: Missing a few headers, but validation will fail on first encounter of missing header.
      [
        'missing header by few altered names',
        [
          'Header 0',
          'Header 1',
          'Header !2',
          'Header !3',
          'Header !4',
          'Header !5',
        ],
        [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'Header 0',
            'Header 1',
            'Header !2',
            'Header !3',
            'Header !4',
            'Header !5',
          ]
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #3: Physically missing.
      [
        'missing header by omission',
        [
          'Header 0',
          'Header 1',
          'Header 2',
          'Header 4',
          'Header 5',
        ],
        [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'Header 0',
            'Header 1',
            'Header 2',
            'Header 4',
            'Header 5',
          ]
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #4: A couple of headers not in order.
      [
        'couple headers not in order',
        [
          'Header 0',
          'Header 1',
          'Header 3',
          'Header 2',
          'Header 4',
          'Header 5',
        ],
        [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'Header 0',
            'Header 1',
            'Header 3',
            'Header 2',
            'Header 4',
            'Header 5',
          ]
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #5: All headers not in order and validator will fail in the first ecounter missing/wrong order.
      [
        'multiple not in the order',
        [
          'Header 5',
          'Header 4',
          'Header 3',
          'Header 2',
          'Header 1',
          'Header 0'
        ],
        [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'Header 5',
            'Header 4',
            'Header 3',
            'Header 2',
            'Header 1',
            'Header 0',
          ]
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #6: A valid header but the expected column strict comparison flag is set to exact match (TRUE).
      [
        'not have expected number',
        [
          'Header 0',
          'Header 1',
          'Header 2',
          'Header 3',
          'Header 4',
          'Header 5'
        ],
        [
          'case' => 'Headers provided does not have the expected number of headers',
          'valid' => FALSE,
          'failedItems' => [
            'Header 0',
            'Header 1',
            'Header 2',
            'Header 3',
            'Header 4',
            'Header 5',
          ]
        ],
        [
          'number_of_columns' => 50,
          'strict' => TRUE
        ]
      ],

      // #7: Missing header with string index values.
      [
        'string index invalid',
        [
          'zero' => 'Header 0',
          'one' => 'Header 1',
          'three' => 'Header 3',
          'four' => 'Header 4',
          'five' => 'Header 5'
        ],
        [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'zero' => 'Header 0',
            'one' => 'Header 1',
            'three' => 'Header 3',
            'four' => 'Header 4',
            'five' => 'Header 5'
          ]
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #8: A valid header with the header count is greater than the expected number and strict is false.
      [
        'extra headers',
        [
          'Header 0',
          'Header 1',
          'Header 2',
          'Header 3',
          'Header 4',
          'Header 5',
          'Header 6',
          'Header 7',
          'Header 8',
          'Header 9',
          'Header 10'
        ],
        [
          'case' => 'Headers exist and match expected headers',
          'valid' => TRUE,
          'failedItems' => []
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],

      // #9: A valid header with string index values.
      [
        'string index valid',
        [
          'zero' => 'Header 0',
          'one' => 'Header 1',
          'two' => 'Header 2',
          'three' => 'Header 3',
          'four' => 'Header 4',
          'five' => 'Header 5'
        ],
        [
          'case' => 'Headers exist and match expected headers',
          'valid' => TRUE,
          'failedItems' => []
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],


      // #10: A valid header (list and order).
      [
        'valid headers',
        [
          'Header 0',
          'Header 1',
          'Header 2',
          'Header 3',
          'Header 4',
          'Header 5'
        ],
        [
          'case' => 'Headers exist and match expected headers',
          'valid' => TRUE,
          'failedItems' => []
        ],
        [
          'number_of_columns' => 6,
          'strict' => FALSE
        ]
      ],
    ];
  }

  /**
   * Test headers validator.
   *
   * @dataProvider provideHeadersToHeadersValidator
   */
  public function testHeaders($scenario, $headers_input, $expected, $expected_columns) {

    $this->validator_instance->setExpectedColumns($expected_columns['number_of_columns'], $expected_columns['strict']);
    $validation_status = $this->validator_instance->validateRow($headers_input);

    foreach($validation_status as $key => $value) {
      $this->assertEquals($value, $expected[ $key ],
        'The validation status key: ' . $key . ' does not match the same key in the expected status of scenario: ' . $scenario);
    }

    // Check that the header input array is the same as the failed item in scenario where
    // header input array is provided.
    if ($headers_input) {
      // Reset the headers input if the headers provided is valid.
      $headers_input = ($validation_status['valid']) ? [] : $headers_input;

      // Check both arrays headers and failedItems are the same in terms of items and order.
      foreach($headers_input as $i => $header) {
        $this->assertEquals(
          $validation_status['failedItems'][ $i ],
          $header,
          'A header in the header input array is in the wrong order in the failed items array in scenario: ' . $scenario
        );
      }
    }
  }
}
