<?php

/**
 * @file
 * Kernel test of Genus Project service.
 */

 namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

 use Drupal\KernelTests\KernelTestBase;
 use Drupal\tripal\Services\TripalLogger;

 /**
  * Test Tripal Cultivate Phenotypes Genus Project service.
  *
  * @group trpcultivate_phenotypes
  */
class ServiceGenusProjectTest extends KernelTestBase {
  protected $service;

  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['trpcultivate_phenotypes']);
    
    $this->service = \Drupal::service('trpcultivate_phenotypes.genus_project');
  }

  public function testGenusProjectService() {
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Class created.
    $this->assertNotNull($this->service);
    
  }
}
