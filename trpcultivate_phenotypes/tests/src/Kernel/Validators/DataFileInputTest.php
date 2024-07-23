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
   * A genus that exists and is configured. 
   */
  protected $test_genus;

  /**
   * Test files.
   */
  protected $test_files;

  /**
   * Test unmanaged file.
   */
  protected $test_unmanaged_file;

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

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Create test files.
    $this->installEntitySchema('file');

    $test_file  = 'test_data_file';
    $dir_public = 'public://';
    
    $create_files = [
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
        'filesize' => 0
      ],
      // An alternative file type.
      'file-alternative' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      // Not valid file
      'file-image' => [
        'ext' => 'png',
        'mime' => 'image/png',
        'content' => '',
        'file' => 'png.png' // can be found in the test Fixtures folder.
      ],
      // Pretend tsv file.
      'file-pretend' => [
        'ext' => 'tsv',
        'mime' => 'application/pdf',
        'file' => 'pdf.txt', // Can be found in the test Fixtures folder.
      ],
    ];

    // To create an actual empty file with 0 file size:
    // First create the file and write an empty string then create a file entity off this file.
    $empty_file = $dir_public . $test_file . 'file-empty.' . $create_files['file-empty']['ext'];
    file_put_contents($empty_file, '');
    
    foreach($create_files as $id => $prop) {
      $filename = $test_file . $id . '.' . $prop['ext'];

      $file = File::create([
        'filename' => $filename,
        'filemime' => $prop['mime'],
        'uri' => $dir_public . $filename,
        'status' => 0,
      ]);

      if (isset($prop['filesize'])) {
        // This is an empty file and to ensure the size is
        // as expected of an empty file = 0;
        $file->setSize(0);
      }

      $file->save();
      // Save id created.
      $create_files[ $id ]['ID'] = $file->id();
      // File uri.
      $fileuri = $file->getFileUri();
      $create_files[ $id ]['URI'] = $fileuri;

      // Write something on file with content key set to a string.
      if (!empty($prop['content'])) {
        file_put_contents($fileuri, $prop['content']);
      }

      // If an existing file was specified then we can add that in here.
      if (!empty($prop['file'])) {
        $path_to_fixtures = __DIR__ . '/../../Fixtures/';
        $full_path = $path_to_fixtures . $prop['file'];
        $this->assertFileIsReadable($full_path,
          "Unable to setup FILE ". $id . " because cannot access Fixture file at $full_path.");

        copy($full_path, $fileuri);
      }

      // Set file permissions if needed.
      if (!empty($prop['permissions'])) {
        if ($prop['permissions'] == 'none') {
          chmod($fileuri, 0000);
        }
      }
    }

    $this->test_files =  $create_files;

    // Create a copy of test file file-1 make the copy unmanaged by Drupal File System.
    $unmanage_copy = File::load($this->test_files['file-valid']['ID']);
    $unmanaged_uri = $unmanage_copy->getFileUri();
    $this->test_unmanaged_file = str_replace($test_file, 'unmanaged_' . $test_file, $unmanaged_uri); 

    copy($unmanaged_uri, $this->test_unmanaged_file);
  }

  /**
   * Test data file input.
   */
  public function testDataFileInput() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_valid_data_file';
    $instance = $this->plugin_manager->createInstance($validator_id);
    
    // Test items that will throw exception:
    // 1. Passing an invalid file path
    // 2. File id is 0
    
    // Invalid path.
    $invalid_file_path = '...C:nodir/Users/Tripal/data-file.tsv/nodir';
    
    $exception_caught  = FALSE;
    $exception_message = ''; 
    try {
      $instance->validateFile($invalid_file_path, NULL);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing invalid file path to data file validator.');
    $this->assertStringContainsString('File path provided is not a valid path', $exception_message, 
      'Expected exception message does not match message when passing invalid file path to data file validator.');

    
    // Invalid file id.
    $invalid_file_id = 0;
    
    $exception_caught  = FALSE;
    $exception_message = ''; 
    try {
      $instance->validateFile('', $invalid_file_id);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing invalid file id to data file validator.');
    $this->assertStringContainsString('Drupal File System Id Number cannot be 0 or negative values', $exception_message, 
      'Expected exception message does not match message when passing invalid file id to data file validator.');

    
    // Other tests:
    // Each test will test that validateFile generated the correct case, valid status and failed item.
    // Failed item is the failed file path or file id value. Failed information is contained in the case.

    // Cannot load filename - unmanaged file (without filed id).
    $filename = $this->test_unmanaged_file;
    $validation_status = $instance->validateFile($filename, NULL);
    
    $this->assertEquals('Filename or file id failed to load a file object', $validation_status['case'],
      'File validator case title does not match expected title for unmanaged file.');
    $this->assertFalse($validation_status['valid'], 'A failed file must return a FALSE valid status.');
    $this->assertStringContainsString($filename, $validation_status['failedItems'], 'Failed file value is expected in failed items.');
    
    // Cannot load a non-existent file id.
    $file_id = 999;
    $validation_status = $instance->validateFile('', $file_id);
    
    $this->assertEquals('Filename or file id failed to load a file object', $validation_status['case'],
      'File validator case title does not match expected title for unmanaged file.');
    $this->assertFalse($validation_status['valid'], 'A failed file must return a FALSE valid status.');
    $this->assertStringContainsString($file_id, $validation_status['failedItems'], 'Failed file value is expected in failed items.');


    // Test for both cases where the parameter is file uri and file id.
    $parameter = ['is_uri', 'is_id'];

    foreach($parameter as $p) {
      // An empty file.
      if ($p == 'is_uri') {
        $param = $this->test_files['file-empty']['URI'];
        $validation_status = $instance->validateFile($param, NULL);
      }
      else {
        $param = $this->test_files['file-empty']['ID'];
        $validation_status = $instance->validateFile('', $param);
      }

      $this->assertEquals('The file uploaded has no data and is an empty file', $validation_status['case'],
        'File validator case title does not match expected title for empty file.');
      $this->assertFalse($validation_status['valid'], 'A failed file must return a FALSE valid status.');
      $this->assertStringContainsString($param, $validation_status['failedItems'], 'Failed file value is expected in failed items.');

      // @TODO: could not test cannot open file case as there seems to be
      // no file that cannot be opened (tested png and zip file).


      // Incorrect file type/mime - a png file.
      if ($p == 'is_uri') {
        $param = $this->test_files['file-image']['URI'];
        $validation_status = $instance->validateFile($param, NULL);
      }
      else {
        $param = $this->test_files['file-image']['ID'];
        $validation_status = $instance->validateFile('', $param);
      }
      
      $this->assertEquals('The file uploaded is not prescribed file type', $validation_status['case'],
        'File validator case title does not match expected title for incorrect file type.');
      $this->assertFalse($validation_status['valid'], 'A failed file must return a FALSE valid status.');
      $this->assertStringContainsString($param, $validation_status['failedItems'], 'Failed file value is expected in failed items.');
    

      // Test a valid tsv file.
      if ($p == 'is_uri') {
        $param = $this->test_files['file-valid']['URI'];
        $validation_status = $instance->validateFile($param, NULL);
      }
      else {
        $param = $this->test_files['file-valid']['ID'];
        $validation_status = $instance->validateFile('', $param);
      }
      
      $this->assertEquals('Data file is valid', $validation_status['case'],
        'File validator case title does not match expected title for incorrect file type.');
      $this->assertTrue($validation_status['valid'], 'A failed file must return a TRUE valid status.');
      $this->assertEmpty($validation_status['failedItems'], 'A valid file does not return a failed item value.');


      // Test a valid alternative txt file.
      if ($p == 'is_uri') {
        $param = $this->test_files['file-alternative']['URI'];
        $validation_status = $instance->validateFile($param, NULL);
      }
      else {
        $param = $this->test_files['file-alternative']['ID'];
        $validation_status = $instance->validateFile('', $param);
      }
      
      $this->assertEquals('Data file is valid', $validation_status['case'],
        'File validator case title does not match expected title for incorrect file type.');
      $this->assertTrue($validation_status['valid'], 'A failed file must return a TRUE valid status.');
      $this->assertEmpty($validation_status['failedItems'], 'A valid file does not return a failed item value.');
    }
  }

  /**
   * Validate file for tab separated content.
   */
  public function testTsvDataFileInput() {
    // @TODO: update validateRow() parameter - the second parameter has been
    // marked deprecated.

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_valid_tsv_data_file';
    $instance = $this->plugin_manager->createInstance($validator_id);
    
    // Test a valid tsv header row but the number of items does not
    // match expected number of items.
    $row_values = implode("\t", ['Header 1', 'Header 2', 'Header 3', 'Header 4', 'Header 5']);
    $validation_status = $instance->validateRow($row_values, []);

    $this->assertEquals('Data file header row is not a tab-separated values', $validation_status['case'],
      'TSV File validator case title does not match expected title for failed tsv row check.');
    $this->assertFalse($validation_status['valid'], 'A failed file header row must return a FALSE valid status.');
    $this->assertStringContainsString($row_values, $validation_status['failedItems'], 'Failed header row value is expected in failed items.');

    // Test an invalid string (not a tsv).
    $row_values = implode(',', ['Header 1', 'Header 2', 'Header 3', 'Header 4', 'Header 5', 'Header 6', 'Header 7']);
    $validation_status = $instance->validateRow($row_values, []);

    $this->assertEquals('Data file header row is not a tab-separated values', $validation_status['case'],
      'TSV File validator case title does not match expected title for failed tsv row check.');
    $this->assertFalse($validation_status['valid'], 'A failed file header row must return a FALSE valid status.');
    $this->assertStringContainsString($row_values, $validation_status['failedItems'], 'Failed header row value is expected in failed items.');

    // Valid header row.
    $row_values = implode("\t", ['Header 1', 'Header 2', 'Header 3', 'Header 4', 'Header 5', 'Header 6', 'Header 7']);
    $validation_status = $instance->validateRow($row_values, []);

    $this->assertEquals('Data file content is valid tab-separated values (tsv)', $validation_status['case'],
      'TSV File validator case title does not match expected title for valid tsv row check.');
    $this->assertTrue($validation_status['valid'], 'A valid file header row must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid file header row does not return a failed item value.');
  }
}