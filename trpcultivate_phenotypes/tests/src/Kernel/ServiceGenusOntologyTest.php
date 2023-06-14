<?php

/**
 * @file
 * Kernel test of Genus Ontology service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\tripal\Services\TripalLogger;

/**
  *  Class definition ServiceGenusOntologyTest.
  */
class ServiceGenusOntologyTest extends KernelTestBase {
  protected $service;
  
  protected static $modules = [
   'tripal', 
   'tripal_chado',
   'trpcultivate_phenotypes'  
  ];

  protected function setUp() {
    parent::setUp();
    $this->installConfig(['trpcultivate_phenotypes']);

    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
  }

  public function testGenusOntologyService() {
    \Drupal::state()->set('is_a_test_environment', TRUE);
    // This line will create install schema.
    $this->installSchema('tripal_chado', ['chado_installations']);
   
    // Class created.
    $this->assertNotNull($this->service);

    // Test when no genus in host Tripal site.
    $no_genus = $this->service->defineGenus();
    $is_empty = (empty($define_genusontology)) ? TRUE : FALSE;


    



    // Create genus records since a clean Tripal site has 
    // no organism/genus records and re-run routines carried out
    // during install process. 

    // Created genus of type null (id: 1).
    $test_genus = ['Lens', 'Cicer'];
    $chado = \Drupal::service('tripal_chado.database');
    $ins_genus = "
      INSERT INTO {1:organism} (genus, species, type_id)
      VALUES 
        ('$test_genus[0]', 'culinaris', 1), 
        ('$test_genus[1]', 'arientinum', 1)
    ";

    $chado->query($ins_genus);

    // #Test defineGenusOntology().
    $define_genusontology = $this->service->defineGenusOntology();
    $this->assertNotNull($define_genusontology);
    // Is an array.
    $is_array = (is_array($define_genusontology)) ? TRUE : FALSE;
    $this->assertTrue($is_array);

    foreach($test_genus as $g) {
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


  }
}