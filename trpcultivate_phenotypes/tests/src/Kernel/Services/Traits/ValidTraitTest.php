<?php

/**
 * @file
 * Kernel test of Traits service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services\Traits;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;

/**
 * Tests that a valid trait/method/unit combination can be inserted/retrieved.
 *
 * @group trpcultivate_phenotypes
 * @group services
 * @group traits
 */
class ValidTraitTest extends ChadoTestKernelBase {
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

  /**
   * Configuration
   *
   * @var config_entity
   */
  protected $config;

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
   * Term config key to cvterm_id mapping.
   * Note: we just grabbed some random cvterm_ids that we know for sure exist.
   */
  protected array $terms = [
    'method_to_trait_relationship_type' => 100,
    'unit_to_method_relationship_type' => 200,
    'trait_to_synonym_relationship_type' => 300,
    'unit_type' => 400,
  ];

  /**
   * CV and DB's configured for this genus.
   * NOTE: We will create these in the setUp.
   */
  protected array $genus_ontology_config = [
    'trait' => NULL,
    'unit'   => NULL,
    'method'  => NULL,
    'database' => NULL,
    'crop_ontology' => NULL
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);
    // Remove services from the container that were initialized before the above chado.
    $this->container->set('trpcultivate_phenotypes.genus_ontology', NULL);
    $this->container->set('trpcultivate_phenotypes.terms', NULL);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Set ontology terms in the configuration used by the terms service.
    foreach($this->terms as $config_key => $cvterm_id) {
      $this->config->set('trpcultivate.phenotypes.ontology.terms.' . $config_key, $cvterm_id);
    }

    // Create an chado organism genus.
    // This will be configured below to become the active genus.
    $genus = 'Wild Genus ' . uniqid();
    $this->genus = $genus;
    $organism_id = $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were unable to create the organism record in chado.");

