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
   * Tests setter/getters are focused on what the importer supports.
   *
   * Specifically,
   *  - FileTypes::setSupportedMimeTypes()
   *  - FileTypes::getSupportedMimeTypes()
   *  - FileTypes::getSupportedFileExtensions()
   *
   * @return void
   */
  public function testSupportedMimeTypes() {

    // Exception message when failed to set supported mime-types
    $exception_caught = FALSE;
    $exception_message = '';
    $expected_message = 'Cannot retrieve supported file mime-types as they have not been set';

    try {
      $this->instance->getSupportedMimeTypes();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'FileTypes::getSupportedMimeTypes() method should throw an exception when trying to get supported mime types before setting them.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Exception message does not match the expected one when trying to get supported mime types before setting them.'
    );

    // Unsupported file extensions.
    $invalid_types = [['jpg', 'gif', 'svg'], ['png', 'pdf'], ['gzip']];
    // Exception message when unsupported file types was provided to the setter.
    $exception_caught = FALSE;
    $exception_message = '';
    $expected_message =  'The setSupportedMimeTypes() setter does not recognize the following extensions: %s';

    foreach ($invalid_types as $invalid_type) {
      $exception_caught = FALSE;
      $exception_message = '';

      $str_file_types = implode(', ', $invalid_type);

      try {
        $this->instance->setSupportedMimeTypes($invalid_type);
      } catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }

      $this->assertTrue($exception_caught, 'FileType::setSupportedMimeTypes() setter method should throw an exception for unsupported file types.');
      $this->assertStringContainsString(
        sprintf($expected_message, $str_file_types),
        $exception_message,
        'Exception message does not match the expected one when an unsupported type was passed to the FileType::setSupportedMimeTypes() setter.'
      );
    }

    // Test a valid type and an unsupported type.
    $unsupported_type = 'jpg';
    $valid_invalid_types = ['tsv', $unsupported_type];
    $expected_message = "The setSupportedMimeTypes() setter does not recognize the following extensions: $unsupported_type";

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      $this->instance->setSupportedMimeTypes($valid_invalid_types);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'FileType::setSupportedMimeTypes() setter method should throw an exception when a mix of supported and unsupported extensions are passed in.');
    $this->assertStringContainsString(
      $exception_message,
      $expected_message,
      'Exception message does not match the expected one when a mix of supported and unsupported file extensions are passed into FileTypes::setSupportedMimeTypes().'
    );

    // Test valid types.
    // Supported file types: tsv, csv and txt.
    /* Commenting this out as it might be better as a data provider since we now need to know the mime types as well.
    $valid_extensions = [['tsv', 'txt'], ['csv', 'txt'], ['tsv'], ['csv'], ['txt']];

    foreach ($valid_types as $valid_type) {
      $this->instance->setSupportedMimeTypes($valid_type);
      $type = $this->instance->getSupportedMimeTypes();

      // Test that each type has an entry in the context variable.
      foreach ($valid_type as $file_type) {
        $this->assertNotEmpty($type[$file_type], 'The extension ' . $file_type . ' must be set in the context file_type property.');
      }
    }
      */
  }

  /**
   * Tests setter/getters focused on the file in the current run.
   *
   * Specifically,
   *  - FileTypes::setFileMimeType()
   *  - FileTypes::getFileMimeType()
   *  - FileTypes::getFileDelimiters()
   *
   * @return void
   */
  public function testFileTypesSetterGetter() {

    // Test than an empty mime-type will trigger an exception.
    $exception_caught = FALSE;
    $exception_message = '';
    $expected_message = "The FileTypes Trait requires a string of the input file's mime-type and must not be empty.";

    try {
      $this->instance->setFileMimeType('');
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'FileTypes::setFileMimeType() should thrown an exception when an empty string is passed in.');
    $this->assertStringContainsString(
      $exception_message,
      $expected_message,
      'Exception message does not match the expected one when no value was passed to the FileTypes::setFileMimeType() method'
    );
  }
}
