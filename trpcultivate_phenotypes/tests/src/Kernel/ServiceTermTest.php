<?php

/**
 * @file
 * Kernel test of Terms service.
 */

 namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

 use Drupal\KernelTests\KernelTestBase;
 use Drupal\tripal\Services\TripalLogger;

 /**
  * Test Tripal Cultivate Phenotypes Terms service.
  *
  * @group trpcultivate_phenotypes
  */
class ServiceTermTest extends KernelTestBase {
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
    'node',
    'user'
  ];

  /**
   * Configuration
   * 
   * @var config_entity
   */
  private $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);
    
    // This line will create chado install schema.
    $this->installSchema('tripal_chado', ['chado_installations']);

    // Install required dependencies.
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

  public function testTermService() {
    // Enable module.
    $this->container->get('module_installer')->install(['trpcultivate_phenotypes']);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');
    // Term service.
    $this->service = \Drupal::service('trpcultivate_phenotypes.terms');

    // Class was created.
    $this->assertNotNull($this->service);
    
    // Test defineTerms().
    $define_terms = $this->service->defineTerms();
    $keys = array_keys($define_terms);

    $this->assertNotNull($define_terms);
    // Is an array.
    $is_array = (is_array($define_terms)) ? TRUE : FALSE;
    $this->assertTrue($is_array);

    // Compare what was defined and the pre-defined terms in the
    // config settings file.
    $term_set = $this->config->get('trpcultivate.default_terms.term_set');
    foreach($term_set as $id => $terms) {
      foreach($terms['terms'] as $term) {
        $match = (in_array($term['config_map'], $keys)) ? TRUE : FALSE;
        $this->assertNotNull($term['config_map']);
        $this->assertTrue($match);
      }
    }

    // Test loadTerms().
    $is_loaded = $this->service->loadTerms('chado');
    $this->assertTrue($is_loaded);

    // #Test getTermId().
    foreach($keys as $key) {
      $id = $this->service->getTermId($key);
      $this->assertNotNull($id);
      $this->assertGreaterThan(0, $id);
      
      $id_to_name[ $id ] = [
        'key' => $key, // config name key.
        'id' => $id,   // cvterm id.
        'name' => $define_terms[ $key ]['name'] // cvterm name.
      ];
    }

    $not_valid_keys = [':p', -1, 0, 'abc', 999999, '', 'lorem_ipsum', '.'];
    foreach($not_valid_keys as $n) {
      // Invalid config name key, will return 0 value.
      $v = $this->service->getTermId($n);
      $this->assertEquals($v, 0);
    }
    
    // Test values matched to what was loaded into the table.
    $chado = \Drupal::service('tripal_chado.database');
    $all_ids = array_keys($id_to_name);
    $rec = $chado->query(
      'SELECT cvterm_id, name FROM {1:cvterm} WHERE cvterm_id IN(:id[])', 
      [':id[]' => $all_ids]
    );

    foreach($rec as $r) {
      $this->assertNotNull($id_to_name[ $r->cvterm_id ]);
      $this->assertEquals($id_to_name[ $r->cvterm_id ]['id'], $r->cvterm_id);
      $this->assertEquals($id_to_name[ $r->cvterm_id ]['name'], $r->name);
    }
    
    // #Test saveTermConfigValues().
    // With the loadTerms above, each term configuration was set with
    // a term id number that matches a term in chado.cvterm. This test
    // will set all terms configuration to null (id: 1).
    
    // This would have came from form submit method.
    $config_values = [];
    foreach($keys as $key) {
      $config_values[ $key ] = 1;
    }

    $is_saved = $this->service->saveTermConfigValues($config_values);
    $this->assertTrue($is_saved);

    foreach($keys as $key) {
      // Test if all config got nulled.
      $id = $this->service->getTermId($key);
      $this->assertEquals($id, 1);
    }

    // Nothing to save.
    $not_saved = $this->service->saveTermConfigValues([]);
    $this->assertFalse($not_saved);    
  }
}