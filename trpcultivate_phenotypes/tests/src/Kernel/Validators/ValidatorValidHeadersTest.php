<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating headers.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\file\Entity\File;

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
        ]
      ],

      // #1: Missing some headers.
      [
        'missing header',
        [
          'Header 0',
          'Header 1',
          'Header 2',
          'Header 4',
        ],
        [
          'case' => 'Missing expected headers',
          'valid' => FALSE,
          'failedItems' => [
            'missing' => [
              'Header 5', 
              'Header 3'
            ]
          ]
        ]
      ],

      // #2: Header not in the right order.
      [
        'not in the order',
        [
          'Header 0',
          'Header 2',
          'Header 1',
          'Header 3',
          'Header 4',
          'Header 5'
        ],
        [
          'case' => 'Headers not in the correct order',
          'valid' => FALSE,
          'failedItems' => [
            'wrong_order' => [
              'Header 1', 
              'Header 2'
            ]
          ]
        ]
      ],

      // #3: A valid header (list and order).
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
        ]
      ],      
    ];
  }

  /**
   * Test headers validator.
   *
   * @dataProvider provideHeadersToHeadersValidator
   */
  public function testHeaders($scenario, $headers_input, $expected) {
    $validation_status = $this->validator_instance->validateRow($headers_input);

    foreach($validation_status as $key => $value) {
      $this->assertEquals($value, $expected[ $key ],
        'The validation status key: ' . $key . ' does not match the same key in the expected status of scenario: ' . $scenario);
    }
  }
}
