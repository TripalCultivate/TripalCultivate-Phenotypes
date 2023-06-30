<?php

/**
 * @file
 * Kernel test of Genus Project service.
 */

 namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

 use Drupal\KernelTests\KernelTestBase;
 use Drupal\tripal\Services\TripalLogger;

 /**
  * Test Tripal Cultivate Phenotypes Genus Project service.
  *
  * @group trpcultivate_phenotypes
  */
class ServiceGenusProjectTest extends KernelTestBase {
  /**
   * Term service.
   * 
   * @var object
   */
  protected $service;

  /**
   * Term service.
   * 
   * @var object
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
    'projectprop_id' => 0
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

    // Chado database.
    $this->chado = \Drupal::service('tripal_chado.database');

    // Prepare by adding test records to genus, project and projectproperty
    // to relate a genus to a project.
    $project = 'Project - ' . uniqid();
    $sql = "INSERT INTO {1:project} (name, description) VALUES('%s', '%s')";
    $query = sprintf($sql, $project, $project . ': Description');
    $this->chado->query($query);
    
    $project_id =$this->chado->query(
      "SELECT project_id FROM {1:project} WHERE name = :name LIMIT 1", [':name' => $project]
    )
      ->fetchField();
    
    $this->ins['project_id'] = $project_id;
    

    $genus = 'Wild Genus ' . uniqid();
    $sql = "INSERT INTO {1:organism} (genus, species, type_id) VALUES('%s', '%s', '%s')";
    $query = sprintf($sql, $genus, 'Wild Species', 1); // Adding as null organism type.
    $this->chado->query($query);
    
    $genus_id =$this->chado->query(
      "SELECT organism_id FROM {1:organism} WHERE genus = :genus LIMIT 1", [':genus' => $genus]
    )
      ->fetchField();
    
    $this->ins['genus_id'] = $genus_id;
    $this->ins['genus'] = $genus;

    $sql = "INSERT INTO {1:projectprop} (project_id, type_id, value) VALUES('%s', '%s', '%s')";
    // Adding as a type null relationship, this null term should correspond
    // to configuration value for term - genus.
    $query = sprintf($sql, $project_id, 1, $genus);
    $this->chado->query($query); 

    $created =$this->chado->query(
      "SELECT projectprop_id FROM {1:projectprop} 
      WHERE project_id = :project_id AND type_id = 1 AND value = :genus_id LIMIT 1", 
      [':project_id' => $project_id, ':genus_id' => $genus_id]
    )
      ->fetchField();

    $this->ins['projectprop_id'] = $created;

    
    // Create Genus Ontology configuration. 
    // All configuration and database value to null (id: 1).
    $config_name = str_replace(' ', '_', strtolower($genus));
    $genus_ontology_config = [
      $config_name => [
        'trait' => 1,
        'unit'   => 1,
        'method'  => 1,
        'database' => 1,
        'crop_ontology' => 1
      ]
    ];

    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon', $genus_ontology_config);

    // Term service.
    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_project');
  }

  public function testGenusProjectService() {
    // Class created.
    $this->assertNotNull($this->service);

    // Assert all relevant records were created in setup.
    foreach($this->ins as $key => $value) {
      $this->assertNotNull($value);
    }

    // Test get activeGenus().
    $active_genus = $this->service->getActiveGenus();
    $this->assertNotNull($active_genus);
    
    foreach($active_genus as $g) {
      $this->assertEquals($g, $this->ins['genus']);
    }

    // Test getGenusOfProject().
    $genus_project = $this->service->getGenusOfProject($this->ins['project_id']);
    $this->assertNotNull($genus_project);
    $this->assertEquals($genus_project['genus'], $this->ins['genus']);

    // Test setGenusToProject().
    // Insert another genus
    $genus = 'Cultivated Genus ' . uniqid();
    $sql = "INSERT INTO {1:organism} (genus, species, type_id) VALUES('%s', '%s', '%s')";
    $query = sprintf($sql, $genus, 'Cultivated Species', 1); // Adding as null organism type.
    $this->chado->query($query);
    
    $genus_id =$this->chado->query(
      "SELECT organism_id FROM {1:organism} WHERE genus = :genus LIMIT 1", [':genus' => $genus]
    )
      ->fetchField();

    // From Wild genus to cultivated genus.
    $set = $this->service->setGenusToProject($this->ins['project_id'], $genus, TRUE);
    $this->assertTrue($set);
    $new_genus = $this->service->getGenusOfProject($this->ins['project_id']);
    $this->assertEquals($new_genus['genus'], $genus);

    // Does nothing. replace option is default to false.
    $set = $this->service->setGenusToProject($this->ins['project_id'], 'REPLACEMENT GENUS');
    $this->assertTrue($set);

    // No relationship yet. Create a relationship in projectprop table.
    // Create a new project.
    $project = 'New Project - ' . uniqid();
    $sql = "INSERT INTO {1:project} (name, description) VALUES('%s', '%s')";
    $query = sprintf($sql, $project, $project . ': Description');
    $this->chado->query($query);
    
    $project_id =$this->chado->query(
      "SELECT project_id FROM {1:project} WHERE name = :name LIMIT 1", [':name' => $project]
    )
      ->fetchField();

    $set = $this->service->setGenusToProject($project_id, $genus);
    $this->assertTrue($set);

    $new_project_genus = $this->service->getGenusOfProject($project_id);
    $this->assertEquals($new_project_genus['genus'], $genus);
  }
}
