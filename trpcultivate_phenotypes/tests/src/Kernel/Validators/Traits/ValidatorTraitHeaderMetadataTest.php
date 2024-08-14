<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorHeaderMetadata;

 /**
  * Tests the header metadata validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitHeaderMetadataTest extends ChadoTestKernelBase {

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
   * @var ValidatorHeaderMetadata
   */
  protected ValidatorHeaderMetadata $instance;

  /**
   * Test headers. This test values is equivalent to setting
   * up the required headers expected by the importer.
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
    $validator_id = 'validator_requiring_header_metadata';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Header Metadata Trait',
      'input_types' => ['header-row', 'data-row'],
    ];

    $instance = new ValidatorHeaderMetadata(
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
   * Tests the HeaderMetadata::setHeaderMetadata() setter and
   * HeaderMetadata::getHeaderMetadata() getter.
   *
   * @return void
   */
  public function testHeaderMetadataSetterGetter() {
    // Test getter will trigger an error when attempting to get a header metadata
    // prior to a call to header metadata setter method.
    
    // Exception message when failed to set header meatadata.
    $expected_message = 'Cannot retrieve header metadata set by the importer.';

    try {
      $this->instance->getHeaderMetadata();
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Header Metadata getter method should throw an exception for unset header metadata.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset header metadata.'
    );
    
    
    // Test an empty headers passed as parameter to the setter.
    
    // Exception message when array does not contain the key-value elements.
    $expected_message = 'The headers provided does not contain key-value pair values.';

    try {
      $this->instance->setHeaderMetadata($this->test_headers);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Header Metadata setter method should throw an exception for empty header array.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to set unset header metadata with empty header array.'
    );


    // Test that an item in the headers is of the incorrect data type.
    
    // An integer value as key (header).    
    $this->test_headers = [
      1 => 'HeaderOne',
      'HeaderTwo' => 'HeaderTwo',
      3 => 'HeaderThree'
    ];
    // Exception message when headers contain in valid integer data type value.
    $expected_message = 'The headers provided contain integer data type value as header: %s';

    try {
      $this->instance->setHeaderMetadata($this->test_headers);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Header Metadata setter method should throw an exception for empty header array.');
    $this->assertStringContainsString(
      sprintf($expected_message, '1, 3'),
      $exception_message,
      'Expected exception message does not match the message when trying to set unset header metadata with empty header array.'
    );
    

    // Test a valid header and the setter will trim any spaces off the header values.

    // Set the test headers to a set of headers with descriptions.
    // Headers has leading spaces to test setter will remove spaces.
    $this->test_headers = [
      ' HeaderOne '  => 'Header One description text',
      ' HeaderTWO '   => 'Header Two description text',
      ' HeaderThree ' => 'Header Three description text',
      ' HeaderFour '  => 'Header Four description text',
      ' HeaderFive ' => 'Header Five description text',
    ];
    
    $arr_headers = array_keys($this->test_headers);
    $sanitized_headers = array_map('trim', $arr_headers);

    // Set the header and test that it matches the sanitized headers above.
    $this->instance->setHeaderMetadata($this->test_headers);
    // Get the header metadata.
    $header_metadata = $this->instance->getHeaderMetadata();
    $this->assertEquals(
      $sanitized_headers, 
      $header_metadata, 
      'The set header metadata does not match the header metadata returned by the header metadata getter method'
    );
  }
}