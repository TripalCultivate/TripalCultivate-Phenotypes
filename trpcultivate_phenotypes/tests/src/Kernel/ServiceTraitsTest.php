<?php

/**
 * @file
 * Kernel test of Traits service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;

/**
 * Tests associated with the Traits Service.
 *
 * @group trpcultivate_phenotypes
 */
class ServiceTraitsTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $service_traits;

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $chado;

  // Genus.
  private $genus;

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
  protected function setUp() :void {
    parent::setUp();
    
    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Set ontology terms to null (id: 1).
    $req_terms = [
      'method', // Collection method.
      'unit',   // Trait unit of measurement. 
      'unit_to_method_relationship_type',  // Relate unit - method.
      'method_to_trait_relationship_type', // Relate method - trait.
      'additional_type' // Unit data type.
    ];
    
    foreach($req_terms as $term) {
      $this->config->set('trpcultivate.phenotypes.ontology.terms.' . $term, 1);
    }
    
    // Create an active genus.

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    $genus = 'Wild Genus ' . uniqid();
    $this->genus = $genus;

    $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
        'type_id' => 1 
      ])
      ->execute();
    
    // Create Genus Ontology configuration. 
    // All configuration and database value to null (id: 1).
    // Traits, method and unit will be inserted into cv set for trait, unit and method.
    $config_name = str_replace(' ', '_', strtolower($genus));
    $genus_ontology_config = [
      'trait' => 1,
      'unit'   => 1,
      'method'  => 1,
      'database' => 1,
      'crop_ontology' => 1
    ];

    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $genus_ontology_config);
  
    // Set the traits service.
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');
  }

  /**
   * Test traits service.
   */
  public function testTraitsService() {
    // Create a trait, unit and method test records.
    
    // As defined by headers property in the importer.
    $headers = [
      'Trait Name', 
      'Trait Description', 
      'Method Short Name', 
      'Collection Method', 
      'Unit',
      'Type'
    ]; 

    // As with the traits importer, values are:
    // Trait Name, Trait Description, Method Short Name, Collection Method, Unit and Type.
    // This is a tsv string similar to a line/row in traits data file.
    $trait_ABC = "TraitABC\t\"TraitABC Description\"\tM-ABC\t\"Pull from ground\"\tcm\tQuantitative";

    // Split tsv to data points and map to headers array where the key is the header
    // and value is the corresponding data point.
    $data_columns = str_getcsv($trait_ABC, "\t");
    // Sanitize every data in rows and columns.
    $data = array_map(function($col) { return isset($col) ? trim(str_replace(['"','\''], '', $col)) : ''; }, $data_columns);
    
    $trait = [];
    $headers_count = count($headers);

    for ($i = 0; $i < $headers_count; $i++) {
      $trait[ $headers[ $i ] ] = $data[ $i ];
    }

    // Save the trait.
    // @TODO: Test genus was created before 
    $ins_trait = $this->service_traits->insertTrait($trait, $this->genus);

    // Test inserted traits.
    print_r($ins_trait);
  }
}