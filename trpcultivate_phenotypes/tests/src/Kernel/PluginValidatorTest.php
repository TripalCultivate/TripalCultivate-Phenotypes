<?php

/**
 * @file
 * Kernel test of Validator Plugin.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;

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
    'headers' => [],
    'skip' => 0
  ];
  
  /**
   * Modules to enable.
   */
  protected static $modules = [
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
