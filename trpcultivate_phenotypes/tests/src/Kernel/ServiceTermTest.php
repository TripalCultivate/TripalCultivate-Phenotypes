<?php

/**
 * @file
 * Kernel test of Terms service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;

 /**
  * Test Tripal Cultivate Phenotypes Terms service.
  *
  * @group trpcultivate_phenotypes
  */
class ServiceTermTest extends ChadoTestKernelBase {

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
   * Configuration
   *
   * @var config_entity
   */
  private $config;

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $chado_connection;

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

    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Term service.
    $this->service = \Drupal::service('trpcultivate_phenotypes.terms');
  }

  public function testTermService() {
    // Class was created.
    $this->assertNotNull($this->service);

    // Test defineTerms().
    $define_terms = $this->service->defineTerms();
    $keys = array_keys($define_terms);

    $this->assertNotNull($define_terms);
    $this->assertIsArray($define_terms,
      "We expected defineTerms() to return an array but it did not.");

    // Compare what was defined and the pre-defined terms in the
    // config settings file.
    $term_set = $this->config->get('trpcultivate.default_terms.term_set');
    foreach($term_set as $id => $terms) {
      foreach($terms['terms'] as $term) {
        $this->assertNotNull($term['config_map']);
        $this->assertArrayHasKey($term['config_map'], $define_terms,
          "The config_map retrieved from config should match one of the keys from defineTerms().");
      }
    }

    // Test loadTerms().
    $is_loaded = $this->service->loadTerms();
    $this->assertTrue($is_loaded,
      "We expect loadTerms() to return TRUE to indicate it successfully loaded the terms.");

    // Test getTermId().
    $expected = [];
    foreach($keys as $key) {
      $id = $this->service->getTermId($key);
      $this->assertNotNull($id,
        "We should have been able to retrieve the term based on the config_map value but we were not.");
      $this->assertGreaterThan(0, $id,
        "We expect the value returned from getTermId() to be a valid cvterm_id.");

      // Keep track of our expectations.
      // mapping of cvterm_id => expected cvterm name.
      $expected[ $id ] = $define_terms[ $key ]['name'];
    }

    $not_valid_keys = [':p', -1, 0, 'abc', 999999, '', 'lorem_ipsum', '.'];
    foreach($not_valid_keys as $n) {
      // Invalid config name key, will return 0 value.
      $v = $this->service->getTermId($n);
      $this->assertEquals($v, 0);
    }

    // Test values matched to what was loaded into the table.
    $chado = $this->chado_connection;
    foreach ($expected as $cvterm_id => $expected_cvterm_name) {
      $query = $chado->select('1:cvterm', 'cvt')
        ->fields('cvt', ['name'])
        ->condition('cvt.cvterm_id', $cvterm_id);
      $cvterm_name = $query->execute()->fetchField();
      $this->assertNotNull($cvterm_name,
        "We should have been able to retrieve the term $expected_cvterm_name using the id $cvterm_id but could not.");
      $this->assertEquals($expected_cvterm_name, $cvterm_name,
        "The name of the cvterm with the id $cvterm_id did not match the one we expected based on the config.");
    }

    // #Test saveTermConfigValues().
    // With the loadTerms above, each term configuration was set with
    // a term id number that matches a term in chado.cvterm. This test
    // will set all terms configuration to null (id: 1).

    // This would have came from form submit method.
    $config_values = [];
    foreach (array_keys($define_terms) as $key) {
      $config_values[ $key ] = 1;
    }

    $is_saved = $this->service->saveTermConfigValues($config_values);
    $this->assertTrue($is_saved,
      "We expected the saveTermConfigValues() method to return TRUE.");

    foreach($config_values as $key => $set_id) {
      // Test if all config got nulled.
      $retrieved_id = $this->service->getTermId($key);
      $this->assertEquals($set_id, $retrieved_id,
        "We expected the retrieved id to match the one we set it to but it did not.");
    }

    // Nothing to save.
    $not_saved = $this->service->saveTermConfigValues([]);
    $this->assertFalse($not_saved,
      "We should not be able to call saveTermConfigValues() with an empty array.");
  }
}
