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
    $ontologyService = new TripalCultivatePhenotypesOntologyService();
    $terms = $ontologyService->defineTerms();

    // Test Load terms with custom cv-term set.
    $id = uniqid();

    $terms[ 'cv' . $id ] = [
      'name' => 'cv' . $id,
      'definition' => 'cv definition',

      'terms' => [
        [
          'id' => 'cv' . $id . ':term' . $id,
          'name' => 'term' . $id,
          'definition' => 'term definition',
        ]
      ],

    ];

    $result = $ontologyService->loadTerms($terms);
    // cv insert error when false.
    $this->assertTrue($result);

    // Find the terms if each one was inserted correctly.
    \Drupal::state()->set('is_a_test_environment', TRUE);
    $chado = \Drupal::service('tripal_chado.database');

    $query_term = "SELECT t2.cvterm_id 
      FROM {1:cv} AS t1 LEFT JOIN {1:cvterm} AS t2 USING(cv_id)
      WHERE t1.name = :cv AND t2.name = :term LIMIT 1";
    
    foreach($terms as $term) {
      foreach($term['terms'] as $t)
      list($id, $cv_term, $cv) = array_values($t);

      $id = $chado->query($query_term, [':cv' => $cv, ':term' => $cv_term])
        ->fetchField();

      $this->assertNotNull($id);
    }
    
    // Test set trait - ontology.
    $m = $ontologyService->setTraitOntology();
    var_dump($m);
  } 
}