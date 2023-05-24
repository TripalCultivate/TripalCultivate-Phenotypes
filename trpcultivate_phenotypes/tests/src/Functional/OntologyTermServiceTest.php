<?php

/**
 * @file
 * Unit test of Ontology/Term service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Unit;

use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesOntologyService;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\BrowserTestBase;

 /**
  *  Class definition OntologyServiceTest
  *
  * @group trpcultivate_phenotypes
  */
class OntologyTermServiceTest extends BrowserTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['tripal', 'tripal_chado', 'trpcultivate_phenotypes'];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';
  
  /**
   * Initialization of container, configurations, service 
   * and service class required by the test.
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Test functionality.
   */  
  public function testOntologyService() {
    \Drupal::state()->set('is_a_test_environment', TRUE);
    $chado = \Drupal::service('tripal_chado.database');
    $chado->setSchemaName('chado');

    $ontologyService = new TripalCultivatePhenotypesOntologyService();
    $terms = $ontologyService->defineTerms();

    // At this point terms were created and inserted into chado.cvterm.
    // cv information was created if required.
    $result = $ontologyService->loadTerms($terms);    
    // Test load/insert was successful.
    // cv insert error when false.
    $this->assertTrue($result);

    $all_terms = [];
    $query_term = "SELECT t2.cvterm_id 
      FROM {1:cv} AS t1 LEFT JOIN {1:cvterm} AS t2 USING(cv_id)
      WHERE t1.name = :cv AND t2.name = :term LIMIT 1";

    foreach($terms as $cv) {
      foreach($cv['terms'] as $term) {
        $id = $chado->query($query_term, [':cv' => $cv['name'], ':term' => $term['name']])
          ->fetchField();

        /*
        $cvterm_row = [
          'name' => $term['name'],
          'cv_id' => ['name' => $cv['name']]
        ];
  
        $cvterm = (function_exists('chado_get_cvterm')) 
          ? chado_get_cvterm($cvterm_row) : tripal_get_cvterm($cvterm_row);
        */
        var_dump($id);
      }
    }

    /*
    $query_term = "SELECT t2.cvterm_id 
      FROM {1:cv} AS t1 LEFT JOIN {1:cvterm} AS t2 USING(cv_id)
      WHERE t1.name = :cv AND t2.name = :term LIMIT 1";
    
    $all_terms = [];
    foreach($terms as $term) {
      foreach($term['terms'] as $t) {
        list($config, $cvterm_id, $cv_term,) = array_values($t);

        // Test term created/inserted.
        $id = $chado->query($query_term, [':cv' => $term['name'], ':term' => $cv_term])->fetchField();
        $this->assertNotNull($id);
        
        // Config term has values term configuration variable value and
        // the cvterm it maps to. 
        if (!empty($config)) {
          $all_terms[ $config ] = [
            '#config_value' => $id,
            '#config_term'  => $cv_term
          ];
        }
      }
    }
  
    var_dump($all_terms);
    */
    // Test mapTerms().
    // Test each entry in the map corresponds to a value in terms definition
    // and has a value (0) by default.
    //$map = $ontologyService->mapDefaultTermToConfig();

    //foreach($map as $conf => $config_values) {
      //$is_config = (in_array($conf, $all_terms)) ? TRUE : FALSE;
      //$this->assertTrue($is_config, $conf);

      //$this->assertEquals($config_values['#config_value'], 0);
    //}
  } 
}