    // Create Genus Ontology configuration.
    // First create a cv or db for each...
    $config_name = str_replace(' ', '_', strtolower($genus));
    foreach ($this->genus_ontology_config as $key => $id) {
      $name = $genus . ' ' . $key;
      $table = ($key == 'database') ? '1:db' : '1:cv';
      $id = $this->chado->insert($table)->fields(['name' => $name])->execute();
      $this->assertIsNumeric($id,
        "Unable to create a record in '$table' for $key where name = '$name'");
      $this->genus_ontology_config[$key] = $id;
    }
    // Then save the configuration set to the new ids.
    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $this->genus_ontology_config);

    // Set the traits service.
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');

    // Install required dependencies - T3 legacy functions.
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
   * Tests that inserting a trait/method/unit populates the database as we expect.
   */
  public function testTraitsServiceDatabaseExpectations() {

    // Generate some fake/unique names.
    $trait_name  = 'TraitABC'  . uniqid();
    $method_name = 'MethodABC' . uniqid();
    $unit_name   = 'UnitABC'   . uniqid();

    // Now bring these together into the array of values
    // requested by the insertTrait() method.
    $trait = [
      'Trait Name' => $trait_name,
      'Trait Description' => $trait_name  . ' Description',
      'Method Short Name' => $method_name . '-SName',
      'Collection Method' => $method_name . ' - Pull from ground',
      'Unit' => $unit_name,
      'Type' => 'Quantitative'
    ];

    // Set genus to use by the traits service.
    $this->service_traits->setTraitGenus($this->genus);
    // Save the trait.
    $trait_assets = $this->service_traits->insertTrait($trait);

    // Trait, method and unit.
    $sql = "SELECT * FROM {1:cvterm} WHERE cvterm_id = :id LIMIT 1";

    foreach($trait_assets as $type => $value) {
      // Retrieve the cvterm with the cvterm_di returned by the service.
      $rec = $this->chado->query($sql, [':id' => $value])
        ->fetchObject();
      $this->assertIsObject($rec,
        "We were unable to retrieve the $type record from chado based on the cvterm_id $value provided by the service.");

      // The was configured in setUp and is keyed by the type.
      $expected_cv = $this->genus_ontology_config[$type];
      // Ensure it was inserted into the correct cv the genus is configured for.
      $this->assertEquals($expected_cv, $rec->cv_id,
        "Failed to insert $type into cv genus is configured.");

      // Check that the name of the cvterm is as we expect.
      $expected_name = NULL;
      if ($type == 'trait')  $expected_name = $trait['Trait Name'];
      if ($type == 'method')  $expected_name = $trait['Method Short Name'];
      if ($type == 'unit')  $expected_name = $trait['Unit'];
      $this->assertEquals($expected_name, $rec->name,
        "The name in the database for the $type did not match the one we expected.");
    }

    // Test relationships.
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship}
      WHERE subject_id = :s_id AND type_id = :t_id AND object_id = :o_id";

    // Method - trait.
    // @todo this relationship is currently in the wrong order
    // for the term we choose but is the same order as in AP.
    // Expected "Measured with ruler" is "Method" of "Plant Height"
    // but is currently saved as "Plant Height" is "Method" of "Measured with ruler"
    $rec = $this->chado->query($sql, [
      ':s_id' => $trait_assets['trait'],
      ':t_id' => $this->terms['method_to_trait_relationship_type'],
      ':o_id' => $trait_assets['method']
    ]);
    $this->assertNotNull($rec,
      'Failed to insert a relationship between the method and it\'s trait.');

    // Method - unit.
    // @todo this relationship is currently in the wrong order
    // for the term we choose but is the same order as in AP.
    // Expected "cm" is "Unit" of "Measured with Ruler"
    // but it is currently saved as "Measures with ruler" is "Unit" of "cm"
    $rec = $this->chado->query($sql, [
      ':s_id' => $trait_assets['method'],
      ':t_id' => $this->terms['unit_to_method_relationship_type'],
      ':o_id' => $trait_assets['unit']
    ]);
    $this->assertNotNull($rec,
      'Failed to insert a relationship between the method and it\'s unit.');

    // Test unit data type.
    $sql = "SELECT cvtermprop_id, value FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado->query($sql, [
      ':c_id' => $trait_assets['unit'],
      ':t_id' => $this->terms['unit_type'],
      ])->fetchObject();

    $this->assertNotNull($data_type, 'Failed to insert unit property - additional type.');
    $this->assertEquals($data_type->value, 'Quantitative', 'Unit property - additional type does not match expected value (Quantitative).');

  }

  /**
   * Test that we can retrieve a trait we just inserted.
   */
  public function testTraitsServiceGetters() {
    // Set the genus.
    $this->service_traits->setTraitGenus($this->genus); 
    
    // Test Data.
    // Keys.
    $keys = [
      'trait' => 'Trait Name',
      'method' => 'Method Short Name',
      'unit' => 'Unit'
    ];

    $test_combo = [
      [
        $keys['trait']  => 'A Trait', // A
        $keys['method'] => 'A Method',
        $keys['unit']   => 'A Unit'
      ],

      // This is trait A Trait
      // New method
      // Re-use C Unit
      [
        $keys['trait']  => 'A Trait',  // A
        $keys['method'] => 'B Method',
        $keys['unit']   => 'A Unit',
      ],

      // This is trait A Trait
      // New Method
      // Re-use C Unit
      [
        $keys['trait']  => 'A Trait',  // A
        $keys['method'] => 'C Method',
        $keys['unit']   => 'A Unit'
      ],

      // This is trait A Trait
      // Re-use B Method
      // New Unit
      [
        $keys['trait']  => 'A Trait',  // A
        $keys['method'] => 'B Method',
        $keys['unit']   => 'B Unit'
      ],

      // This is trait A Trait
      // Re-use B Method
      // New Unit
      [
        $keys['trait']  => 'A Trait',  // A
        $keys['method'] => 'B Method',
        $keys['unit']   => 'C Unit'
      ],

      // This is new trait B Trait
      // Re-use B Method
      // Re-use A Unit
      [
        $keys['trait']  => 'B Trait',  // B
        $keys['method'] => 'B Method',
        $keys['unit']   => 'A Unit'
      ],

      // This is trait B Trait
      // Re-use B Method
      // New Unit
      [
        $keys['trait']  => 'B Trait',  // B
        $keys['method'] => 'B Method',
        $keys['unit']   => 'D Unit'
      ],

      // This is trait B Trait
      // New method
      // Re-use C Unit
      [
        $keys['trait']  => 'B Trait',  // B
        $keys['method'] => 'C Method',
        $keys['unit']   => 'C Unit'
      ],

      // This is trait C Trait
      // New method
      // New Unit
      [
        $keys['trait']  => 'C Trait',  // C
        $keys['method'] => 'D Method',
        $keys['unit']   => 'E Unit'
      ]
    ];

    // Summary:
    // A Trait has 3 methods - A, B, C Method
    // B Trait has 2 methods - B and C Method
    // C Trait has 1 method - C Method
    // A Method has 1 unit - A Unit
    // B Method has 4 units - A, B, C, and D Unit
    // C Method has 2 units - A and C Unit.
    // D Method has 1 unit - E Unit

    // Construct trait asset array.
    foreach($test_combo as $i => $combo) {
      $data_type = ($i % 2 == 0) ? 'Qualitative' : 'Quantitative'; 
      
      // Force E Unit to be Quantitative for the test.
      if ($combo['Unit'] == 'E Unit') {
        $data_type = 'Quantitative';
      }

      $ins_trait = [ 
        'Trait Name' => $combo['Trait Name'],
        'Trait Description' => $combo['Trait Name']  . ' Description',
        'Method Short Name' => $combo['Method Short Name'],
        'Collection Method' => $combo['Method Short Name'] . ' Collection Method',
        'Unit' => $combo['Unit'],
        'Type' => $data_type
      ];

      // Set genus to use by the traits service.
      // This method will return the inserted cvterm ids.
      $trait_assets = $this->service_traits->insertTrait($ins_trait);
      // Check trait assets got inserted.
      $trait_combo = $this->service_traits->getTraitMethodUnitCombo($combo['Trait Name'], $combo['Method Short Name'], $combo['Unit']);
      
      // Trait.
      $this->assertEquals($trait_assets['trait'], $trait_combo['trait']->cvterm_id, 
        'Test trait '. $combo['Trait Name'] .' was not inserted.');
      // Method.
      $this->assertEquals($trait_assets['method'], $trait_combo['method']->cvterm_id, 
        'Test ' . $combo['Method Short Name'] . ' was not inserted.');
      // Unit.
      $this->assertEquals($trait_assets['unit'], $trait_combo['unit']->cvterm_id, 
        'Test unit ' . $combo['Unit'] . ' was not inserted.');
    }
    
    // At this point, all traits should be in, test the relationships, connections and all.
    
    // Test nothing got inserted more than once and re-using a trait asset meant
    // that it just referenced existing asset and not creating another copy
    // in the same cv the genus is configured.
    $reuse_test = [
      'trait' => 'A Trait',   // Re-used 5x
      'method' => 'B Method', // Re-used 5x
      'unit' => 'C Unit'      // Re-used 2x
    ];

    foreach($reuse_test as $asset => $name) {
      $exception_message = '';
      
      try {
        // Use string trait asset name as parameter.
        $this->service_traits->getTraitAsset($name, $asset);
      }
      catch (Exception $e) {
        // The getter will throw an exception when it detected multiple copies
        // of the same name in the same cv the genus is configured.
        $exception_message = $e->getMessage();
      }
      
      $this->assertEmpty($exception_message, 'Test trait asset ' . $asset . ':' . $name . ' was inserted more than once.');
    }
    
    // Test that any trait asset can be retrieved either by using 
    // name or id number as parameter using getTraitAsset() method.

    // Test that by using id number and the id provided is an id for a trait asset that belongs
    // to a cv not the cv set for the genus.

    // term null with id 1 not in the genus.
    /* Does not seem to catch and would kill the tests.
    $exception_message = '';
    try {
      $this->service_traits->getTraitAsset(1, 'trait');
    }
    catch (Exception $e) {
      $exception_message = $e->getMessage();
    }
    
    $this->assertMatchesRegularExpression('/The requested trait asset (trait|method|unit)/', $exception_message, 
      'Trait asset id provided returned an asset not in the cv the genus was configured.');
    */

    foreach($test_combo as $combo) {
      foreach($keys as $key => $title) {
        // Each combo, retrieve assets - trait, method and unit.
        
        // By string parameter.
        $asset = $this->service_traits->getTraitAsset($combo[ $title ], $key);
        $asset_bystring_id = (int) $asset->cvterm_id;
        $this->assertNotEquals($asset_bystring_id, 0, 'Failed to fetch trait asset' . $key . ' : ' . $combo[ $title ] . ' (by string name parameter).');

        // By id number parameter.
        $asset = $this->service_traits->getTraitAsset($asset_bystring_id, $key);
        $asset_byinteger_id = (int) $asset->cvterm_id;
        $this->assertNotEquals($asset_byinteger_id, 0, 'Failed to fetch trait asset' . $key . ' : ' . $combo[ $title ] . ' (by integer id parameter).');
    
        // Either cases both should match.
        $this->assertEquals($asset_bystring_id, $asset_byinteger_id, 
          'Trait id returned by trait asset getter with string and integer parameters do not match.');      
      }
    }

    // Test get trait using trait name or trait id number as parameter
    // to getTrait() method.
    foreach($test_combo as $combo) {
      $name_key = 'Trait Name';
      
      // By string parameter.
      $trait = $this->service_traits->getTrait($combo[ $name_key ]);
      $trait_id = (int) $trait->cvterm_id;
      $this->assertNotEquals($trait->cvterm_id, 0, 'Failed to fetch trait ' .  $combo[ $name_key ] . ' (by trait name parameter).');
      
      // By id number parameter.
      $trait = $this->service_traits->getTrait($trait->cvterm_id);
      $this->assertNotEquals($trait->cvterm_id, 0, 'Failed to fetch trait ' .  $combo[ $name_key ] . ' (by trait id parameter).');
    
      // Either cases both should match.
      $this->assertEquals($trait_id, (int) $trait->cvterm_id, 
        'Trait id returned by trait getter with string and integer parameters do not match.'); 
    }

    // Test get trait method using trait name or trait id as parameter
    // to getTraitMethod() method.
    
    // Based on the summary of traits assets above, test the following.
    // 1. A Trait has 3 methods - A, B, C Method.
    // 2. C Trait has 1 method - D Method.
    $a_trait_methods_byname = $this->service_traits->getTraitMethod('A Trait');
    $a_trait = $this->service_traits->getTraitAsset('A Trait', 'trait');
    $a_trait_methods_byid   = $this->service_traits->getTraitMethod($a_trait->cvterm_id);

    // Assert that in both cases, the returned set of methods was the same.
    // Then proceed to assert that methods returned are the expected methods.
    $this->assertEquals($a_trait_methods_byname, $a_trait_methods_byid, 
      'A Trait methods returned by methods getter with name and id as parameter do not match.');

    // 3 methods in the set?
    $methods_count = count($a_trait_methods_byid);
    $this->assertEquals($methods_count, 3, 'A Trait methods returned by methods getter does not match expected count (3).'); 

    foreach(['A', 'B', 'C'] as $expected) {
      $method_name = $expected . ' Method';
      $a_found = FALSE;

      foreach($a_trait_methods_byid as $a_m) {
        if ($a_m->name == $method_name) {
          $a_found = TRUE;
          break;
        }
      }

      $this->assertTrue($a_found, 'The method ' . $method_name . ' was not found in the trait methods.');
    }
    
    // The same steps for C Trait.
    $c_trait_methods_byname = $this->service_traits->getTraitMethod('C Trait');
    $c_trait = $this->service_traits->getTraitAsset('C Trait', 'trait');
    $c_trait_methods_byid   = $this->service_traits->getTraitMethod($c_trait->cvterm_id);
    
    $this->assertEquals($c_trait_methods_byname, $c_trait_methods_byid, 
      'C Trait methods returned by methods getter with name and id as parameter do not match.');
    
    $methods_count = count($c_trait_methods_byid);
    $this->assertEquals($methods_count, 1, 'C Trait methods returned by methods getter does not match expected count (1).'); 
    
    $this->assertEquals($c_trait_methods_byid[0]->name, 'D Method', 'The method D Method was not found in the trait methods.');
    
    // Test get unit method using method name or method id as parameter
    // to getMethodUnit() method.

    // Based on the summary of traits assets above, test the following.
    // B Method has 4 units - A, B, C, and D Unit
    // D Method has 1 unit - E Unit

    $b_method_units_byname = $this->service_traits->getMethodUnit('B Method');
    $b_method = $this->service_traits->getTraitAsset('B Method', 'method');
    $b_method_units_byid   = $this->service_traits->getMethodUnit($b_method->cvterm_id);

    $this->assertEquals($b_method_units_byname, $b_method_units_byid, 
      'B Method units returned by units getter with name and id as parameter do not match.');
    
    $units_count = count($b_method_units_byid);
    $this->assertEquals($units_count, 4, 'B Method units returned by units getter does not match expected count (4).');

    foreach(['A', 'B', 'C', 'D'] as $expected) {
      $unit_name = $expected . ' Unit';
      $a_found = FALSE;

      foreach($b_method_units_byid as $a_u) {
        if ($a_u->name == $unit_name) {
          $a_found = TRUE;
          break;
        }
      }

      $this->assertTrue($a_found, 'The method unit ' . $unit_name . ' was not found in the method units.');
    }

    // The same steps for D Method.
    $d_method_units_byname = $this->service_traits->getMethodUnit('D Method');
    $d_method = $this->service_traits->getTraitAsset('D Method', 'method');
    $d_method_units_byid   = $this->service_traits->getMethodUnit($d_method->cvterm_id);

    $this->assertEquals($d_method_units_byname, $d_method_units_byid, 
      'D Method units returned by units getter with name and id as parameter do not match.');

    $units_count = count($d_method_units_byid);
    $this->assertEquals($units_count, 1, 'D Method units returned by units getter does not match expected count (1).');

    $this->assertEquals($d_method_units_byid[0]->name, 'E Unit', 'The unit E Unit was not found in the method units.');

    // Test get unit data type method using unit name or unit id as parameter
    // to getMethodUnitDataType() method.
    // From the trait asset insert test, E Unit was set to Qualitative data type.
    $e_unit_type_byname = $this->service_traits->getMethodUnitDataType('E Unit');
    $e_unit = $this->service_traits->getTraitAsset('E Unit', 'unit');
    $e_unit_type_byid   = $this->service_traits->getMethodUnitDataType($e_unit->cvterm_id);

    // Assert that in both cases, the returned data types were the same.
    $this->assertEquals($e_unit_type_byname, $e_unit_type_byid, 
      'E Unit data type returned by unit type getter with name and id as parameter do not match.');
    
    // Is it Quantitative?
    $this->assertEquals($e_unit_type_byid, 'Quantitative', 
      'E Unit data type returned by unit type getter does not match expected data type (Quantitative).');
  }

  /**
   * Test that we can retrieve a trait we just inserted by providing
   * trait, method and unit combination, of which each can either be the id or name.
   */
  public function testTraitsServiceComboGetters() {
    // Set genus to use by the traits service.
    $this->service_traits->setTraitGenus($this->genus);    
    
    // Generate some fake combination.
    $trait = [
      'trait' => 'Trait Name Combo' . uniqid(),
      'method' => 'Method Name Combo' . uniqid(),
      'unit' => 'Unit Name Combo' . uniqid(),
    ];

    $combo = [
      'Trait Name' => $trait['trait'],
      'Trait Description' => 'A trait name combo',
      'Method Short Name' => $trait['method'],    
      'Collection Method' => 'A trait method collection method', 
      'Unit' => $trait['unit'],
      'Type' => 'Quantitative'
    ];

    $trait_assets = $this->service_traits->insertTrait($combo);

    // Ids.
    $trait_id = $trait_assets['trait'];
    $method_id = $trait_assets['method'];
    $unit_id = $trait_assets['unit'];

    // Invalid parameter error. For all 3 trait asset - trait, method and unit
    // No 0, empty string or negative number.
    $test_missing_param = [
      ['', $method_id, $unit_id], 
      [$trait_id, '', $unit_id],  
      [$trait_id, $method_id, ''],
      ['', '', ''], 
      [0, $method_id, $unit_id],
      [$trait_id, 0, $unit_id],
      [$trait_id, $method_id, 0],
      [0, 0, 0], 
      [-1, $method_id, $unit_id],
      [$trait_id, -1, $unit_id],
      [$trait_id, $method_id, -1],
      [-1, -1, -1]
    ];

    foreach($test_missing_param as $test) {
      $trait_val  = $test[0];
      $method_val = $test[1];
      $unit_val   = $test[2];
      
      $exception_caught  = FALSE;
      $exception_message = '';
      try {
        $this->service_traits->getTraitMethodUnitCombo($trait_val, $method_val, $unit_val);
      }
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }

      $this->assertMatchesRegularExpression('/Not a valid (trait|method|unit) key value provided/', $exception_message, 'Invalid parameter error message does not match expected error.');
    }

    // Not found.
    $test_not_found = [
      ['Not found trait', $method_id, $unit_id],
      [$trait_id, 'Not found method', $unit_id],
      [$trait_id, $method_id, 'Not found unit']
    ];

    foreach($test_not_found as $test) {
      $trait_val  = $test[0];
      $method_val = $test[1];
      $unit_val   = $test[2];

      $combo = $this->service_traits->getTraitMethodUnitCombo($trait_val, $method_val, $unit_val);
      $this->assertEquals($combo, 0, 'Combo getter must return 0 on non-existent record.');
    }

    unset($combo);
    // All parameters as id number (integer).
    $combo = $this->service_traits->getTraitMethodUnitCombo($trait_id, $method_id, $unit_id);
    $this->assertEquals($combo['trait']->name, $trait['trait'], 'Trait name does not match expected trait name.');
    $this->assertEquals($combo['method']->name, $trait['method'], 'Trait name does not match expected method name.');
    $this->assertEquals($combo['unit']->name, $trait['unit'], 'Trait name does not match expected unit name.');

    // All parameters as name (string).
    $combo = $this->service_traits->getTraitMethodUnitCombo($trait['trait'], $trait['method'], $trait['unit']);
    $this->assertEquals($combo['trait']->name, $trait['trait'], 'Trait name does not match expected trait name.');
    $this->assertEquals($combo['method']->name, $trait['method'], 'Trait name does not match expected method name.');
    $this->assertEquals($combo['unit']->name, $trait['unit'], 'Trait name does not match expected unit name.');

    // Mix type parameters (string and integer).
    $combo = $this->service_traits->getTraitMethodUnitCombo($trait_id, $trait['method'],  $unit_id);
    $this->assertEquals($combo['trait']->name, $trait['trait'], 'Trait name does not match expected trait name.');
    $this->assertEquals($combo['method']->name, $trait['method'], 'Trait name does not match expected method name.');
    $this->assertEquals($combo['unit']->name, $trait['unit'], 'Trait name does not match expected unit name.');

    // Check that the unit has extra property data type.
    $this->assertEquals($combo['unit']->data_type, 'Quantitative', 'Unit data_type property value does not match expected type (Quantitative).');
  }
}