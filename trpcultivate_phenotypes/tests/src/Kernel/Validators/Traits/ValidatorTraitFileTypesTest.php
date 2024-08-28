<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorFileTypes;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\FileTypes;

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
    // -- scenario label to provide helpful feedback if a test fails.
    // -- an array of arrays pertaining to file extensions:
    //    - an array of file extensions to pass to setSupportedMimeTypes()
    //    - an array of file extensions we expect to have returned by getSupportedFileExtensions()
    // -- an array of the expected mime types to be returned by getSupportedMimeTypes()
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
      'empty string', // scenario label
      [
        'input_file_extensions' => [],
        'expected_file_extensions' => [],
      ],
      [], // expected mime-types
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

    // #1: Just tsv
    $scenarios[] = [
      'tsv', // scenario label
      [
        'input_file_extensions' => ['tsv'],
        'expected_file_extensions' => ['tsv'],
      ],
      ['text/tab-separated-values'], // expected mime-types
      [
        'setSupportedMimeTypes' => FALSE,
        'getSupportedMimeTypes' => FALSE,
        'getSupportedFileExtensions' => FALSE,
      ],
      [
        'setSupportedMimeTypes' => '',
        'getSupportedMimeTypes' => '',
        'getSupportedFileExtensions' => '',
      ]
    ];

    // #2: Just csv
    $scenarios[] = [
      'csv', // scenario label
      [
        'input_file_extensions' => ['csv'],
        'expected_file_extensions' => ['csv'],
      ],
      ['text/csv'], // expected mime-types
      [
        'setSupportedMimeTypes' => FALSE,
        'getSupportedMimeTypes' => FALSE,
        'getSupportedFileExtensions' => FALSE,
      ],
      [
        'setSupportedMimeTypes' => '',
        'getSupportedMimeTypes' => '',
        'getSupportedFileExtensions' => '',
      ]
    ];

    // #3: Just txt
    $scenarios[] = [
      'txt', // scenario label
      [
        'input_file_extensions' => ['txt'],
        'expected_file_extensions' => ['txt'],
      ],
      ['text/plain'], // expected mime-types
      [
        'setSupportedMimeTypes' => FALSE,
        'getSupportedMimeTypes' => FALSE,
        'getSupportedFileExtensions' => FALSE,
      ],
      [
        'setSupportedMimeTypes' => '',
        'getSupportedMimeTypes' => '',
        'getSupportedFileExtensions' => '',
      ]
    ];

    // #4: tsv, txt
    $scenarios[] = [
      'tsv, txt', // scenario label
      [
        'input_file_extensions' => ['tsv', 'txt'],
        'expected_file_extensions' => ['tsv', 'txt'],
      ],
      ['text/tab-separated-values', 'text/plain'], // expected mime-types
      [
        'setSupportedMimeTypes' => FALSE,
        'getSupportedMimeTypes' => FALSE,
        'getSupportedFileExtensions' => FALSE,
      ],
      [
        'setSupportedMimeTypes' => '',
        'getSupportedMimeTypes' => '',
        'getSupportedFileExtensions' => '',
      ]
    ];

    // #5: csv, txt
    $scenarios[] = [
      'csv, txt', // scenario label
      [
        'input_file_extensions' => ['csv', 'txt'],
        'expected_file_extensions' => ['csv', 'txt'],
      ],
      ['text/csv', 'text/plain'], // expected mime-types
      [
        'setSupportedMimeTypes' => FALSE,
        'getSupportedMimeTypes' => FALSE,
        'getSupportedFileExtensions' => FALSE,
      ],
      [
        'setSupportedMimeTypes' => '',
        'getSupportedMimeTypes' => '',
        'getSupportedFileExtensions' => '',
      ]
    ];

    // Invalid types

    // #6: jpg, gif, svg
    $scenarios[] = [
      'jpg, gif, svg', // scenario label
      [
        'input_file_extensions' => ['jpg', 'gif', 'svg'],
        'expected_file_extensions' => [],
      ],
      [], // expected mime-types
      [
        'setSupportedMimeTypes' => TRUE,
        'getSupportedMimeTypes' => TRUE,
        'getSupportedFileExtensions' => TRUE,
      ],
      [
        'setSupportedMimeTypes' => 'The setSupportedMimeTypes() setter does not recognize the following extensions: jpg, gif, svg',
        'getSupportedMimeTypes' => $get_types_exception_message,
        'getSupportedFileExtensions' => $get_ext_exception_message,
      ]
    ];

    // #7: png, pdf
    $scenarios[] = [
      'png, pdf', // scenario label
      [
        'input_file_extensions' => ['png', 'pdf'],
        'expected_file_extensions' => [],
      ],
      [], // expected mime-types
      [
        'setSupportedMimeTypes' => TRUE,
        'getSupportedMimeTypes' => TRUE,
        'getSupportedFileExtensions' => TRUE,
      ],
      [
        'setSupportedMimeTypes' => 'The setSupportedMimeTypes() setter does not recognize the following extensions: png, pdf',
        'getSupportedMimeTypes' => $get_types_exception_message,
        'getSupportedFileExtensions' => $get_ext_exception_message,
      ]
    ];

    // #8: gzip
    $scenarios[] = [
      'gzip', // scenario label
      [
        'input_file_extensions' => ['gzip'],
        'expected_file_extensions' => [],
      ],
      [], // expected mime-types
      [
        'setSupportedMimeTypes' => TRUE,
        'getSupportedMimeTypes' => TRUE,
        'getSupportedFileExtensions' => TRUE,
      ],
      [
        'setSupportedMimeTypes' => 'The setSupportedMimeTypes() setter does not recognize the following extensions: gzip',
        'getSupportedMimeTypes' => $get_types_exception_message,
        'getSupportedFileExtensions' => $get_ext_exception_message,
      ]
    ];

    // Mixed types
    // #9: tsv, jpg
    $scenarios[] = [
      'tsv, jpg', // scenario label
      [
        'input_file_extensions' => ['tsv', 'jpg'],
        'expected_file_extensions' => [],
      ],
      [], // expected mime-types
      [
        'setSupportedMimeTypes' => TRUE,
        'getSupportedMimeTypes' => TRUE,
        'getSupportedFileExtensions' => TRUE,
      ],
      [
        'setSupportedMimeTypes' => 'The setSupportedMimeTypes() setter does not recognize the following extensions: jpg',
        'getSupportedMimeTypes' => $get_types_exception_message,
        'getSupportedFileExtensions' => $get_ext_exception_message,
      ]
    ];

    return $scenarios;
  }

  /**
   * Data Provider: provides various scenarios of mime-types from a single input file
   */
  public function provideMimeTypeForSetter() {

    $scenarios = [];

    // For each senario we expect the following:
    // -- scenario label to provide helpful feedback if a test fails.
    // -- an array of input and expected mime-type:
    //    - a mime-type as a string to pass to setFileMimeType()
    //    - a string that we expect to have returned by getFileMimeType()
    // -- an array of the expected file delimiters to be returned by getFileDelimiters()
    // -- an array indicating whether to expect and exception with the keys
    //    being the method and the value being TRUE if we expect an exception
    //    when calling it for this senario.
    // -- an array of expected exception messages with the key being the method
    //    and the value being the message we expect (NULL if no exception expected)

    // NOTE: getters have only one exception message and they are different
    // depending on the getter.
    $get_type_exception_message = 'Cannot retrieve the input file mime-type as it has not been set by setFileMimeType() method.';
    $get_delimiter_exception_message = 'Cannot retrieve supported file delimiters as they have not been set by setFileMimeType() method.';

    // #0: Test with an empty mime-type
    $scenarios[] = [
      'empty string', // scenario label
      [
        'input_mime_type' => '',
        'expected_mime_type' => '',
      ],
      [], // expected file delimiters
      [
        'setFileMimeType' => TRUE,
        'getFileMimeType' => TRUE,
        'getFileDelimiters' => TRUE,
      ],
      [
        'setFileMimeType' => "The setFileMimeType() setter requires a string of the input file's mime-type and must not be empty.",
        'getFileMimeType' => $get_types_exception_message,
        'getFileDelimiters' => $get_ext_exception_message,
      ]
    ];

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
  public function testSupportedMimeTypes($scenario, $file_extensions, $expected_mime_types, $expected_exception_thrown, $expected_exception_message) {

    // These exception messages are expected when we intially call the getter
    // methods for every scenario
    $get_types_exception_message = 'Cannot retrieve supported file mime-types as they have not been set by setSupportedMimeTypes() method.';
    $get_ext_exception_message = 'Cannot retrieve supported file extensions as they have not been set by setSupportedMimeTypes() method.';

    // scenario: Check that the getSupportedMimeTypes() throws exception when not set.
    // --------------------------------------------------------------------------
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
      $get_types_exception_message,
      $exception_message,
      'Exception message does not match the expected one when trying to get supported mime types before setting them.'
    );

    // scenario: Check that the getSupportedFileExtensions() throws exception
    // when not set.
    // --------------------------------------------------------------------------
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
      $get_ext_exception_message,
      $exception_message,
      'Exception message does not match the expected one when trying to get supported file extensions before setting them.'
    );

    // scenario: Finally test setSupportedMimeTypes() with current scenario.
    // --------------------------------------------------------------------------
    // Test various file extensions (see data provider) and check that their
    // expected supported mime types are returned by the getter method.
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $this->instance->setSupportedMimeTypes($file_extensions['input_file_extensions']);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expected_exception_thrown['setSupportedMimeTypes'],
      $exception_caught,
      "Unexpected exception activity occured for scenario: '" . $scenario . "'");
    $this->assertEquals(
      $expected_exception_message['setSupportedMimeTypes'],
      $exception_message,
      "The expected and actual exception messages do not match for scenario: '" . $scenario . "'"
    );

    // scenario: Check getSupportedMimeTypes() returns expected mime types after
    // setting.
    // --------------------------------------------------------------------------
    // Now try to grab the mime-types
    $actual_types = [];
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $actual_types = $this->instance->getSupportedMimeTypes();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expected_exception_thrown['getSupportedMimeTypes'],
      $exception_caught,
      "Unexpected exception activity occured when trying to get supported mime-types for scenario: '" . $scenario . "'"
    );
    $this->assertEquals(
      $expected_exception_message['getSupportedMimeTypes'],
      $exception_message,
      "The expected and actual exception messages do not match when calling getSupportedMimeTypes() for scenario: '" . $scenario . "'"
    );
    // Finally, check that our retreived mime-types match our expected
    $this->assertEquals(
      $expected_mime_types,
      $actual_types,
      "The expected mime-types using getSupportedMimeTypes() did not match the actual ones for scenario: '" . $scenario . "'"
    );

    // scenario: Check getSupportedFileExtensions() returns valid extensions
    // after setting.
    // --------------------------------------------------------------------------
    // Now try to grab the supported file extensions
    $actual_extensions = [];
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $actual_extensions = $this->instance->getSupportedFileExtensions();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expected_exception_thrown['getSupportedFileExtensions'],
      $exception_caught,
      "Unexpected exception activity occured when trying to get file extensions for scenario: '" . $scenario . "'"
    );
    $this->assertEquals(
      $expected_exception_message['getSupportedFileExtensions'],
      $exception_message,
      "The expected and actual exception messages do not match when calling getSupportedFileExtensions() for scenario: '" . $scenario . "'"
    );
    // Finally, check that our retreived file extensions match our expected
    $this->assertEquals(
      $file_extensions['expected_file_extensions'],
      $actual_extensions,
      "The expected file extensions using getSupportedFileExtensions() did not match the actual ones for scenario: '" . $scenario . "'"
    );
  }

  /**
   * Tests setter/getters focused on the file in the current run.
   *
   * Specifically,
   *  - FileTypes::setFileMimeType()
   *  - FileTypes::getFileMimeType()
   *  - FileTypes::getFileDelimiters()
   *
   * @dataProvider provideMimeTypeForSetter
   *
   * @return void
   */
  public function testFileMimeType($scenario, $mime_type, $expected_delimiters, $expected_exception_thrown, $expected_exception_message) {

    // These exception messages are expected when we intially call the getter
    // methods for every scenario
    $get_type_exception_message = 'Cannot retrieve the input file mime-type as it has not been set by setFileMimeType() method.';
    $get_delimiter_exception_message = 'Cannot retrieve supported file delimiters as they have not been set by setFileMimeType() method.';

    // scenario: Check that getFileMimeType() throws exception when not set.
    // --------------------------------------------------------------------------
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $this->instance->getFileMimeType();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'FileTypes::getFileMimeType() method should throw an exception when trying to get a mime type before setting it.');
    $this->assertEquals(
      $get_type_exception_message,
      $exception_message,
      'Exception message does not match the expected one when trying to get mime type before setting it.'
    );
  }
}
