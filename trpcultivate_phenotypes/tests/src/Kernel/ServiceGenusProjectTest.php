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
  protected $service;

  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  private $projects;
  private $genus;

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['trpcultivate_phenotypes']);
    
    // Create test records: project, organism and genus-project relationship
    // using projectprop table.
    $chado = \Drupal::service('tripal_chado.database');
    $chado->query("DELETE FROM {1:project} WHERE project_id > 0");
    $chado->query("DELETE FROM {1:organism} WHERE organism_id > 0");
    $chado->query("DELETE FROM {1:projectprop} WHERE project_id > 0");

    $this->projects = [
      'Project ABC',
      'Project XYZ'
    ];

    $this->organism = [
      'Genus EFG',
      'Genus QRS'
    ];

    // Insert projects.
    $chado->query("
      INSERT INTO {1:project} (project_id, name, description)
      VALUES
        (1, 'Project ABC', 'ABC description'),
        (2, 'Project XYZ', 'XYZ description')
    ");

    // Insert organism. type null (id: 1).
    $chado->query("
      INSERT INTO {1:organism} (organism_id, genus, species, type_id)
      VALUES
        (1, 'Genus Wild', 'Species LMN', 1),
        (2, 'Genus Cultivated', 'Species HIJ', 1)
    ");    

    // Insert relationship in projectprop table.
    // relationship type is null (id: 1) which would
    // represent the term.genus configuration value.
    $chado->query("
      INSERT INTO {1:projectprop} (project_id, type_id, value)
      VALUES
        (1, 1, 2),
        (2, 1, 1)
    ");

    // Create genus-ontology configuration variable.
    $config = \Drupal::service('config.factory')
      ->getEditable('trpcultivate_phenotypes.settings');
    
    $genus_config = [
      'genus_efg' => [
        'trait' => 1,
        'unit' => 1,
        'method' => 1,
        'database' => 1,
        'crop_ontology' => 1
      ],
      'genus_qrs' => [
        'trait' => 1,
        'unit' => 1,
        'method' => 1,
        'database' => 1,
        'crop_ontology' => 1
      ]
    ];

    $config->set('trpcultivate.phenotypes.ontology.cvdbon', $genus_config);
    
    // Service.
    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_project');
  }

  public function testGenusProjectService() {
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Class created.
    $this->assertNotNull($this->service);
    
    $active_genus = $this->service->getActiveGenus();
    var_dump($active_genus);
    $this->assertNotNull($active_genus);     
    foreach($active_genus as $genus) {
      // Reconstruct genus configuration name and compare 
      // to genus inserted in the setup routine.
      $g = ucfirst(str_replace('_', ' ', $genus));
      $match = (in_array($genus, $this->genus)) ? TRUE : FALSE;
      $this->assertTrue($match);
    }
  }
}
