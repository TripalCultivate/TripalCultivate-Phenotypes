<?php

/**
 * @file
 * Kernel test of Validator Plugin.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Test Tripal Cultivate Phenotypes Validator Plugin.
  *
  * @group trpcultivate_phenotypes
  */
class PluginValidatorTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $chado;

  /**
   * Configuration
   * 
   * @var config_entity
   */
  private $config;

  /**
   * Import assets.
   */
  private $assets = [
    'project' => '',
    'genus' => '',
    'file' => 0,
    'headers' => ['Header 1', 'Header 2', 'Header 3'],
    'skip' => 0
  ];

  /**
   * Test file ids.
   */
  private $test_files;
  
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
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Set ontology.term: genus to null (id: 1).
    // This is used as type_id when creating relationship between a project and genus.
    $this->config->set('trpcultivate.phenotypes.ontology.terms.genus', 1);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    // Prepare by adding test records to genus, project and projectproperty
    // to relate a genus to a project.
    $project = 'Project - ' . uniqid();
    $project_id = $this->chado->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => $project . ' : Description'   
      ])
      ->execute();

    $this->assets['project'] = $project;

    $genus = 'Wild Genus ' . uniqid();
    $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
        'type_id' => 1 
      ])
      ->execute();
    
    $this->assets['genus'] = $genus;  

    $this->chado->insert('1:projectprop')
      ->fields([
        'project_id' => $project_id,
        'type_id' => 1,
        'value' => $genus 
      ])
      ->execute();  

    // Create Genus Ontology configuration. 
    // All configuration and database value to null (id: 1).
    $config_name = str_replace(' ', '_', strtolower($genus));
    $genus_ontology_config = [
      'trait' => 1,
      'unit'   => 1,
      'method'  => 1,
      'database' => 1,
      'crop_ontology' => 1
    ];

    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $genus_ontology_config);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  
    // Test files.

    // File schema for FILE validator.
    $this->installEntitySchema('file');

    // Create a test file.
    $test_file  = 'test_data_file';
    $dir_public = 'public://';

    // Column headers - in the importer this is the headers property.
    $column_headers = implode("\t", $this->assets['headers']);

    // Prepare test file for the following extensions.
    // Each extension is set to file id 0 until created.
    $create_files = [
      // A valid file type, default type expected by the importer.
      'file-1' => [
        'ext' => 'tsv', 
        'mime' => 'text/tab-separated-values',
        'content' => 'Header 1  Header 2  Header 3'
      ],
      // A valid file type, an empty file.
      'file-2' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => ''
      ],
      // An alternative file type.
      'file-3' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => 'Header 4  Header  5 HEADER 6'
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
        'content' => ''
      ],
      // Test file with the correct headers.
      'file-6' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => $column_headers
      ],
    ];

    foreach($create_files as $id => $prop) {
      $filename = $test_file . '.' . $prop['ext'];

      $file = File::create([
        'filename' => $filename,
        'filemime' => $prop['mime'],
        'uri' => $dir_public . $filename,
        'status' => 0,
      ]);

      $file->save();
      // Save id created.
      $create_files[ $id ]['ID'] = $file->id();

      // Write something on file with content key set to a string.
      if (!empty($prop['content'])) {      
        $fileuri = $file->getFileUri();
        file_put_contents($fileuri, $prop['content']);
      }
    }

    $this->test_files =  $create_files;
  }
  
  /**
   * Test test records were created.
   */
  public function testRecordsCreated() {
    // Test project.
    $sql_project = "SELECT name FROM {1:project} WHERE name = :name LIMIT 1";
    $project = $this->chado->query($sql_project, [':name' => $this->assets['project']])
      ->fetchField();

    $this->assertNotNull($project, 'Project test record not created.');
    
    // Test genus.
    $sql_genus = "SELECT genus FROM {1:organism} WHERE genus = :genus LIMIT 1";
    $genus = $this->chado->query($sql_genus, [':genus' => $this->assets['genus']])
      ->fetchField();

    $this->assertNotNull($genus, 'Genus test record not created.');
  }

  /**
   * Test Project Plugin Validator.
   */
  public function testProjectPluginValidator() {
    $scope = 'PROJECT';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // Test a valid project - exists.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Test validator with a project (exists) without genus configured.
    // This will allow (pass) so long as project has no genus set and user
    // can set the genus so further in the importer the project-genus can be created.
    $project_no_genus = 'Project No Genus';
    $this->chado->insert('1:project')
      ->fields([
        'name' => $project_no_genus,
        'description' => $project_no_genus . ' : Description'   
      ])
      ->execute();

    $instance->loadAssets($project_no_genus, $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';

    // Test empty value.
    $instance->loadAssets('', $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Test validator plugin with non-existent project.
    $instance->loadAssets('NON-Existent-Project', $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    // Test skip flag to skip this test - set to upcoming validation step.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Test Genus Plugin Validator.
   */
  public function testGenusPluginValidator() {
    $scope = 'GENUS';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // Test project exits and has a genus (active genus).
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Check genus is an active genus when not paired with a project.
    $instance->loadAssets(null, $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';
    
    // Test empty value.
    $instance->loadAssets($assets['project'], '', $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Incorrect genus picked for a project that has genus set (mismatch).
    $instance->loadAssets($assets['project'], 'NOT THE GENUS', $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    // Test skip flag to skip this test - set to upcoming validation step.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Test Data file Plugin Validator.
   */
  public function testDataFilePluginValidator() {
    $scope = 'FILE';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // File is tsv, not empty and can be read.
    $file_id = $this->test_files['file-1']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File is txt (alternative file type), not empty and can be read.
    $file_id = $this->test_files['file-3']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';

    // File is tsv, can be read but is an empty file.
    // Could not test empty file, file size is still greater than 0.
    $file_id = $this->test_files['file-2']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    // $this->assertEquals($validation[ $scope ]['status'], $status);

    // File is pdf but pretending to be tsv.
    $file_id = $this->test_files['file-5']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Not a valid file. Image/PNG.
    $file_id = $this->test_files['file-4']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Test Headers Plugin Validator.
   */
  public function testColumnHeaderPluginValidator() {
    $scope = 'HEADERS';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // File headers match the expected headers.
    $file_id = $this->test_files['file-6']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';
    
    // Change the contents of the tsv_file so the headers do not match the headers asset;
    $file = File::load($file_id);
    $file_uri = $file->getFileUri();
    file_put_contents($file_uri, 'NOT THE HEADERS EXPECTED');
    
    // File headers do not match the expected headers - Extra Headers.
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File headers do not match the expected headers - Less/Missing Headers.
    unset($assets['headers'][2]); // Removes Header 3.
    file_put_contents($file_uri, $assets['headers']);

    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Header row is missing.
    file_put_contents($file_uri, '');

    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    // Test skip flag to skip this test - set to upcoming validation step.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Test TRAIT IMPORT VALUES Plugin Validator.
   */
  public function testTraitImportValuePluginValidator() {
    $scope = 'TRAIT IMPORT VALUES';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // Write expected headers into the file and a sample data for every column.
    $file_id = $this->test_files['file-6']['ID'];
    $file = File::load($file_id);
    $file_uri = $file->getFileUri();
    
    // Trait importer required column headers.
    $assets_header = [
      'Trait Name',
      'Trait Description',
      'Method Short Name',
      'Collection Method',
      'Unit',
      'Type'
    ];

    $values = [
      'Trait 1',
      '"Trait 1 Description"',
      'method-1',
      '"Pull from the ground"',  
      'cm',
      'Quantitative'
    ];

    $test_data = implode("\t", $assets_header) . "\n" . implode("\t", $values);
    file_put_contents($file_uri, $test_data);

    // PASS:
    $status = 'pass';

    // All columns present, all columns have value and headers match the header array.
    $instance->loadAssets(0, $assets['genus'], $file_id, $assets_header, $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    
    // FAIL:
    $status = 'fail';

    // Empty column.
    $values = [
      'Trait 1',
      '',
      '',  
      '"Trait 1 First Method"',
      '',
      'Quantitative'
    ];

    $test_data = implode("\t", $assets_header) . "\n" . implode("\t", $values);
    file_put_contents($file_uri, $test_data);

    $instance->loadAssets(0, $assets['genus'], $file_id, $assets_header, $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Unexpected Type value column.
    $values = [
      'Trait 1',
      '"Trait 1 Description"',
      'method-1',
      '"Pull from the ground"',  
      'cm',
      'Collective' // Quantitative or Qualitative only.
    ];

    $test_data = implode("\t", $assets_header) . "\n" . implode("\t", $values);
    file_put_contents($file_uri, $test_data);

    $instance->loadAssets(0, $assets['genus'], $file_id, $assets_header, $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Duplicate trait (Trait Name + Method Short Name + Unit).
    $values = [
      'Trait 1',
      '"Trait 1 Description"',
      'method-1',
      '"Pull from the ground"',  
      'cm',
      'Qualitative'
    ];

    $test_data = implode("\t", $assets_header) . "\n" . implode("\t", $values) . "\n" . implode("\t", $values);
    file_put_contents($file_uri, $test_data);

    $instance->loadAssets(0, $assets['genus'], $file_id, $assets_header, $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    // Test skip flag to skip this test - set to upcoming validation step.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Template.
   * Test SCOPE Plugin Validator.
   *//*
  public function testScopePluginValidator() {
    $scope = 'SCOPE';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

     // PASS:
     $status = 'pass';


     // FAIL:
     $status = 'fail';


     // TODO:
     $status = 'todo';
  }
  */
}
