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
    foreach($expected_types as $type) {
      // Exception message when failed to set headers - all header types.
      $expected_message = 'Cannot retrieve %s headers from the context array as one has not been set by setHeaders() method.';
      $method = 'get' . ucfirst($type) . 'Headers';

      try {
        $this->instance->$method();
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }

      $this->assertTrue($exception_caught, 'Header type ' . $type . ' getter method should throw an exception for unset header.');
      $this->assertStringContainsString(
        sprintf($expected_message, $type),
        $exception_message,
        'Expected exception message does not match the message when trying to get headers of type ' . $type . ' on unset headers.'
      );
    }

    // Exception message when failed to set headers - all headers.
    try {
      $this->instance->getAllHeaders();
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'All headers getter method should throw an exception for unset header.');
    $this->assertStringContainsString(
      'Cannot retrieve all headers from the context array as one has not been set by setHeaders() method.',
      $exception_message,
      'Expected exception message does not match the message when trying to get all headers on unset header.'
    );

    
    // @TODO:
    // - other case that throws an exception: 
    //   headers array does not contain a required header.
    //   headers array is an empty array.
    // 
    // - create test cases for the setter method.
    
  }
}