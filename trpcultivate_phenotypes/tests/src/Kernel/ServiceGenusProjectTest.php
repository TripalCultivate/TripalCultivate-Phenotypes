<?php

/**
 * @file
 * Kernel test of Genus Project service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Symfony\Component\HttpFoundation\Request;
use Drupal\trpcultivate_phenotypes\Controller\TripalCultivatePhenotypesProjectGenusController;

/**
 * Test Tripal Cultivate Phenotypes Genus Project service.
 *
 * @group trpcultivate_phenotypes
 */
class ServiceGenusProjectTest extends ChadoTestKernelBase {
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
   * Modules to enable.
   */
  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * Configuration
   * 
   * @var config_entity
   */
  private $config;
  
  /**
   * Contains inserted records.
   *
   * @var array.
   */
  private $ins = [
    'project_id' => 0,
    'genus' => 0,
    'genus_id' => 0,
    'projectprop_id' => 0,
    'second_genus' => 0,
    'second_genus_id' => 0
  ];

  /**
   * Project that is set with a genus.
   */
  private $project;

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
    $this->project = $project;
    $project_id = $this->chado->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => $project . ' : Description'   
      ])
      ->execute();

    $this->ins['project_id'] = $project_id;
    
    $genus = 'Wild Genus ' . uniqid();
    $genus_id = $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
        'type_id' => 1 
      ])
      ->execute();

    $this->ins['genus_id'] = $genus_id;
    $this->ins['genus'] = $genus;

    $prop_id = $this->chado->insert('1:projectprop')
      ->fields([
        'project_id' => $project_id,
        'type_id' => 1,
        'value' => $genus 
      ])
      ->execute();

    $this->ins['projectprop_id'] = $prop_id;

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

    // Insert a secondary genus to be used to test when setting up a genus
    // where replace existing genus is enabled.
    $genus = 'Cultivated Genus ' . uniqid();
    $genus_id = $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Cultivated Species',
        'type_id' => 1 
      ])
      ->execute();
    
    // Configure this other genus.
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

    $this->ins['second_genus_id'] = $genus_id;
    $this->ins['second_genus'] = $genus;

    // Save all genus ontology config.
    $this->config->save();

    // Term service.
    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_project');
  }

  public function testGenusProjectService() {
    // Class created.
    $this->assertNotNull($this->service, 'Term service not created.');

    // Assert all relevant records were created in setup.
    foreach($this->ins as $key => $value) {
      $this->assertNotNull($value, $key . ' Test record not created.');
    }

    // Test getGenusOfProject().
    $genus_project = $this->service->getGenusOfProject($this->ins['project_id']);
    
    $this->assertNotNull($genus_project, 'Genus of a project returned null: project_id - ' . $this->ins['project_id']);
    $this->assertEquals($genus_project['genus'], $this->ins['genus'], 'Genus does not match expected genus: ' . $genus_project['genus']);

    // Test setGenusToProject().
    // From Wild genus to cultivated genus. Replace genus flag enabled.
    $set = $this->service->setGenusToProject($this->ins['project_id'], $this->ins['second_genus'], TRUE);
    $this->assertTrue($set, 'Change of genus failed: project_id - ' . $this->ins['project_id'] . ' to ' . $this->ins['second_genus']);
    $new_genus = $this->service->getGenusOfProject($this->ins['project_id']);
    $this->assertEquals($new_genus['genus'], $this->ins['second_genus'], 'Genus does not match expected genus: ' . $this->ins['second_genus']);

    // Does nothing. replace option is default to false.
    // This will throw an error since target genus is not configured.
    // $set = $this->service->setGenusToProject($this->ins['project_id'], 'REPLACEMENT GENUS');
    // $this->assertTrue($set, 'Change of genus failed: project_id - ' . $this->ins['project_id'] . ' to ' . $genus);

    // No relationship yet. Create a relationship in projectprop table.
    // Create a new project.
    $project = 'New Project - ' . uniqid();
    $project_id = $this->chado->insert('1:project')
    ->fields([
      'name' => $project,
      'description' => $project . ' : Description'
    ])
    ->execute();

    $set = $this->service->setGenusToProject($project_id, $this->ins['second_genus']);
    $this->assertTrue($set, 'Change of genus failed: project_id - ' . $project_id . ' to ' . $this->ins['second_genus']);

    $new_project_genus = $this->service->getGenusOfProject($project_id);
    $this->assertEquals($new_project_genus['genus'], $this->ins['second_genus'], 'Genus does not match expected genus: ' . $this->ins['second_genus']);
  
    // Test method inserted correct record into projectprop table.
    $projectprop = $this->chado->query("
      SELECT * FROM {1:projectprop} 
      WHERE project_id = :project_id AND type_id = :term_genus AND value = :value_genus LIMIT 1
    ", [':project_id' => $project_id, ':term_genus' => 1, ':value_genus' => $new_project_genus['genus']])
      ->fetchObject();
    
    $this->assertNotNull($projectprop->projectprop_id, 'Method setGenusToProject failed to create a record in projectprop table.');
    $this->assertEquals($new_project_genus['genus'], $projectprop->value, 'Genus set value in projectprop does not match expected genus.');
    $this->assertEquals($project_id, $projectprop->project_id, 'Project id set value in projectprop does not match expected project id.');
  }

  /**
   * Test AJAX request to fetch project genus.
   * 
   * @see Importer behavior - auto-select project genus
   */
  public function testControllerGetProjectGenus() {
    // Controller to handle ajax request. This controller does not append parameter values
    // into the query string, instead is uses a POST method to attach value to the request.
    $controller = new TripalCultivatePhenotypesProjectGenusController();
    
    // Route that points to this controller.
    $url_generator = \Drupal::service('url_generator');
    $route = $url_generator->generateFromRoute('trpcultivate_phenotypes.ajax_callback_get_project_genus', [], ['absolute' => TRUE]);
    
    // Create an AJAX request like in the form. This will mock the request POST value for project
    // that the controller will base the project parameter.
    $request = Request::create($route, 'POST', ['project' => $this->project]);
    // Save posted value in Drupal http request stack.
    \Drupal::requestStack()->push($request);
    
    // Get response.
    $response = $controller->getProjectGenus();
    $genus_response = $response->getContent();
    $this->assertEquals($this->ins['genus'], trim($genus_response, '"'));
  }
}
