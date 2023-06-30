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
 * Service: trpcultivate_phenotypes.genus_ontology
 * Class: TripalCultivatePhenotypesGenusOntologyService
 */
class ServiceGenusOntologyTest extends ChadoTestKernelBase {
  protected $service;

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

    $this->installConfig(['trpcultivate_phenotypes']);

    $test_insert_genus = ['Lens', 'Cicer'];
    $chado = \Drupal::service('tripal_chado.database');
    $ins_genus = "
      INSERT INTO {1:organism} (genus, species, type_id)
      VALUES
        ('$test_insert_genus[0]', 'culinaris', 1),
        ('$test_insert_genus[1]', 'arientinum', 1)
    ";

    $chado->query($ins_genus);

    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
  }

  public function testGenusOntologyService() {
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Class created.
    $this->assertNotNull($this->service);

    // TEST WHEN THERE IS A GENUS RECORD.
    // Create genus records since a clean Tripal site has
    // no organism/genus records and re-run routines carried out
    // during install process.

    // Created genus of type null (id: 1).
    $test_insert_genus = ['Lens', 'Cicer'];

    // #Test defineGenusOntology().
    $define_genusontology = $this->service->defineGenusOntology();
    $this->assertNotNull($define_genusontology);
    // Is an array.
    $is_array = (is_array($define_genusontology)) ? TRUE : FALSE;
    $this->assertTrue($is_array);

    foreach($test_insert_genus as $g) {
      $key = $this->service->formatGenus($g);
      $this->assertNotNull($define_genusontology[ $key ]);
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
      $this->assertEquals($format_genus, $result);
    }

    // #Test loadGenusOntology().
    $is_saved = $this->service->loadGenusOntology();
    $this->assertTrue($is_saved);

    // Compare what was registered in the config settings.
    $config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');
    $config_genus_ontology = $config->get('trpcultivate.phenotypes.ontology.cvdbon');
    foreach($test_insert_genus as $genus) {
      $g = $this->service->formatGenus($genus);
      // Genus configuration found.
      $this->assertNotNull($config_genus_ontology[ $g ]);

      // Genus configuration properties/variables are set to 0.
      foreach($config_genus_ontology[ $g ] as $prop => $val) {
        $is_config = (in_array($prop, ['trait', 'unit', 'method', 'database', 'crop_ontology'])) ? TRUE : FALSE;
        $this->assertTrue($is_config);
        $this->assertEquals($val, 0);
      }
    }

    // #Test getGenusOntologyConfigValues().
    foreach($test_insert_genus as $genus) {
      $genus_config = $this->service->getGenusOntologyConfigValues($genus);
      $this->assertNotNull($genus_config);
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
    $this->assertTrue($is_saved);

    // Test if all genus ontology config got nulled.
    foreach($test_insert_genus as $genus) {
      $genus_config = $this->service->getGenusOntologyConfigValues($genus);
      foreach($genus_config as $config_name => $config_value) {
        $this->assertEquals($config_value, $null_id);
      }
    }
  }
}
