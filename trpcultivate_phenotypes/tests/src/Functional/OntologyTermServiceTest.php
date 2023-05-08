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
  protected static $modules = ['tripal', 'tripal_chado'];

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
    $result = $ontologyService->loadTerms();
    // cv insert error when false.
    $this->assertTrue($result);

    // Find the terms if each one was inserted correctly.
    $terms = [  
      [ 
        'name' => 'genus',
        'cv' => 'taxonomic_rank',
      ],
      [ 
        'name' => 'unit',
        'cv' => 'uo',
      ],
      [
        'name' => 'related',
        'cv' => 'synonym_type',
      ],
      [      
        'name' => 'Year',
        'cv' => 'tripal_pub',
      ],
      [
        'name' => 'method',
        'cv' => 'NCIT',
      ],
      [
        'name' => 'location',
        'cv' => 'NCIT',
      ],
      [
        'name' => 'replicate',
        'cv' => 'NCIT',
      ],
      [
        'name' => 'Collected By',
        'cv' => 'NCIT',
      ],
      [
        'name' => 'Entry',
        'cv' => 'NCIT',
      ],
      [
        'name' => 'name',
        'cv' => 'NCIT',
      ],
      [
        'name' => 'plot',
        'cv' => 'AGRO',
      ]
    ];    
    
    \Drupal::state()->set('is_a_test_environment', TRUE);
    $chado = \Drupal::service('tripal_chado.database');

    foreach($terms as $term) {
      list($cv_term, $cv) = array_values($term);

      $id = $chado->query("
        SELECT t2.cvterm_id FROM {1:cv} AS t1 LEFT JOIN {1:cvterm} AS t2 USING(cv_id)
        WHERE t1.name = :cv AND t2.name = :term LIMIT 1
      ", [':cv' => $cv, ':term' => $cv_term])
        ->fetchField();

      $this->assertNotNull($id);
    }
  } 
}