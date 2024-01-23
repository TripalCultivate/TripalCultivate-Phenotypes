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
    // Remove services from the container that were initialized before the above chado.
    $this->container->set('trpcultivate_phenotypes.genus_ontology', NULL);
    $this->container->set('trpcultivate_phenotypes.terms', NULL);

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

    // Set genus to use by the traits service.
    $this->service_traits->setTraitGenus($this->genus);
    // Save the trait.
    $trait_assets = $this->service_traits->insertTrait($trait);

    // Trait, method and unit.
    $sql = "SELECT * FROM {1:cvterm} WHERE cvterm_id = :id LIMIT 1";

    foreach($trait_assets as $type => $value) {
      // Query.
      $rec = $this->chado->query($sql, [':id' => $value])
        ->fetchObject();

      if ($type == 'trait') {
        // Trait created.
        $this->assertEquals($rec->name, $trait['Trait Name'], 'Failed to insert trait.');
        // Inserted into the correct cv the genus is configured.
        $this->assertEquals($rec->cv_id, 1, 'Failed to insert trait into cv genus is configured.');
      }
      elseif ($type == 'method') {
        // Trait method created.
        $this->assertEquals($rec->name, $trait['Method Short Name'], 'Failed to insert trait method.');
        // Inserted into the correct cv the genus is configured.
        $this->assertEquals($rec->cv_id, 1, 'Failed to insert trait method into cv genus is configured.');
      }
      elseif ($type == 'unit') {
        // Trait unit created.
        $this->assertEquals($rec->name, $trait['Unit'], 'Failed to insert trait unit.');
        // Inserted into the correct cv the genus is configured.
        $this->assertEquals($rec->cv_id, 1, 'Failed to insert trait unit into cv genus is configured.');
      }
    }

    // Test relations.
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship}
      WHERE subject_id = :s_id AND type_id = :t_id AND object_id = :o_id";

    // Method - trait.
    $rec = $this->chado->query($sql, [':s_id' =>$trait_assets['trait'], ':t_id' => 1, ':o_id' => $trait_assets['method']]);
    $this->assertNotNull($rec, 'Failed to relate method to trait.');

    // Method - unit.
    $rec = $this->chado->query($sql, [':s_id' =>$trait_assets['method'], ':t_id' => 1, ':o_id' => $trait_assets['unit']]);
    $this->assertNotNull($rec, 'Failed to relate method to unit.');

    // Test unit data type.
    $sql = "SELECT cvtermprop_id, value FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado->query($sql, [':c_id' => $trait_assets['unit'], ':t_id' => 1])
      ->fetchObject();

    $this->assertNotNull($data_type, 'Failed to insert unit property - additional type.');
    $this->assertEquals($data_type->value, 'Quantitative', 'Unit property - additional type does not match expected value (Quantitative).');

    // Test get trait.

    $trait_id = $trait_assets['trait'];
    $trait_name = $insert_trait[0];

    // Get trait by id.
    $t = $this->service_traits->getTrait(['id' => $trait_id]);
    $this->assertEquals($trait_name, $t->name, 'Trait not found (by trait id).');

    // Get trait by name.
    $t = $this->service_traits->getTrait(['name' => $trait_name]);
    $this->assertEquals($trait_name, $t->name, 'Trait not found (by trait name).');

    // Test get trait method.
    $method_name = $insert_trait[2];

    // Get trait method by trait id.
    $m = $this->service_traits->getTraitMethod(['id' => $trait_id]);
    $this->assertEquals($method_name, $m[0]->name, 'Trait method not found (by trait id).');

    // Get trait method by trait name.
    $m = $this->service_traits->getTraitMethod(['name' => $trait_name]);
    $this->assertEquals($method_name, $m[0]->name, 'Trait method not found (by trait name).');
    $method_id = $m[0]->cvterm_id;

    // Test get trait method unit.

    $unit_name = $insert_trait[4];
    $u = $this->service_traits->getMethodUnit($method_id);
    $this->assertEquals($unit_name, $u[0]->name, 'Trait method unit not found.');
    $unit_id = $u[0]->cvterm_id;

    // Test get unit data type.
    $data_type = $insert_trait[5];
    $dt = $this->service_traits->getMethodUnit($unit_id);
    $this->assertEquals($data_type, $dt, 'Trait method unit data type does not match expected.');
  }
}
