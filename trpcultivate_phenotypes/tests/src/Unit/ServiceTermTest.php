<?php

/**
 * @file
 * Unit test of Terms service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Unit;

use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
  *  Class definition ServiceTermTest.
  *
  * @coversDefaultClass Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService
  * @group trpcultivate_phenotypes
  */
class ServiceTermTest extends UnitTestCase {
  protected $config;
  private $term_values;
  
  protected function setUp(): void {
    parent::setUp();

     $this->term_values = [
      'collector' => 0,
      'entry' => 0, 
      'genus' => 0, 
      'location' => 0, 
      'method' => 0,
      'name' => 100, // Test this value below.
      'plot' => 0,
      'related' => 0,
      'replicate' => 0,
      'unit' => 0,
      'year' => 0,
     ];

    $config_map = [
      'trpcultivate_phenotypes.settings' => [
        'trpcultivate' => [
          'phenotypes' => [
            'ontology' => [
              'terms' => $this->term_values
            ]
          ]
        ]
      ]
    ];

    $this->config = $this->getConfigFactoryStub($config_map);
  
    // Create container.
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->config);
    \Drupal::setContainer($container);
  }

  /**
   * Test service.
   */  
  public function testService() {
    $service = new TripalCultivatePhenotypesTermsService($this->config);
    // Class created.
    $this->assertNotNull($service);

    // Define terms.
    $define_terms = $service->defineTerms();
    $this->assertNotNull($define_terms);
    // Is an array.
    $is_array = (is_array($define_terms)) ? TRUE : FALSE;
    $this->assertTrue($is_array);

    // Get all terms - config value map.
    $terms_to_config = $service->mapDefaultTermToConfig();
    
    // Test all terms are in and all values except name are set to 0.
    $config_names = array_keys($this->term_values);
    foreach($terms_to_config as $term => $config) {
      $is_in = (in_array($config, $config_names)) ? TRUE : FALSE;
      $this->assertTrue($is_in);

      $has_value = $service->getTermConfigValue($term);
      $this->assertNotNull($has_value);

      if ($term == 'name') {
        // Is 100.
        $this->assertEquals($has_value, 100);
      }
    }
  } 
}