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

    // Install required dependencies - T3 legacy functions .
    $tripal_chado_path = 'modules/contrib/tripal/tripal_chado/src/api/';
    $tripal_chado_api = [
      'tripal_chado.cv.api.php',
      'tripal_chado.variables.api.php',
      'tripal_chado.schema.api.php'
    ];

    if ($handle = opendir($tripal_chado_path)) {
      while (false !== ($file = readdir($handle))) {
        if (strlen($file) > 2 && in_array($file, $tripal_chado_api)) {
          include_once($tripal_chado_path . $file);
        }
      }

      closedir($handle);
    }
  }

  /**
   * Test test records were created.
   */
  public function testRecordsCreated() {
    // Test genus.
    $sql_genus = "SELECT genus FROM {1:organism} WHERE genus = :genus LIMIT 1";
    $genus = $this->chado->query($sql_genus, [':genus' => $this->genus])
      ->fetchField();

    $this->assertNotNull($genus, 'Genus test record not created.');
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
    $trait  = 'TraitABC'  . uniqid(); 
    $method = 'MethodABC' . uniqid();
    $unit   = 'UnitABC'   . uniqid();

    $insert_trait = [
      $trait,
      $trait  . ' Description',
      $method . '-SName',
      $method . ' - Pull from ground',
      $unit,
      'Quantitative'
    ]; 
    
    $line = implode("\t", $insert_trait);

    // Split tsv to data points and map to headers array where the key is the header
    // and value is the corresponding data point.
    $data_columns = str_getcsv($line, "\t");
    // Sanitize every data in rows and columns.
    $data = array_map(function($col) { return isset($col) ? trim(str_replace(['"','\''], '', $col)) : ''; }, $data_columns);
    
    $trait = [];
    $headers_count = count($headers);

    for ($i = 0; $i < $headers_count; $i++) {
      $trait[ $headers[ $i ] ] = $data[ $i ];
    }

    // Save the trait.
    $ins_trait = $this->service_traits->insertTrait($trait, $this->genus);

    // Test inserted trait, method and unit.
    $this->assertEquals($ins_trait['trait']->name, $trait['Trait Name'], 'Failed to insert trait.');
    $this->assertEquals($ins_trait['method']->name, $trait['Method Short Name'], 'Failed to insert trait method.');
    $this->assertEquals($ins_trait['unit']->name, $trait['Unit'], 'Failed to insert trait unit.');

    // Test trait, method and unit are inserted in the correct cv as
    // configured for the genus.
    $this->assertEquals($ins_trait['trait']->cv_id, 1, 'Failed to insert trait in the configured cv.');
    $this->assertEquals($ins_trait['trait']->db_id, 1, 'Failed to insert trait in the configured db.');

    $this->assertEquals($ins_trait['method']->cv_id, 1, 'Failed to insert trait method in the configured cv.');
    $this->assertEquals($ins_trait['method']->db_id, 1, 'Failed to insert trait method in the configured db.');
    
    $this->assertEquals($ins_trait['unit']->cv_id, 1, 'Failed to insert trait unit in the configured cv.');
    $this->assertEquals($ins_trait['unit']->db_id, 1, 'Failed to insert trait unit in the configured db.');    

    // Test relationships created.

    // Supplemental metadata to unit.
    $unit_type = [
      'cvterm_id' => $ins_trait['unit']->cvterm_id,
      'type_id' => 1 // Null.
    ];

    $prop_unit = chado_select_record('cvtermprop', ['cvtermprop_id', 'value'], $unit_type)[0];


    $sql = "SELECT cvtermprop_id FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $prop_unit = $this->chado->query($sql, [':c_id' => $ins_trait['unit']->cvterm_id, ':t_id' => 1])
      ->fetchField();

    $this->assertNotNull($prop_unit, 'Failed to insert unit property - additional type.');
    $this->assertEquals($prop_unit->value, 'Quantitative', 'Unit property - additional type does not match expected value.');

    // Relationships:
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = :t_id AND object_id = :o_id LIMIT 1";

    // Trait-Method Relation.
    $trait_method_rel = [
      'subject_id' => $ins_trait['trait']->cvterm_id,
      'type_id' => 1, // Null
      'object_id' => $ins_trait['method']->cvterm_id,
    ];

    $rec_trait_method = $this->chado->query($sql, [':s_id' =>$ins_trait['trait']->cvterm_id, ':t_id' => 1, ':o_id' => $ins_trait['method']->cvterm_id])
    $this->assertNotNull($rec_trait_method, 'Failed to relate trait to method.');
    
    // Method-Unit Relation.
    $method_unit_rel = [
      'subject_id' => $ins_trait['method']->cvterm_id,
      'type_id' => 1, // Null
      'object_id' => $ins_trait['unit']->cvterm_id,
    ];

    $rec_method_unit = $this->chado->query($sql, [':s_id' =>$ins_trait['method']->cvterm_id, ':t_id' => 1, ':o_id' => $ins_trait['unit']->cvterm_id])
    $this->assertNotNull($rec_trait_method, 'Failed to relate method to unit.');
  }
}