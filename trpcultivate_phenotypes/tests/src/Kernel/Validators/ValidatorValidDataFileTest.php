<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Tests Tripal Cultivate Phenotypes Data File Validator Plugins.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
class ValidatorValidDataFileTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * An array of input test files.
   *
   * Each element is keyed by short scenario description and the value is an
   * array with the following keys:
   *   - 'filename': the name of the file (should match filename in test_param).
   *   - 'fid': the file id number.
   *   - 'mime': the file MIME type.
   *   - 'extension': the file extension.
   *
   * @var array
   */
  protected $test_files;

  /**
   * An instance of the data file validator.
   *
   * @var object
   */
  protected $validator_instance;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'file',
    'user',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a plugin instance for this validator.
    $validator_id = 'valid_data_file';
    $this->validator_instance = \Drupal::service('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Create test files.
    $this->installEntitySchema('file');

    // Set the supported mime types for this test.
    $this->validator_instance->setSupportedMimeTypes([
      'tsv',
    ]);

    $test_file_scenario = [
      // A valid file type, default type expected by the importer.
      'file-valid' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => [
          'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        ],
      ],

      // A valid file type, an empty file.
      'file-empty' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => [
          'string' => '',
        ],
        'filesize' => 0,
      ],

      // Not valid file.
      'file-image' => [
        'ext' => 'png',
        'mime' => 'image/png',
        'content' => [
          'string' => '',
          // File can be found in the test Fixtures folder.
          'file' => 'png.png',
        ],
      ],

      // Pretend tsv file.
      'file-pretend' => [
        'ext' => 'tsv',
        'mime' => 'application/pdf',
        'content' => [
          'string' => '',
          // File can be found in the test Fixtures folder.
          'file' => 'pdf.txt',
        ],
      ],

      // Could not open the file - not permitted to read.
      'file-locked' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => [
          'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        ],
        'permissions' => 'none',
      ],

      // Test a txt file type.
      'file-alternative' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => [
          'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        ],
      ],

      // A manaaged file with file object record but refereced file is missing.
      'file-missing' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => [
          'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        ],
      ],
    ];

    // Array to hold the file id and file uri of the generated files
    // that will be used as parameters to the validator (filename or fid).
    $test_file_param = [];

    // Create the file for each test file scenario.
    foreach ($test_file_scenario as $test_scenario => $file_properties) {
      $filename = 'test_data_file_' . $test_scenario . '.' . $file_properties['ext'];
      $file_properties['filename'] = $filename;

      $file = $this->createTestFile($file_properties);

      // Reference relevant file properties that will be used
      // to indicate attributes of the file that failed the validation.
      $file_id = $file->id();
      $file_mime_type = $file->getMimeType();
      $file_filename = $file->getFileName();
      $file_extension = pathinfo($file_filename, PATHINFO_EXTENSION);

      // Create a test scenario and attach the file properties.
      $test_file_param[$test_scenario] = [
        'filename' => $file_filename,
        'fid' => $file_id,
        'mime' => $file_mime_type,
        'extension' => $file_extension,
      ];

      // With the file for file-missing scenario created, delete the file using
      // PHP delete file function to maintain the file object record.
      if ($test_scenario == 'file-missing') {
        $missing_file_uri = $file->getFileUri();
        unlink($missing_file_uri);
      }
    }

    // Create test scenario where the file id is null.
    $test_file_param['null-fid-parameter'] = [
      'filename' => '',
      'fid' => NULL,
      'mime' => '',
      'extension' => '',
    ];

    // Create test scenario where the fid is zero.
    $test_file_param['zero-fid-parameter'] = [
      'filename' => '',
      'fid' => 0,
      'mime' => '',
      'extension' => '',
    ];

    // Create test scenario where the file id does not exist.
    $test_file_param['non-existent-fid'] = [
      'filename' => '',
      'fid' => 999,
      'mime' => '',
      'extension' => '',
    ];

    // Set the property to all test file input scenarios.
    $this->test_files = $test_file_param;
  }

  /**
   * Data provider: provides test data file input.
   *
   * @return array
   *   Each scenario/element is an array with the following values:
   *   - A human-readable short description of the test scenario.
   *   - Test scenario array key set in the $test_files property. The key
   *     corresponds to file properties created in setUp() method above.
   *   - Expected validation response with the following keys.
   *     - 'validation_response': the response array returned by the validator
   *       that contains the case title and valid status information.
   *     - 'failed_items_key': a list of keys that reference file attributes
   *       that cause validation to fail:
   *       - 'filename': filename.
   *       - 'fid': the file id number.
   *       - 'mime': the file MIME type.
   *       - 'extension': the file extension.
   */
  public function provideFileForDataFileValidator() {

    return [
      // #0: Test a null file id number.
      [
        'null fid parameter',
        'null-fid-parameter',
        [
          'validation_response' => [
            'case' => 'Invalid file id number',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'fid',
          ],
        ],
      ],

      // #1: Test a zero file id number.
      [
        'zero fid parameter',
        'zero-fid-parameter',
        [
          'validation_response' => [
            'case' => 'Invalid file id number',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'fid',
          ],
        ],
      ],

      // #2: Test non-existent file id number.
      [
        'file id number does not exist',
        'non-existent-fid',
        [
          'validation_response' => [
            'case' => 'File id failed to load a file object',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'fid',
          ],
        ],
      ],

      // #3: Test a valid file - primary type (tsv).
      [
        'valid tsv file',
        'file-valid',
        [
          'validation_response' => [
            'case' => 'Data file is valid',
            'valid' => TRUE,
          ],
          'failed_items_key' => [],
        ],
      ],

      // #4: Test an empty file.
      [
        'file is empty',
        'file-empty',
        [
          'validation_response' => [
            'case' => 'The file has no data and is an empty file',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid',
          ],
        ],
      ],

      // #5: Test file that is not the right MIME type.
      [
        'incorrect mime type',
        'file-image',
        [
          'validation_response' => [
            'case' => 'Unsupported file mime type and unsupported extension',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'mime',
            'extension',
          ],
        ],
      ],

      // #6: Test txt file type.
      [
        'txt file type',
        'file-alternative',
        [
          'validation_response' => [
            'case' => 'Unsupported file mime type and unsupported extension',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'mime',
            'extension',
          ],
        ],
      ],

      // #7. Test file of a type pretending to be another.
      [
        'pretentious file',
        'file-pretend',
        [
          'validation_response' => [
            'case' => 'Unsupported file MIME type',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'mime',
            'extension',
          ],
        ],
      ],

      // #8: Test a locked file - cannot read a valid file.
      [
        'file is locked',
        'file-locked',
        [
          'validation_response' => [
            'case' => 'Data file cannot be opened',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid',
          ],
        ],
      ],

      // #9: Test a managed file id but the file is missing.
      [
        'file is missing',
        'file-missing',
        [
          'validation_response' => [
            'case' => 'Data file cannot be opened',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid',
          ],
        ],
      ],
    ];
  }

  /**
   * Test data file input validator.
   *
   * @param string $scenario
   *   A human-readable short description of the test scenario.
   * @param string $test_file_key
   *   Test scenario array key set in the $test_files property.
   * @param array $expected
   *   The expected validation response with the following keys:
   *   - 'filename': using filename (first parameter).
   *   - 'fid': using fid (file id, second parameter).
   *   - 'failed_items_key': a list of keys that reference file attributes
   *     that cause validation to fail:
   *     - 'filename': filename.
   *     - 'fid': the file id number.
   *     - 'mime': the file MIME type.
   *     - 'extension': the file extension.
   *
   * @dataProvider provideFileForDataFileValidator
   */
  public function testDataFileInput(string $scenario, string $test_file_key, array $expected) {
    $file_input = $this->test_files[$test_file_key];
    $fid = $file_input['fid'];

    $validation_status = $this->validator_instance->validateFile($fid);

    // Determine the actual failed items.
    // - If the validation passed, the failed item is an empty array.
    // - If failed, create the item key specified by the test scenario and set
    //   the value using the value of the same key in the test_files property.
    $failed_items = [];
    foreach ($expected['failed_items_key'] as $file_property) {
      $failed_items[$file_property] = $file_input[$file_property];
    }

    $expected['validation_response']['failedItems'] = ($validation_status['valid']) ? [] : $failed_items;

    foreach ($validation_status as $key => $value) {
      $this->assertEquals(
        $value,
        $expected['validation_response'][$key],
        'The validation status key ' . $key . ' with the fid parameter ' . $fid . ', does not match expected in scenario: ' . $scenario
      );
    }
  }

}
