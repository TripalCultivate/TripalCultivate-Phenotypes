<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorFileTypes;

 /**
  * Tests the file types validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitFileTypesTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * The validator instance to use for testing.
   *
   * @var ValidatorFileTypes
   */
  protected ValidatorFileTypes $instance;

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
    $validator_id = 'validator_requiring_filetypes';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using File Types Trait',
      'input_types' => ['file'],
    ];

    $instance = new ValidatorFileTypes(
      $configuration,
      $validator_id,
      $plugin_definition
    );

    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the File Types trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the FileTypes::setFileTypes() setter and
   * FileTypes::getFileTypes() getter.
   *
   * @return void
   */
  public function testFileTypesSetterGetter() {
    // Test getter will trigger an error when attempting to get file types
    // prior to a call to file types setter method.
    
    // Exception message when failed to set file types.
    $expected_message = 'Cannot retrieve file types from the context array as one has not been set by setFileTypes() method.';

    try {
      $this->instance->getFileTypes();
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'File types getter method should throw an exception for unset file types.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset file types.'
    );
    

    // Test invalid file types will trigger an exception.

    // No file type provided:
    // Exception message when no file type was provided to the setter.
    $exception_caught = FALSE;
    $exception_message = '';
    
    try {
      $this->instance->setFileTypes([]);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'File types setter method should throw an exception when no file type was provided.');
    $this->assertStringContainsString(
      $exception_message,
      'The File Types Trait requires an array of file extensions and must not be empty.',
      'Expected exception message does not match the message when no value was passed to the file type setter method'
    );
  
    // Unsupported file extensions.
    $invalid_types = [['jpg', 'gif', 'svg'], ['png', 'pdf'], ['gzip']];
    // Exception message when unsupported file types was provided to the setter.
    $expected_message =  'The File Types Trait could not to resolve the mime type of the extensions: %s';

    foreach($invalid_types as $invalid_type) {
      $exception_caught = FALSE;
      $exception_message = '';
      
      $str_file_types = implode(', ', $invalid_type);

      try {
        $this->instance->setFileTypes($invalid_type);
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
      
      $this->assertTrue($exception_caught, 'File type setter method should throw an exception for unsupported file types.');
      $this->assertStringContainsString(
        sprintf($expected_message, $str_file_types),
        $exception_message,
        'Expected exception message does not match the message when unsupported type was passed to the file type setter method'
      );
    }
    
    // Test a valid type and an unsupported type.
    $unsupported_type = 'jpg';
    $valid_invalid_types = ['tsv', $unsupported_type];
    
    $exception_caught = FALSE;
    $exception_message = '';
    
    try {
      $this->instance->setFileTypes($valid_invalid_types);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'File types setter method should throw an exception when a type mime could not be resolved.');
    $this->assertStringContainsString(
      $exception_message,
      'The File Types Trait could not to resolve the mime type of the extensions: ' . $unsupported_type,
      'Expected exception message does not match the message when a type mime could not be resolved'
    );

    
    // Test valid types.

    // Supported file types: tsv, csv and txt.
    $valid_types = [['tsv', 'txt'], ['csv', 'txt'], ['tsv'], ['csv'], ['txt']];

    foreach($valid_types as $valid_type) {
      $this->instance->setFileTypes($valid_type);
      $type = $this->instance->getFileTypes();

      // Test that each type has an entry in the context variable.
      foreach($valid_type as $file_type) {
        $this->assertNotEmpty($type[ $file_type ], 'The extension ' . $file_type. ' must be set in the context file_type property.');
      }
    }

    // Test that the set value is the correct mime.
    $tsv_txt = [
      'tsv' => 'text/tab-separated-values',
      'txt' => 'text/plain'
    ];

    $types = array_keys($tsv_txt);

    $this->instance->setFileTypes($types);
    $type = $this->instance->getFileTypes();

    $this->assertEquals($tsv_txt, $type, 'Tsv file type set does not match returned file type of the getter method.');
  }
}