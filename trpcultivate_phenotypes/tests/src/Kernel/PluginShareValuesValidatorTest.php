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
    [
      'Trait Name' => 'TRAIT ABC',
      'Trait Description' => 'Trait ABC description',
      'Method Short Name' => 'METHOD-ABC',
      'Collection Method' => 'Pull from the ground and measure',
      'Unit' => 'A_unit',
      'Type' => 'Quantitative'
    ],
    [
      'Trait Name' => 'TRAIT EFG',
      'Trait Description' => 'Trait EFG description',
      'Method Short Name' => 'METHOD-EFG',
      'Collection Method' => 'Measure on the 4th week',
      'Unit' => 'B_unit',
      'Type' => 'Qualitative'
    ]
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

    // Set ontology.term: genus and trait, method and unit relationship to null (id: 1).
    // This is used as type_id when creating relationship between a project and genus.
    $this->config->set('trpcultivate.phenotypes.ontology.terms.genus', 1);
    // This is used as type_id when relating trait, method and unit.
    $this->config->set('trpcultivate.phenotypes.ontology.terms.method', 1);
    $this->config->set('trpcultivate.phenotypes.ontology.terms.unit', 1);
    $this->config->set('trpcultivate.phenotypes.ontology.terms.unit_to_method_relationship_type', 1);
    $this->config->set('trpcultivate.phenotypes.ontology.terms.method_to_trait_relationship_type', 1);
    // This is used to set the unit data type.
    $this->config->set('trpcultivate.phenotypes.ontology.terms.additional_type', 1);
    $this->config->save();

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
      'trait' => 1,
      'unit'   => 1,
      'method'  => 1,
      'database' => 1,
      'crop_ontology' => 1
    ];

    // Create germplasm/stock.
    // Stock type is set to null (id: 1).
    $u = uniqid();
    $this->stock['name'] = 'Stock-' . $u;
    $this->stock['uniquename'] = 'KPGERM-' . $u;
    $this->stock['type_id'] = 1;
    $this->stock['organism_id'] = $organism;

    $this->chado->insert('1:stock')
      ->fields($this->stock)
      ->execute();    

    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $genus_ontology_config);

    // Create trait, method and unit (plus all relationships).
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');
    // Set the genus the trait service will restrict insert and select query.
    $this->service_traits->setTraitGenus($genus);
    $this->service_traits->insertTrait($this->trait_method_unit[0]);
    $this->service_traits->insertTrait($this->trait_method_unit[1]);

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
    $trait = $this->service_traits->getTrait(['name' => $this->trait_method_unit[0]['Trait Name']]);
    $this->assertEquals($trait->name, $this->trait_method_unit[0]['Trait Name'], 'Test trait not created');

    $trait = $this->service_traits->getTrait(['name' => $this->trait_method_unit[1]['Trait Name']]);
    $this->assertEquals($trait->name, $this->trait_method_unit[1]['Trait Name'], 'Test trait not created');

    // Test trait method.
    $method = $this->service_traits->getTraitMethod(['name' => $this->trait_method_unit[0]['Trait Name']]);
    $this->assertEquals($method[0]->name, $this->trait_method_unit[0]['Method Short Name'], 'Test method not created');
    $method1_id = $method[0]->cvterm_id;
    
    $method = $this->service_traits->getTraitMethod(['name' => $this->trait_method_unit[1]['Trait Name']]);
    $this->assertEquals($method[0]->name, $this->trait_method_unit[1]['Method Short Name'], 'Test method not created');
    $method2_id = $method[0]->cvterm_id;
  }

  /**
   * Test Importer Share Values Plugin Validator.
   */
  public function testScopePluginValidator() {
    $scope = 'PHENOSHARE IMPORT VALUES';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // Create test file. In the the setup test file only contained the headers.
    $file_content = [

    ]; 






    // PASS:
    $status = 'pass';


    // FAIL:
    $status = 'fail';


    // TODO:
    $status = 'todo';
  }
}
