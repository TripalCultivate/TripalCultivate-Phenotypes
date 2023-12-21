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
class PluginShareValuesValidatorTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * Traits service.
   */
  protected $service_traits;

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
    'headers' => [
      'Trait Name', 
      'Method Name', 
      'Unit',
      'Germplasm Accession',
      'Germplasm Name',
      'Year',
      'Location',
      'Replicate',
      'Value',
      'Data Collector'
    ],
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
   * Stock details.
   */
  private $stock = [
    'name' => '',
    'uniquename' => '',
  ];

  /**
   * Trait, method and unit.
   */
  private $trait_method_unit = [
    'Trait Name' => 'TRAIT ABC',
    'Trait Description' => 'Trait ABC description',
    'Method Short Name' => 'METHOD-ABC',
    'Collection Method' => 'Pull from the ground and measure',
    'Unit' => 'A_unit',
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

    // Null cv/db.
    $null = 1;
    // Phenotypes settings ontology term.
    $phenotypes_settings = 'trpcultivate.phenotypes.ontology.terms';

    // Set ontology.term: genus and trait, method and unit relationship to null (id: 1).
    // This is used as type_id when creating relationship between a project and genus.
    $this->config->set($phenotypes_settings . '.genus', $null);
    // This is used as type_id when relating trait, method and unit.
    $this->config->set($phenotypes_settings . '.method', $null);
    $this->config->set($phenotypes_settings . '.unit', $null);
    $this->config->set($phenotypes_settings . '.unit_to_method_relationship_type', $null);
    $this->config->set($phenotypes_settings . '.method_to_trait_relationship_type', $null);
    // This is used to set the unit data type.
    $this->config->set($phenotypes_settings . '.additional_type', $null);
    // THIS IS VERY IMPORTANT CONFIGURATION:
    // Default to not to allow new trait, method and unit to trigger validator.
    $this->config->set('trpcultivate.phenotypes.ontology.allownew', TRUE);

    $this->config->save();

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    // Prepare by adding test records to genus, project and projectproperty
    // to relate a genus to a project, trait, method, unit and relationships.
    $u_id = uniqid();

    $project = 'Project - ' . $u_id;
    $project_id = $this->chado->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => $project . ' : Description'   
      ])
      ->execute();

    $this->assets['project'] = $project;

    $genus = 'Wild Genus ' . $u_id;
    $organism = $this->chado->insert('1:organism')
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
      'trait' => $null,
      'unit'   => $null,
      'method'  => $null,
      'database' => $null,
      'crop_ontology' => $null
    ];

    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $genus_ontology_config);

    // Create germplasm/stock. Stock type is set to null (id: 1).
    $this->stock['name'] = 'Stock-' . $u_id;
    $this->stock['uniquename'] = 'KPGERM-' . $u_id;
    
    $this->chado->insert('1:stock')
      ->fields([
        'name' =>  $this->stock['name'],
        'uniquename' => $this->stock['uniquename'],
        'organism_id' => $organism,
        'type_id' => $null
      ])
      ->execute();    

    // Create trait, method and unit (plus all relationships).
    $cv = 'null';
    $db = 'null';
    $values = $this->trait_method_unit;

    // Trait.
    $trait = [
      'id' => $db . $values['Trait Name'],
      'name' => $values['Trait Name'],
      'cv_name' => $cv,
      'definition' => $values['Trait Description']
    ];

    $i = chado_insert_cvterm($trait, [], NULL);
    $trait_id = $i->cvterm_id;
    
    // Method.
    $method = [
      'id' => $db . $values['Method Short Name'],
      'name' => $values['Method Short Name'],
      'cv_name' => $cv,
      'definition' => $values['Collection Method']
    ];

    $i = chado_insert_cvterm($method, [], NULL);
    $method_id = $i->cvterm_id;

    // Unit
    $unit = [
      'id' => $db . $values['Unit'],
      'name' => $values['Unit'],
      'cv_name' => $cv,
      'definition' => $values['Unit']
    ];

    $i = chado_insert_cvterm($unit, [], NULL);
    $unit_id = $i->cvterm_id;
    
    // Method-Trait:
    $this->chado->insert('1:cvterm_relationship')
      ->fields([
        'subject_id' => $trait_id,
        'type_id' => $null, 
        'object_id'  => $method_id,
      ])
      ->execute();

    // Method-Unit:
    $this->chado->insert('1:cvterm_relationship')
    ->fields([
      'subject_id' => $method_id,
      'type_id' => $null, 
      'object_id'  => $unit_id,
    ])
    ->execute();

    // Unit data type:
    $this->chado->insert('1:cvtermprop')
      ->fields([
        'cvterm_id' => $unit_id,
        'type_id' => $null, 
        'value'  => 'Quantitative'
      ])
      ->execute();
    
    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  
    // Test files.

    // File schema for FILE validator.
    $this->installEntitySchema('file');

    // Create a test file.
    $test_file  = 'test_data_file';
    $dir_public = 'public://';

    // Column headers - in the importer this is the headers property.
    $column_headers = implode("\t", $this->assets['headers']) . "\n";

    // Prepare test file for the following extensions.
    // Each extension is set to file id 0 until created.
    $create_files = [
      // A valid file type, default type expected by the importer.
      'file-1' => [
        'ext' => 'tsv', 
        'mime' => 'text/tab-separated-values',
        'content' => $column_headers
      ],
    ];

    foreach($create_files as $id => $prop) {
      $filename = $test_file . $id . '.' . $prop['ext'];

      $file = File::create([
        'filename' => $filename,
        'filemime' => $prop['mime'],
        'uri' => $dir_public . $filename,
        'status' => 0,
      ]);

      $file->save();
      // Save id created.
      $create_files[ $id ]['ID'] = $file->id();

      // Write the headers into file.
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
    /*
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

    // Test germplasm.
    $sql_stock = "SELECT stock_id FROM {1:stock} WHERE name = :stock LIMIT 1";
    $stock = $this->chado->query($sql_stock, [':stock' => $this->stock['name']])
      ->fetchField();
    $this->assertNotNull($stock, 'Stock test record not created.');

    // Test trait.
    $sql = "SELECT cvterm_id FROM {1:cvterm} WHERE name = :name AND cv_id = 1 LIMIT 1";
    $trait = $this->chado->query($sql, [':name' => $this->trait_method_unit['Trait Name']])
      ->fetchField();
    $this->assertNotNull($trait, 'Trait test record not created.');

    // Test method.
    $method = $this->chado->query($sql, [':name' => $this->trait_method_unit['Method Short Name']])
      ->fetchField();
    $this->assertNotNull($method, 'Method test record not created.');

    // Test unit.
    $unit = $this->chado->query($sql, [':name' => $this->trait_method_unit['Unit']])
      ->fetchField();
    $this->assertNotNull($unit, 'Unit test record not created.');


    // Test method-trait relationship.
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = 1 AND object_id = :o_id";
    
    $method_trait = $this->chado->query($sql, [':s_id' => $trait, 'o_id'  => $method])
      ->fetchField();
    $this->assertNotNull($method_trait, 'Method-Trait relationship test record not created.');

    // Test method-unit relationship.
    $method_unit = $this->chado->query($sql, [':s_id' => $method, 'o_id'  => $unit])
      ->fetchField();
    $this->assertNotNull($method_unit, 'Method-Unit relationship test record not created.');

    // Test unit data type.
    $sql_type = "SELECT cvtermprop_id FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = 1 LIMIT 1";
    $unit_type = $this->chado->query($sql_type, [':c_id' => $unit])
      ->fetchField();
    $this->assertNotNull($unit_type, 'Unit data type test record not created.');

    */
  }

  /**
   * Test Importer Share Values Plugin Validator.
   */
  public function testScopePluginValidator() {
    $scope = 'PHENOSHARE IMPORT VALUES';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // Create test file. In the the setup, test file only contained the headers.
    // Default unit type is quantitative.
    $file_id = $this->test_files['file-1']['ID'];
    $file = File::load($file_id);
    $file_uri = $file->getFileUri();

    // PASS:
    $status = 'pass';
    
    $raw_data = [
      $this->trait_method_unit['Trait Name'],
      $this->trait_method_unit['Method Short Name'],
      $this->trait_method_unit['Unit'],  
      $this->stock['uniquename'],
      $this->stock['name'],
      2020,
      'Canada',
      1,
      7,
      'ABC Institute'
    ];

    $test_data = $raw_data;
    $test_data = implode("\t", $test_data);
    file_put_contents($file_uri, $test_data, FILE_APPEND);
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // New Trait, Method and Unit where the ALLOW NEW CONFIG IS SET TO TRUE.
    $test_data = $raw_data;
    $test_data[0] = 'Unknown Trait';
    $test_data[1] = 'Unfamiliar Method';
    $test_data[2] = 'Undetermined Unit';

    $test_data = implode("\t", $test_data);
    file_put_contents($file_uri, $test_data, FILE_APPEND);
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
    

    // FAIL:
    $status = 'fail';

    // Each column is empty.
    $test_data = [
      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'
    ];
    // '', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'
    // 'A', '', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'
    // 'A', 'B', '', 'D', 'E', 'F', 'G', 'H', 'I', 'J'
    // and so on...

    $headers = implode("\t", $assets['headers']) . "\n";
    foreach($test_data as $i => $data) {
      $new_data = $test_data;
      $new_data[ $i ] = '';
      
      $empty_data = $headers . implode("\t", $new_data);
      file_put_contents($file_uri, $empty_data);

      $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
      $validation[ $scope ] = $instance->validate();
      $this->assertEquals($validation[ $scope ]['status'], $status);

      // Error message. There is only 1 data row in the file and error is always
      // at line 1. Column information is indicated in the error details.
      $error = 'Empty value @ line #1 Column(s): %s';
      $line_col = sprintf($error, $assets['headers'][ $i ]);
      $this->assertEquals($validation[ $scope ]['details']['#EMPTY'], $line_col);
    }

    // Unrecognized trait name, method name, unit and germplasm.
    // ALLOW NEW CONFIG IS SET TO FALSE - trait, method and unit must exists.
    $this->config->set('trpcultivate.phenotypes.ontology.allownew', FALSE);
    $this->config->save();

    $test_data = $raw_data;
    $switch_data = ['Unknown Trait', 'Unfamiliar Method', 'Undetermined Unit', 'Incognito Stock Accession', 'Obscure Stock Name'];
    
    foreach($switch_data as $i => $unrecognized) {
      $new_data = $test_data;
      $new_data[ $i ] = $unrecognized;

      $write_data = $headers . implode("\t", $new_data);
      file_put_contents($file_uri, $write_data);
      
      $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
      $validation[ $scope ] = $instance->validate();
      $this->assertEquals($validation[ $scope ]['status'], $status);
    }

    // Unrecognized Value - Quantitative but text provided.
    $test_data = $raw_data;
    $test_data[8] = 'TEXT VALUE';
    
    $write_data = $headers . implode("\t", $test_data);
    file_put_contents($file_uri, $write_data);

    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
    $this->assertEquals($validation[ $scope ]['details']['#UNEXPECTED'], 'Unexpected value @ line #1 Column(s): Value: TEXT VALUE (expected: Quantitative (number) value)');
    
    // Invalid year.
    // Year is less than 1900, a string, not 4 digit and a future year.
    $test_data = $raw_data;
    $invalid_years = [1899, 'YEAR', 20, 2050];

    foreach($invalid_years as $year) {
      $new_data = $test_data;
      // Index 5 of the headers is Year.
      $new_data[5] = $year;

      $write_data = $headers . implode("\t", $new_data);
      file_put_contents($file_uri, $write_data);
      
      $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
      $validation[ $scope ] = $instance->validate();      
      $this->assertEquals($validation[ $scope ]['status'], $status);
    }

    // Invalid replicate.
    $test_data = $raw_data;
    $invalid_replicate = [0, -1, 'a'];

    foreach($invalid_replicate as $rep) {
      $new_data = $test_data;
      // Index 7 of headers is Replicate.
      $new_data[7] = $rep;

      $write_data = $headers . implode("\t", $new_data);
      file_put_contents($file_uri, $write_data);
      
      $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
      $validation[ $scope ] = $instance->validate();      
      $this->assertEquals($validation[ $scope ]['status'], $status);
    }

    // TODO:
    $status = 'todo';
    
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();      
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }
}
