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
   * Data Provider: provides test headers.
   */
  public function provideHeadersForHeadersSetters() {
    return [
      'header element' => [
        'missing key' => [
          [
            'not-name' => 'Not the name',
            'type' => 'required'
          ],
          [
            'name' => 'Header',
            'not-type' => 'Not the type'
          ]
        ],
        'empty value' => [
          [
            'name' => '',
            'type' => 'required'
          ],
          [
            'name' => 'Header',
            'type' => ''
          ]
        ],
        'invalid type' => [
          [
            'name' => 'Header',
            'type' => 'spurious type'
          ]
        ]
      ],
      'header content' => [
        'empty array' => [],
        'all required' => [
          [
            'name' => 'Header 1',
            'type' => 'required'
          ],
          [
            'name' => 'Header 2',
            'type' => 'required',
          ],
          [
            'name' => 'Header 3',
            'type' => 'required'
          ]
        ],
        'mix types' => [
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
        ]
      ],
    ];
  }
  
  /**
   * Test the Headers setter and getters.
   * 
   * @dataProvider provideHeadersForHeadersSetters
   */
  public function testHeadersSetterGetterDraft($test, $case, $headers) {
    if ($test == 'header element') {
      try {
        $this->instance->setHeaders($headers);
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }

      $this->assertTrue($exception_caught, 'Headers setter method should throw an exception if ' . $case);
      $this->assertStringContainsString(
        'Headers Trait requires the header key',
        $exception_message,
        'Expected exception message does not match the message if ' . $case
      );
    }
  }



  ////////////////////////




  
  /**
   * Tests the Headers setter and getters.
   * 
   * @return void
   */
  public function testHeadersSetterGetter() {
    // Test getter will trigger an error when attempting to get a type(s) of headers
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
    
    
    // Test setter parameter and header key/value requirements.
    
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
        'Expected exception message does not match the message if ' . $error_type . ' ' . $error_key
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
        'The set headers does not match the headers returned by headers getter method (param type: ' . $type . ').'
      );     
    }


    // Test header getter with invalid type request.
    
    $with_bad_types = ['not_my_type', 'required', 'rare_type'];
    
    try {
      $this->instance->getHeaders($with_bad_types);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Header getter method should throw an exception if type requested is invalid.');
    $this->assertStringContainsString(
      'Cannot retrieve invalid header types: ' . $with_bad_types[0] . ', ' . $with_bad_types[2],
      $exception_message,
      'Expected exception message does not match the message when trying to get headers.'
    );

    // Default should match required + optional.
    $all_headers = $this->instance->getHeaders();
    $this->assertEquals(
      $headers_by_type['required'] + $headers_by_type['optional'],
      $all_headers, 
      'The set headers does not match the headers returned by headers getter method (param type: default).'
    );


    // Test that the order of header, index number is unaltered.

    // The resulting headers array will have optional headers preceding
    // required headers.
    $headers_optional_index = array_keys($headers_by_type['optional']);
    $headers_required_index = array_keys($headers_by_type['required']);

    $all_headers = $this->instance->getHeaders(['optional', 'required']);
    $this->assertEquals(
      array_merge($headers_optional_index, $headers_required_index),
      array_keys($all_headers), 
      'The set header index does not match the header index returned by headers getter method (param type: optional, required).'
    );


    // Test that if a type has no headers, the context array
    // for that type is set to empty array.
    $headers = [
      [
        'name' => 'Header 1',
        'type' => 'required'
      ],
      [
        'name' => 'Header 2',
        'type' => 'required',
      ],
      [
        'name' => 'Header 3',
        'type' => 'required'
      ]
    ];

    $this->instance->setHeaders($headers);

    // Get optional headers:
    $optional_set_headers = $this->instance->getOptionalHeaders();
    $this->assertEquals(
      $optional_set_headers,
      [], 
      'The optional type headers getter should return an empty array when no optional type defined in the headers parameter.'
    );
  }
}