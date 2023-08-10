<?php

/**
 * @file
 * Kernel test of Genus Ontology service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;

/**
 * Tests associated with the Genus Ontology Service.
 *
 * @group trpcultivate_phenotypes
 */
class ServiceGenusOntologyTest extends ChadoTestKernelBase {
  /**
   * Term service.
   *
   * @var object
   */
  protected $service;

  /**
   * Modules to enable.
   */
  protected static $modules = [
   'tripal',
   'tripal_chado',
   'trpcultivate_phenotypes'
  ];

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
   * Test genus.
   * 
   * @var array
   */
  private $test_genus = [
    'Lens',
    'Cicer'
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    
    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    // Create organism of type null (id: 1).
    $test_insert_genus = $this->test_genus;
    $ins_genus = "
      INSERT INTO {1:organism} (genus, species, type_id)
      VALUES
        ('$test_insert_genus[0]', 'culinaris', 1),
        ('$test_insert_genus[1]', 'arientinum', 1)
    ";

    $this->chado->query($ins_genus);

    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
  }

  public function testGenusOntologyService() {
    // Class created.
    $this->assertNotNull($this->service, 'Genus ontology service not created.');

    // TEST WHEN THERE IS A GENUS RECORD.
    // Create genus records since a clean Tripal site has
    // no organism/genus records and re-run routines carried out
    // during install process.

    // Created genus of type null (id: 1).
    $test_insert_genus = $this->test_genus;

    // #Test defineGenusOntology().
    $define_genusontology = $this->service->defineGenusOntology();
    $this->assertNotNull($define_genusontology, 'Failed to define genus ontology configuration.');
    // Is an array.
    $is_array = (is_array($define_genusontology)) ? TRUE : FALSE;
    $this->assertTrue($is_array, 'Define genus ontology returned an unexpected value.');

    foreach($test_insert_genus as $g) {
      $key = $this->service->formatGenus($g);
      $this->assertNotNull($define_genusontology[ $key ], 'Failed to create genus ontology configuration with key: ' . $key);
    }

    // #Test formatGenus().
    // Genus = formatting applied by formatGenus().
    $test_genus = [
      'Lens' => 'lens',
      'Hello Genus' => 'hello_genus',
      'WOW GENUS' => 'wow_genus',
      'beautiful_genus' => 'beautiful_genus',
      'a genus ' => 'a_genus',
      'Y' => 'y',
      '     Not cool genus    ' => 'not_cool_genus',
      ' ' => null
    ];

    foreach($test_genus as $base => $result) {
      $format_genus = $this->service->formatGenus($base);
      $this->assertEquals($format_genus, $result, 'Base genus key and formatted genus key do not match.');
    }

    // #Test loadGenusOntology().
    $is_saved = $this->service->loadGenusOntology();
    $this->assertTrue($is_saved, 'Failed to load genus ontology.');

    // Compare what was registered in the config settings.
    $config_genus_ontology = $this->config->get('trpcultivate.phenotypes.ontology.cvdbon');
    foreach($test_insert_genus as $genus) {
      $g = $this->service->formatGenus($genus);
      // Genus configuration found.
      $this->assertNotNull($config_genus_ontology[ $g ], 'Failed to register a configuration with the key: ' . $g);

      // Genus configuration properties/variables are set to 0.
      foreach($config_genus_ontology[ $g ] as $prop => $val) {
        $is_config = (in_array($prop, ['trait', 'unit', 'method', 'database', 'crop_ontology'])) ? TRUE : FALSE;
        $this->assertTrue($is_config, 'Genus ontology configuration has no property: ' . $g . ' - ' . $prop);
        $this->assertEquals($val, 0, 'Genus ontology has unexpected default value (expecting 0): ' . $val);
      }
    }

    // #Test getGenusOntologyConfigValues().
    foreach($test_insert_genus as $genus) {
      $genus_config = $this->service->getGenusOntologyConfigValues($genus);
      $this->assertNotNull($genus_config, 'Failed to fetch value of a genus ontology configuration: ' . $genus);
    }

    $not_valid_keys = [':p', -1, 0, 'abc', 999999, '', 'lorem_ipsum', '.', 'G', 'lenz', '@'];
    foreach($not_valid_keys as $key) {
      $not_found = $this->service->getGenusOntologyConfigValues($key);
      $this->assertEquals($not_found, 0);
    }

    // #Test saveGenusOntologyConfigValues().
    // loadGenusOntology() sets all configuration values for all genus to a default value
    // 0. This test will set every configuration to null cv (id: 1) and null db (id: 1).

    // This would have came from a form submit method.
    $null_id = 1;
    $genus_ontology_values = [];

    foreach($define_genusontology as $genus_key => $config_values) {
      foreach($config_values as $config_name) {
        $genus_ontology_values[ $genus_key ][ $config_name ] = $null_id;
      }
    }

    $is_saved = $this->service->saveGenusOntologyConfigValues($genus_ontology_values);
    $this->assertTrue($is_saved, 'Failed to save genus ontology value.');

    // Test if all genus ontology config got nulled.
    foreach($test_insert_genus as $genus) {
      $genus_config = $this->service->getGenusOntologyConfigValues($genus);
      foreach($genus_config as $config_name => $config_value) {
        $this->assertEquals($config_value, $null_id, 'Genus ontology configuration has unexpected value (expecting 1): ' . $config_value);
      }
    }
  }
}
