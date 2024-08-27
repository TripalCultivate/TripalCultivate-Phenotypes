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
   * Data Provider: provides various scenarios of file extensions
   */
  public function provideExtensionsForSetter() {

    $scenarios = [];

    // For each senario we expect the following:
    // -- an array of file extensions to pass to setSupportedMimeTypes()
    // -- an array of the expected mime types to be returned by getSupportedMime Types.
    // -- scenario label to provide helpful feedback if a test fails.
    // -- an array indicating whether to expect and exception with the keys
    //    being the method and the value being TRUE if we expect an exception
    //    when calling it for this senario.
    // -- an array of expected exception messages with the key being the method
    //    and the value being the message we expect (NULL if no exception expected)

    // NOTE: getters have only one exception message and they are different
    // depending on the getter.
    $get_types_exception_message = 'Cannot retrieve supported file mime-types as they have not been set by setSupportedMimeTypes() method.';
    $get_ext_exception_message = 'Cannot retrieve supported file extensions as they have not been set by setSupportedMimeTypes() method.';

    // #0: Test with an empty extensions array
    $scenarios[] = [
      [], // file extensions
      [], // expected mime-types
      'invalid', // case
      [
        'setSupportedMimeTypes' => TRUE,
        'getSupportedMimeTypes' => TRUE,
        'getSupportedFileExtensions' => TRUE,
      ],
      [
        'setSupportedMimeTypes' => 'The setSupportedMimeTypes() setter requires an array of file extensions that are supported by the importer and must not be empty.',
        'getSupportedMimeTypes' => $get_types_exception_message,
        'getSupportedFileExtensions' => $get_ext_exception_message,
      ]
    ];
    /**
    $scenarios[] = [
      [], // file extensions
      [], // expected mime-types
      'invalid', // case
      TRUE, // setter exception thrown
      'The setSupportedMimeTypes() setter requires an array of file extensions that are supported by the importer and must not be empty.', // expected exception message by the setter
    ];
    */

    /*
    // #1: Just tsv
    $scenarios[] = [
      ['tsv'],
      ['text/tab-separated-values'],
      'valid',
      FALSE,
      '',
    ];

    // #2: Just csv
    $scenarios[] = [
      ['csv'],
      ['text/csv'],
      'valid',
      FALSE,
      '',
    ];

    // #3: Just txt
    $scenarios[] = [
      ['txt'],
      ['text/plain'],
      'valid',
      FALSE,
      '',
    ];

    // #4: tsv, txt
    $scenarios[] = [
      ['tsv', 'txt'],
      ['text/tab-separated-values', 'text/plain'],
      'valid',
      FALSE,
      '',
    ];

    // #5: csv, txt
    $scenarios[] = [
      ['csv', 'txt'],
      ['text/csv', 'text/plain'],
      'valid',
      FALSE,
      '',
    ];

    // Invalid types

    // #6: jpg, gif, svg
    $scenarios[] = [
      ['jpg', 'gif', 'svg'],
      [],
      'invalid',
      TRUE,
      'The setSupportedMimeTypes() setter does not recognize the following extensions: jpg, gif, svg',
    ];

    // #7: png, pdf
    $scenarios[] = [
      ['png', 'pdf'],
      [],
      'invalid',
      TRUE,
      'The setSupportedMimeTypes() setter does not recognize the following extensions: png, pdf',
    ];

    // #8: gzip
    $scenarios[] = [
      ['gzip'],
      [],
      'invalid',
      TRUE,
      'The setSupportedMimeTypes() setter does not recognize the following extensions: gzip',
    ];

    // Mixed types
    // #9: tsv, jpg
    $scenarios[] = [
      ['tsv', 'jpg'],
      [],
      'mixed',
      TRUE,
      'The setSupportedMimeTypes() setter does not recognize the following extensions: jpg',
    ];
    */

    return $scenarios;
  }

  /**
   * Tests setter/getters are focused on what the importer supports.
   *
   * Specifically,
   *  - FileTypes::setSupportedMimeTypes()
   *  - FileTypes::getSupportedMimeTypes()
   *  - FileTypes::getSupportedFileExtensions()
   *
   * @dataProvider provideExtensionsForSetter
   *
   * @return void
   */
  public function testSupportedMimeTypes($expected_extensions, $expected_mime_types, $case, $expect_expection_thrown, $expected_exception_message) {

    // CASE: Check that the getSupportedMimeTypes() throws exception when not set
    // --------------------------------------------------------------------------
    // Attempt to get mime-types with the getter before any have been set.
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $this->instance->getSupportedMimeTypes();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'FileTypes::getSupportedMimeTypes() method should throw an exception when trying to get supported mime types before setting them.');
    $this->assertEquals(
      $expected_exception_message['getSupportedMimeTypes'],
      $exception_message,
      'Exception message does not match the expected one when trying to get supported mime types before setting them.'
    );

    // CASE: Check that the getSupportedFileExtensions() throws exception when not set
    // --------------------------------------------------------------------------
    // Attempt to get file extensions with the getter before any have been set.
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $this->instance->getSupportedFileExtensions();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'FileTypes::getSupportedFileExtensions() method should throw an exception when trying to get supported file extensions before setting them.');
    $this->assertEquals(
      $expected_exception_message['getSupportedFileExtensions'],
      $exception_message,
      'Exception message does not match the expected one when trying to get supported file extensions before setting them.'
    );

    // CASE: Finally test setSupportedMimeTypes() with current scenario.
    // --------------------------------------------------------------------------
    // Test various file extensions (see data provider) and check that their
    // expected supported mime types are returned by the getter method.
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $this->instance->setSupportedMimeTypes($expected_extensions);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals($expect_expection_thrown['setSupportedMimeTypes'], $exception_caught, 'Unexpected exception activity occured when ' . $case . ' file extensions were provided.');
    $this->assertEquals(
      $expected_exception_message['setSupportedMimeTypes'],
      $exception_message,
      'The expected and actual exception messages do not match with ' . $case . ' file extensions provided.'
    );

    // CASE: Check getSupportedMimeTypes() returns expected mime types.
    // --------------------------------------------------------------------------
    // Now try to grab the mime-types
    $grabbed_types = [];
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $grabbed_types = $this->instance->getSupportedMimeTypes();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expect_expection_thrown['getSupportedMimeTypes'],
      $exception_caught,
      'Unexpected exception activity occured when trying to get supported mime-types after ' . $case . ' file extensions were provided.');
    $this->assertEquals(
      $expected_exception_message['getSupportedMimeTypes'],
      $exception_message,
      'The expected and actual exception messages do not match when calling getSupportedMimeTypes() after setting with ' . $case . ' file extensions provided.'
    );
    // Finally, check that our retrieved mime-types match our expected
    $this->assertEquals($expected_mime_types, $grabbed_types);

    // CASE: Check getSupportedFileExtensions() returns original extensions.
    // --------------------------------------------------------------------------
    // Now try to grab the supported file extensions
    $grabbed_extensions = [];
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $grabbed_extensions = $this->instance->getSupportedFileExtensions();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expect_expection_thrown['getSupportedFileExtensions'],
      $exception_caught,
      'Unexpected exception activity occured when trying to get file extensions after ' . $case . ' file extensions were provided.'
    );
    $this->assertEquals(
      $expected_exception_message['getSupportedFileExtensions'],
      $exception_message,
      'The expected and actual exception messages do not match when calling getSupportedFileExtensions() after setting with ' . $case . ' file extensions provided.'
    );
    // Finally, check that our retrieved mime-types match our expected
    $this->assertEquals($expected_extensions, $grabbed_extensions);
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
  public function testFileMimeTypes() {

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
