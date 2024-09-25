<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating data file.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\file\Entity\File;

 /**
  * Tests Tripal Cultivate Phenotypes Data File Validator Plugins.
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class ValidatorValidDataFileTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * An array of input test file. Each element is keyed by short description and the value 
   * is an array with the following keys:
   *  - test_param: is a list of parameter combination that will be passed to the validate method.
   *    each item in the list is an array of 2 elements where the first element is parameter to filename
   *    and the second element is parameter to fid.
   * - test_file: is a list of file properties keyed by:
   *  - filename: filename or the value as provided to the filename parameter.
   *  - fid: the file id number.
   *  - mime: the file MIME type.
   *  - extension: the file extension. 
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
    $validator_id = 'valid_data_file';
    $this->validator_instance = \Drupal::service('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Create test files.
    $this->installEntitySchema('file');
    
    // Set the supported mime types for this test.
    $this->validator_instance->setSupportedMimeTypes([
      'tsv', // text/tab-separated-values
      'txt'  // text/plain
    ]);

    $test_file_scenario = [
      // A valid file type, default type expected by the importer.
      'file-valid' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        'filesize' => 1024
      ],
      
      // A valid file type, an empty file.
      'file-empty' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => '',
        'filesize' => 0
      ],
      
      // An alternative file type.
      'file-alternative' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        'filesize' => 1024,
      ],
      
      // Not valid file.
      'file-image' => [
        'ext' => 'png',
        'mime' => 'image/png',
        'content' => '',
        'filesize' => 1024,
        'file' => 'png.png' // Can be found in the test Fixtures folder.
      ],
      
      // Pretend tsv file.
      'file-pretend' => [
        'ext' => 'tsv',
        'mime' => 'application/pdf',
        'filesize' => 1024,
        'file' => 'pdf.txt' // Can be found in the test Fixtures folder.
      ],

      // Could not open the file - not permitted to read.
      'file-locked' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        'filesize' => 1024,
        'lock' => TRUE
      ]
    ];
    
    // Array to hold the file id and file uri of the generated files
    // that will be used as parameters to the validator (filename or fid).
    $test_file_param = [];

    // Create the file for each test file scenario.
    foreach($test_file_scenario as $test_scenario => $file_properties) {
      $filename = 'test_data_file_' . $test_scenario . '.' . $file_properties['ext'];

      $file = File::create([
        'filename' => $filename,
        'filemime' => $file_properties['mime'],
        'uri' => 'public://' . $filename,
        'status' => 0
      ]);
      
      // Update test scenario file properties.
      
      // Set the file size.
      if (isset($file_properties['filesize'])) {
        $file->setSize($file_properties['filesize']);
      }

      $file->save();

      // Reference relevant file properties that will be used
      // to indicate attributes of the file that failed the validation.
      $file_id  = $file->id();
      $file_uri = $file->getFileUri();
      $file_mime_type = $file->getMimeType();
      $file_filename = $file->getFileName();
      $file_extension = pathinfo($file_filename, PATHINFO_EXTENSION);

      // Write contents into the file.
      if (!empty($file_properties['content'])) {
        file_put_contents($file_uri, $file_properties['content']);
      }

      // If an existing file was specified, move the file fixture into the uri 
      // to override the created file and use it in lieu of the created file.
      if (!empty($file_properties['file'])) {
        $path_to_fixtures = __DIR__ . '/../../Fixtures/';
        $full_path = $path_to_fixtures . $file_properties['file'];
        $this->assertFileIsReadable($full_path,
          "Unable to setup FILE ". $test_scenario . " because cannot access Fixture file at $full_path.");

        copy($full_path, $file_uri);
      }
      
      // If file should be locked.
      if (isset($file_properties['lock']) && $file_properties['lock']) {
        chmod($file_uri, 0000);
      }

      // Create a test scenario file input parameter and attach the file properties.
      $test_file_param[ $test_scenario ] = [
        'test_param' =>[
          'filename' => [$file_uri, NULL], // Test input by filename.
          'fid' => ['', $file_id],         // Test input by file id (fid).
        ],

        'test_file' => [                   // Test file properties.
          'filename' => $file_filename,
          'fid' => $file_id,
          'mime' => $file_mime_type,
          'extension' => $file_extension
        ]
      ];
    }
    

    // Create an unmanaged file copy of the valid test file scenario 
    // to use as input for validating a file without a file id (unmanaged file).
    $file_valid_uri = $test_file_param['file-valid']['test_param']['filename'][0];
    // Rename the duplicate copy as unmanaged_test_data_file.
    $file_unmanaged_uri = str_replace('test_data_file', 'unmanaged_test_data_file', $file_valid_uri);
    // Move a copy of the file and rename it using the new filename.
    copy($file_valid_uri, $file_unmanaged_uri);

    // Create test scenario file input using the filename of this unmanaged file.
    $test_file_param['file-unmanaged'] = [
      'test_param' => [
        'filename' => [$file_unmanaged_uri, NULL],
      ],

      'test_file' => [
        'filename' => $file_unmanaged_uri,
        'fid' => NULL,
        'mime' => 'text/tab-separated-values',
        'extension' => 'tsv'
      ]
    ];
    
    
    // Create test scenario file input where the filename is an empty string value.
    $test_file_param['invalid-filename-parameter'] = [
      'test_param' => [
        'filename' => ['', NULL]
      ],

      'test_file' => [
        'filename' => '', 
        'fid' => NULL,
        'mime' => '',
        'extension' => ''
      ]
    ];
    

    // Create test scenario file input where the fid is zero.
    $test_file_param['invalid-fid-parameter'] = [
      'test_param' => [
        'fid' => ['', 0]
      ],

      'test_file' => [
        'filename' => '', 
        'fid' => 0,
        'mime' => '',
        'extension' => ''
      ]
    ];


    // Create test scenario file input where the filename does not exist.
    $test_file_param['non-existent-filename'] = [
      'test_param' => [
        'filename' => ['public://non-existent.tsv', NULL]
      ],

      'test_file' => [
        'filename' => 'public://non-existent.tsv', 
        'fid' => NULL,
        'mime' => '',
        'extension' => ''
      ]
    ];

    
    // Create test scenario file input where the file id does not exist.
    $test_file_param['non-existent-fid'] = [
      'test_param' => [
        'fid' => ['', 999]
      ],

      'test_file' => [
        'filename' => '', 
        'fid' => 999,
        'mime' => '',
        'extension' => ''
      ]
    ];
    

    // Set the property to all test file input scenario.
    $this->test_files = $test_file_param;
  }

  /**
   * Data provider: provides test data file input.
   * 
   * @return array
   *   Each scenario/element is an array with the following values.
   *   
   *   - A string, human-readable short description of the test scenario.
   *   - Test scenario array key set in the $test_files property. The key corresponds to a pair of file input (filename and fid).
   *   - Expected validation response for using either parameters. An empty array value indicates test for the parameter has not been performed.
   *    - filename: using filename (first parameter).
   *    - fid: using fid (file id, second parameter).
   *    - failed_items_key: a list of keys that will reference file attributes that caused the validation to fail.
   *     - filename: filename or the value as provided to the filename parameter.
   *     - fid: the file id number.
   *     - mime: the file MIME type.
   *     - extension: the file extension. 
   */
  public function provideFileForDataFileValidator() {
        
    return [
      // #0: Test an invalid empty string value to the filename parameter.
      [
        'invalid filename parameter',
        'invalid-filename-parameter',
        [
          'filename' => [
            'case' => 'Filename is empty string',
            'valid' => FALSE,
          ],
          'fid' => [],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ],
      
      // #1: Test an invalid zero value to the fid parameter.
      [
        'invalid fid parameter',
        'invalid-fid-parameter',
        [
          'filename' => [],
          'fid' => [
            'case' => 'Invalid file id number',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ],
  
      // #2: Test non-existent filename.
      [
        'filename does not exist',
        'non-existent-filename',
        [
          'filename' => [
            'case' => 'Filename or file id failed to load a file object',
            'valid' => FALSE,
          ],
          'fid' => [],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ],

       // #3: Test non-existent file id number.
       [
        'file id number does not exist',
        'non-existent-fid',
        [
          'filename' => [],
          'fid' => [
            'case' => 'Filename or file id failed to load a file object',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ],

      // #4: Test unmanaged filename - file does not exist in file system.
      [
        'unmanaged file',
        'file-unmanaged',
        [
          'filename' => [
            'case' => 'Filename or file id failed to load a file object',
            'valid' => FALSE,
          ],
          'fid' => [],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ],
      
      // #5: Test an empty file.
      [
        'file is empty',
        'file-empty',
        [
          'filename' => [
            'case' => 'The file has no data and is an empty file',
            'valid' => FALSE,
          ],
          'fid' => [
            'case' => 'The file has no data and is an empty file',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ],
      
      // #6: Test file that is not the right MIME type.
      [
        'incorrect mime type',
        'file-image',
        [
          'filename' => [
            'case' => 'Unsupported file mime type and mismatched extension',
            'valid' => FALSE,
          ],
          'fid' => [
            'case' => 'Unsupported file mime type and mismatched extension',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'mime',
            'extension'
          ]
        ]
      ],

      // #7. Test file of a type pretending to be another.
      [
        'pretentious file',
        'file-pretend',
        [
          'filename' => [
            'case' => 'Unsupported file MIME type',
            'valid' => FALSE,
          ],
          'fid' => [
            'case' => 'Unsupported file MIME type',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'mime',
            'extension'
          ]
        ]
      ],
      
      // #8. Test a valid file - primary type (tsv).
      [
        'valid tsv file',
        'file-valid',
        [
          'filename' => [
            'case' => 'Data file is valid',
            'valid' => TRUE,
          ],
          'fid' => [
            'case' => 'Data file is valid',
            'valid' => TRUE,
          ],
          'failed_items_key' => []
        ],
      ],

      // #9. Test a valid file - alternative type (txt).
      [
        'valid txt file',
        'file-alternative',
        [
          'filename' => [
            'case' => 'Data file is valid',
            'valid' => TRUE,
          ],
          'fid' => [
            'case' => 'Data file is valid',
            'valid' => TRUE,
          ],
          'failed_items_key' => []
        ],
      ],

      // #10: Test a locked file - cannot read a valid file.
      [
        'file is locked',
        'file-locked',
        [
          'filename' => [
            'case' => 'The file cannot be opened',
            'valid' => FALSE,
          ],
          'fid' => [
            'case' => 'The file cannot be opened',
            'valid' => FALSE,
          ],
          'failed_items_key' => [
            'filename',
            'fid'
          ]
        ]
      ]
    ];
  }

  /**
   * Test data file input validator.
   * 
   * @dataProvider provideFileForDataFileValidator
   */
  public function testDataFileInput($scenario, $test_file_key, $expected) {
    $file_input = $this->test_files[ $test_file_key ];
    
    foreach($file_input['test_param'] as $input_type => $test_param) {
      list($filename, $fid) = $test_param;
      $validation_status = $this->validator_instance->validateFile($filename, $fid);

      // Determine the failed items: if the validation passed, the failed item is an empty array,
      // otherwise, create the item key specified by the test scenario and set the value to
      // the value of the same key in the parameter array test file properties.
      $failed_items = [];
      foreach($expected['failed_items_key'] as $item) {
        $failed_items[ $item ] = $file_input['test_file'][ $item ];
      }
      
      $expected[ $input_type ]['failedItems'] = ($validation_status['valid']) ? [] : $failed_items;

      foreach($validation_status as $key => $value) {
        $this->assertEquals($value, $expected[ $input_type ][ $key ],
          'The validation status key ' . $key . ' with the parameter ' . $input_type . ', does not match expected in scenario: ' . $scenario);
      }
    }
  }
}