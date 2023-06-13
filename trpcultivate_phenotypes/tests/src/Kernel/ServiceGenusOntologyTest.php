<?php

/**
 * @file
 * Kernel test of Genus Ontology service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\KernelTests\KernelTestBase;

/**
  *  Class definition ServiceGenusOntologyTest.
  */
class ServiceGenusOntologyTest extends KernelTestBase {
  protected $service;
  public static $modules = [
   'tripal', 'trpcultivate_phenotypes'  
  ];

  protected function setUp() {
    parent::setUp();
    $this->installConfig(['trpcultivate_phenotypes']);

    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
  }

  public function testGenusOntology() {
    $m = $this->service->defineGenusOntology();
    var_dump($m);
  }
}