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
   * Term service.
   * 
   * @var object
   */
  protected $service;

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
   * Test genus and project.
   */
  private $test_records = [
    'genus' => '',
    'project' => ''
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

    $this->test_records['project'] = $project;

    $genus = 'Wild Genus ' . uniqid();
    $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
        'type_id' => 1 
      ])
      ->execute();
    
    $this->test_records['genus'] = $genus;  

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
  }

  public function testPluginValidator() {
    // Assert genus and project records created.
    foreach($this->test_records as $key => $value) {
      $this->assertNotNull($value, $key . ' Test record not created.');
    }

    // Create instance of the validator plugin - Project.
    $manager = \Drupal::service('plugin.manager.trpcultivate_validator');
    $plugins = $manager->getDefinitions();
    $plugin_definitions = array_values($plugins);
    
    $project = $this->test_records['project'];
    $genus = $this->test_records['genus'];
    $file = 1;
    $scope = 'PROJECT';

    $plugin_key = array_search($scope, array_column($plugin_definitions, 'validator_scope'));
    $validator = $plugin_definitions[ $plugin_key ]['id'];
          
    $instance = $manager->createInstance($validator);
    $instance->loadAssets($project, $genus, $file);
          
    // Perform Project Level validation.
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], 'pass');    

    // Test validator plugin with non-existent project.
    $instance->loadAssets('NON-Existent-Project', $genus, $file);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], 'fail');

    // Test validator with a project with incorrect genus.
    $instance->loadAssets($project, 'NON-Existent-Genus', $file);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], 'fail');
  }
}
