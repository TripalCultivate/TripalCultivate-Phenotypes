<?php

/**
 * @file
 * Kernel test of Values Validator Plugin.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Test Tripal Cultivate Phenotypes Values Validator Plugin.
  *
  * @group trpcultivate_phenotypes
  */
class PluginValuesValidatorTest extends ChadoTestKernelBase {
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
   * Template.
   * Test Phenotypes-Share Values Plugin Validator.
   */
  public function testShareImportValuePluginValidator() {
    $scope = 'PHENOSHARE IMPORT VALUES';
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
