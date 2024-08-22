<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorHeaders;

 /**
  * Tests the headers validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitHeadersTest extends ChadoTestKernelBase {

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
   * @var ValidatorHeaders
   */
  protected ValidatorHeaders $instance;

  /**
   * Test headers. This test value is equivalent to setting
   * up the required headers expected by the importer where
   * each array element comprises of header name, description 
   * and type (ie. required, optional), keyed by name, description
   * type, respectively.
   * 
   * @var array
   */
  protected array $test_headers = [];


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
    $validator_id = 'validator_requiring_headers';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Headers Trait',
      'input_types' => ['header-row', 'data-row'],
    ];

    $instance = new ValidatorHeaders(
      $configuration,
      $validator_id,
      $plugin_definition
    );

    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Header Metadata trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the Headers setter and getters.
   * 
   * @return void
   */
  public function testHeaderSetterGetter() {
    // Test getter will trigger an error when attempting to get a headers
    // prior to a call to headers setter method.
    
    $expected_types = ['required', 'optional'];
    // Exception message when failed to set headers - all header types.
    $expected_message = 'Cannot retrieve%s headers from the context array as one has not been set by setHeaders() method.';

    foreach($expected_types as $type) {
      $getter = 'get' . ucfirst($type) . 'Headers';

      try {
        $this->instance->$getter();
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
       
      $this->assertTrue($exception_caught, 'Header type ' . $type . ' getter method should throw an exception for unset header.');
      $this->assertStringContainsString(
        sprintf($expected_message, ' ' . $type),
        $exception_message,
        'Expected exception message does not match the message when trying to get headers of type ' . $type . ' on unset headers.'
      );
    }
    
    // Header getter.
    try {
      // No specific type, the getter will get default types.
      $this->instance->getHeaders();
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Header getter method should throw an exception for unset header.');
    $this->assertStringContainsString(
      sprintf($expected_message, ''),
      $exception_message,
      'Expected exception message does not match the message when trying to get headers.'
    );
    
    
    // Test setter parameter and header key/value.
    
    // An empty headers array. 
    try {
      $this->instance->setHeaders([]);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Headers setter method should throw an exception if headers parameter is an empty array.');
    $this->assertStringContainsString(
      'The Headers Trait requires an array of headers and must not be empty.',
      $exception_message,
      'Expected exception message does not match the message when trying to set headers using an empty headers array.'
    );

    // Key and value test.
    $headers = [
      'undefined:name' => [
        [
          'not-name' => 'Not the name',
          'type' => 'required'
        ]                              // No name key defined.
      ],
      'undefined:type' => [
        [
          'name' => 'Header 1',
          'not-type' => 'Not the type'
        ]                              // No type key defined.
      ],
      'empty:name' => [
        [
          'name' => '',
          'type' => 'required'
        ]                             // Has name and type but name is empty value.
      ],
      'empty:type' => [
        [
          'name' => 'Header 1',
          'type' => ''
        ]                             // Has name and type but type is empty value.
      ],
      'invalid:type' => [
        [
          'name' => 'Header 1',
          'type' => 'spurious_type'
        ]                             // Has name and type with value but type is not valid header type.
      ]
    ];

    $failed_message = [
      'undefined' => 'Headers Trait requires the header key: %s when defining headers.',
      'empty' => 'Headers Trait requires the header key: %s to be have a value.',
      'invalid' => 'Headers Trait requires the header key: %s value to be one of'
    ];

    foreach($headers as $test_case => $header) {
      
      try {
        $this->instance->setHeaders($header);
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
      
      list($error_type, $error_key) = explode(':', $test_case);
      $expected_message = sprintf($failed_message[ $error_type ], $error_key);
      
      $this->assertTrue($exception_caught, 'Headers setter method should throw an exception if ' . $error_type . ' ' .  $error_key);
      $this->assertStringContainsString(
        $expected_message,
        $exception_message,
        'Expected exception message does not match the message if ' . $error_type . ' ' .  $error_key
      );
    }


    // Test setter and getters.

    // A mix bag of header types.
    $headers = [
      [
        'name' => 'Header 1',
        'type' => 'required'  // 0
      ],
      [
        'name' => 'Header 2',
        'type' => 'required'  // 1
      ],
      [
        'name' => 'Header 3',
        'type' => 'optional'  // 2
      ],
      [
        'name' => 'Header 4',
        'type' => 'optional'  // 3
      ],
      [
        'name' => 'Header 5',
        'type' => 'required'  // 4
      ]
    ];

    $this->instance->setHeaders($headers);

    // The setter will maintain the index (order) of the headers so that
    // the required header set is [0, 1, 4] and optional header set is [2, 3].
    $headers_by_type = [
      'required' => [
        0 => $headers[0]['name'],
        1 => $headers[1]['name'],
        4 => $headers[4]['name']
      ],
      'optional' => [
        2 => $headers[2]['name'],
        3 => $headers[3]['name']  
      ]
    ];

    foreach($expected_types as $type) {
      $getter = 'get' . ucfirst($type) . 'Headers';

      // Required headers.
      $required_set_headers = $this->instance->$getter();
      $this->assertEquals(
        $headers_by_type[ $type ],
        $required_set_headers, 
        'The set headers does not match the headers returned by ' . $type . ' headers getter method.'
      );

      $required_set_headers = $this->instance->getHeaders([ $type ]);
      $this->assertEquals(
        $headers_by_type[ $type ],
        $required_set_headers, 
        'The set headers does not match the headers returned by headers getter method (type: ' . $type . ').'
      );     
    }


    // Test header getter with invalid type request.
    try {
      // No specific type, the getter will get default types.
      $this->instance->getHeaders(['not_my_type', 'required']);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Header getter method should throw an exception if type requested is invalid.');
    $this->assertStringContainsString(
      'Cannot retrieve invalid header types: not_my_type',
      $exception_message,
      'Expected exception message does not match the message when trying to get headers.'
    );

    // Default should match required + optional.
    $all_headers = $this->instance->getHeaders();
    $this->assertEquals(
      $headers_by_type['required'] + $headers_by_type['optional'],
      $all_headers, 
      'The set headers does not match the headers returned by headers getter method (type: ' . $type . ').'
    );     
  }
}