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

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    $genus = 'Tripalus';
    // Create our organism and configure it.
    $organism_id = $this->connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (configured).');
    $this->test_genus['configured'] = $genus;
    $this->setOntologyConfig($this->test_genus['configured']);

    // Set terms configuration.
    $this->setTermConfig();

    
    // Create test files.
    $this->installEntitySchema('file');

    $test_file  = 'test_data_file';
    $dir_public = 'public://';
    
    $create_files = [
      // A valid file type, default type expected by the importer.
      'file-1' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      // A valid file type, an empty file.
      'file-2' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => '',
        'filesize' => 0
      ],
      // An alternative file type.
      'file-3' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      // Not valid file
      'file-4' => [
        'ext' => 'png',
        'mime' => 'image/png',
        'content' => ''
      ],
      // Pretend tsv file.
      'file-5' => [
        'ext' => 'tsv',
        'mime' => 'application/pdf',
        'file' => 'pdf.txt', // Can be found in the test Fixtures folder.
      ],
    ];

    // To create an actual empty file with 0 file size:
    // First create the file and write an empty string then create a file entity off this file.
    $empty_file = $dir_public . $test_file . 'file-2.' . $create_files['file-2']['ext'];
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

      // Write something on file with content key set to a string.
      if (!empty($prop['content'])) {
        $fileuri = $file->getFileUri();
        file_put_contents($fileuri, $prop['content']);
      }

      // If an existing file was specified then we can add that in here.
      if (!empty($prop['file'])) {
        $fileuri = $file->getFileUri();

        $path_to_fixtures = __DIR__ . '/../../Fixtures/';
        $full_path = $path_to_fixtures . $prop['file'];
        $this->assertFileIsReadable($full_path,
          "Unable to setup FILE ". $id . " because cannot access Fixture file at $full_path.");

        copy($full_path, $fileuri);
      }

      // Set file permissions if needed.
      if (!empty($prop['permissions'])) {
        $fileuri = $file->getFileUri();
        if ($prop['permissions'] == 'none') {
          chmod($fileuri, 0000);
        }
      }
    }

    $this->test_files =  $create_files;

    // Create a copy of test file file-1 make the copy unmanaged by Drupal File System.
    $unmanage_copy = File::load($this->test_files['file-1']['ID']);
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

    // Cannot load filename.
    $filename = $this->test_unmanaged_file;
    $validation_status = $instance->validateFile($filename, NULL);
    
    $this->assertEquals('Filename or file id failed to load a file object', $validation_status['case'],
      'File validator case title does not match expected title for unmanaged file.');
    $this->assertFalse($validation_status['valid'], 'A failed file must return a FALSE valid status.');
    $this->assertStringContainsString($filename, $validation_status['failedItems'], 'Failed file value is expected in failed items.');

    // Cannot load a non-existent file id.
    $file_id = 9999;
    $validation_status = $instance->validateFile('', $file_id);
    
    $this->assertEquals('Filename or file id failed to load a file object', $validation_status['case'],
      'File validator case title does not match expected title for unmanaged file.');
    $this->assertFalse($validation_status['valid'], 'A failed file must return a FALSE valid status.');
    $this->assertStringContainsString($file_id, $validation_status['failedItems'], 'Failed file value is expected in failed items.');
    
    //
  }
}