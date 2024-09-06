<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating data file to importer.
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
class DataFileInputTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Array input test file. Each element is keyed by short description and the value 
   * is an array with the following keys:
   *  - ext: file extension.
   *  - mime: MIME type.
   *  - content: file content to write into the file.
   *  - filesize: file size.
   *  - file: a file in the test fixtures directory to use.
   *  - file_id: file id number.
   *  - file_uri: file uri.
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
    $validator_id = 'trpcultivate_phenotypes_validator_valid_data_file';
    $this->validator_instance = \Drupal::service('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Create test files.
    $this->installEntitySchema('file');
    
    $test_file_scenario = [
      // A valid file type, default type expected by the importer.
      'file-valid' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      
      // A valid file type, an empty file.
      'file-empty' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => '',
        'filesize' => 0,
      ],
      
      // An alternative file type.
      'file-alternative' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      
      // Not valid file.
      'file-image' => [
        'ext' => 'png',
        'mime' => 'image/png',
        'content' => '',
        'file' => 'png.png' // Can be found in the test Fixtures folder.
      ],
      
      // Pretend tsv file.
      'file-pretend' => [
        'ext' => 'tsv',
        'mime' => 'application/pdf',
        'file' => 'pdf.txt' // Can be found in the test Fixtures folder.
      ],
    ];
    
    // Array to hold the file id and file uri of the generated files
    // that will be used as parameters to the validator.
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
      
      $file_uri = $file->getFileUri();
      $file_id  = $file->id();

      // Update test scenario file properties.
      
      // Set the file size.
      if (isset($file_properties['filesize'])) {
        $file->setSize($file_properties['filesize']);
      }
      
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
      
      // Save file id and file uri.
      $test_file_param[ $test_scenario ] = [
        'file_id' => $file_id,
        'file_uri' => $file_uri
      ];
    }
    
    
    // Create an unmanaged file copy of the valid test file scenario 
    // to use as input for validating a file without a file id (unmanaged file).
    $file_valid_uri = $test_file_param['file-valid']['file_uri'];
    $file_unmanaged_uri = str_replace('test_data_file', 'unmanaged_test_data_file', $file_valid_uri);
    
    $test_file_param['file-unmanaged'] = [
      'file_id' => 0,
      'file_uri' => $file_unmanaged_uri
    ];
    
    // Move a copy of the file and rename it using the new filename.
    copy($file_valid_uri, $file_unmanaged_uri);
    

    // Create test scenario for invalid parameters.
    $test_file_param['invalid-parameters'] = [
      'file_id' => 0,
      'file_uri' => '...C:nodir/Users/Tripal/data-file.tsv/nodir'
    ];
    
    // Create test scenario for non-existent file.
    $test_file_param['non-existent'] = [
      'file_id' => 999,
      'file_uri' => 'public://non-existent.tsv'
    ];

    // Set the property to all test file scenario.
    $this->test_files = $test_file_param;
  }

  /**
   * Data provider: provides test data file input.
   * 
   * @return array
   *   Each scenario/element is an array with the following values.
   *   
   *   - A string, human-readable short description of the test scenario.
   *   - Test scenario array key set in the $test_files property.
   *   - Expected validation response for using either parameters.
   *    - filename: using filename (first parameter).
   *    - fid: using fid (file id, second parameter).
   */
  public function provideFileForDataFileValidator() {
        
    return [
      // #0: Test invalid/non-existent file path as filename input and invalid fid of 0.
      [
        'invalid parameters',
        'invalid-parameters',
        [
          'filename' => [
            'case' => 'File path does not exist',
            'valid' => FALSE,
            'failedItems' => ['filename' => '...C:nodir/Users/Tripal/data-file.tsv/nodir']
          ],
          'fid' => [
            'case' => 'Invalid file id number',
            'valid' => FALSE,
            'failedItems' => ['file_id' => 0]
          ]
        ]
      ],

      // #2: Test non-existent file.
      [
        'file does not exist',
        'non-existent',
        [
          'filename' => [
            'case' => 'File path does not exist',
            'valid' => FALSE,
            'failedItems' => ['filename' => 'public://non-existent.tsv']
          ],
          'fid' => [
            'case' => 'Filename or file id failed to load a file object',
            'valid' => FALSE,
            'failedItems' => ['file_id' => 999]
          ]
        ]
      ],

      // #3: Test unmanaged file.
      [
        'unmanaged file',
        'file-unmanaged',
        [
          'filename' => [
            'case' => 'Filename or file id failed to load a file object',
            'valid' => FALSE,
            'failedItems' => ['filename' => 'public://unmanaged_test_data_file_file-valid.tsv']
          ],
          'fid' => [
            'case' => 'Invalid file id number',
            'valid' => FALSE,
            'failedItems' => ['file_id' => 0]
          ]
        ]
      ],
      
      // #4: Test an empty file.
    ];
  }

  /**
   * Test data file input validator.
   * 
   * @dataProvider provideFileForDataFileValidator
   */
  public function testDataFileInput($scenario, $test_file_key, $expected) {
    $file_input = $this->test_files[ $test_file_key ];
    
    // Test file scenario using the file uri as parameter to filename (first parameter).
    $validation_status = $this->validator_instance->validateFile($file_input['file_uri'], NULL);
    foreach($validation_status as $key => $value) {
      $this->assertEquals($value, $expected['filename'][ $key ], 'The validation status using parameter filename, does not match expected status for parameter filename in scenario: ' . $scenario);
    } 
    
    // Test file scenario using the file id as parameter to fid (second parameter).
    $validation_status = $this->validator_instance->validateFile('', $file_input['file_id']);
    foreach($validation_status as $key => $value) {
      $this->assertEquals($value, $expected['fid'][ $key ], 'The validation status using parameter fid, does not match expected status for parameter fid in scenario: ' . $scenario);
    }
  }
}