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
      'data_collector' => 0,
      'entry' => 0, 
      'genus' => 0, 
      'location' => 0, 
      'method' => 0,
      'name' => 100, // Test this value below.
      'experiment_container' => 0,
      'related' => 0,
      'experiment_replicate' => 0,
      'unit' => 0,
      'experiment_year' => 0,
     ];

    $config_map = [
      'trpcultivate_phenotypes.settings' => [
        'trpcultivate' => [
          'phenotypes' => [
            'ontology' => [
              'terms' => $this->term_values
            ]
          ],
          'default_terms' => [
            'term_set' => [
              0 => [
                'name' => 'taxonomic_rank',
                'definition' => 'A vocabulary of taxonomic ranks (species, family, phylum, etc).',
                'terms' => [
                  0 => [
                    'config_map' => 'genus',
                    'id' => 'TAXRANK:0000005',
                    'name' => 'genus',
                    'definition' => 'The genus'
                  ]
                ]
              ],
              1 => [
                'name' => 'uo',
                'definition' => 'Units of Measurement Ontology.',
                'terms' => [
                  0 => [  
                    'config_map' => 'unit',
                    'id' => 'UO:0000000',
                    'name' => 'unit',
                    'definition' => 'Unit of measurement'
                  ]
                ]
              ],
              2 => [
                'name' => 'synonym_type',
                'definition' => 'A local vocabulary added for synonynm types.',
                'terms' => [
                  0 => [
                    'config_map' => 'related',
                    'id' => 'internal:related',
                    'name' => 'related',
                    'definition' => 'Is related to.'
                  ]
                ]
              ],
              3 => [
                'name' => 'tripal_pub',
                'definition' => 'Tripal Publication Ontology. A temporary ontology until a more formal appropriate ontology to be identified.',
                'terms' => [
                  0 => [
                    'config_map' => 'experiment_year',
                    'id' => 'TPUB:0000059',
                    'name' => 'Year',
                    'definition' => 'The year the work was published. This should be a 4 digit year.'
                  ]
                ]
              ],
              4 => [
                'name' => 'NCIT',
                'definition' => 'The NCIT OBO Edition project aims to increase integration of the NCIt with OBO Library ontologies NCIt is a reference terminology that includes broad coverage of the cancer domain, including cancer related diseases, findings and abnormalities. NCIt OBO Edition releases should be considered experimental.',
                'terms' => [
                  0 => [
                    'config_map' => 'method',
                    'id' => 'NCIT:C71460',
                    'name' => 'method',
                    'definition' => 'A means, manner of procedure, or systematic course of action that have to be performed in order to accomplish a particular goal.'
                  ],
                  1 => [
                    'config_map' => 'location',
                    'id' => 'NCIT:C25341',
                    'name' => 'Location',
                    'definition' => 'A position, site, or point in space where something can be found.'
                  ],
                  2 => [
                    'config_map' => 'experiment_replicate',
                    'id' => 'NCIT:C28038',
                    'name' => 'replicate',
                    'definition' => 'A role played by a biological sample in the context of an experiment where the intent is that biological or technical variation is measured.'
                  ],
                  3 => [
                    'config_map' => 'data_collector',
                    'id' => 'NCIT:C45262',
                    'name' => 'Collected By',
                    'definition' => 'Indicates the person, group, or institution who performed the collection act.'
                  ],
                  4 => [
                    'config_map' => 'entry',
                    'id' => 'NCIT:C43381',
                    'name' => 'Entry',
                    'definition' => 'An item inserted in a written or electronic record.'
                  ],
                  5 => [  
                    'config_map' => 'name',
                    'id' => 'NCIT:C42614',
                    'name' => 'name',
                    'definition' => 'The words or language unit by which a thing is known.'
                  ]
                ]
              ],
              5 => [
                'name' => 'AGRO',
                'definition' => 'Agricultural experiment plot',
                'terms' => [
                  0 => [
                    'config_map' => 'experiment_container',
                    'id' => 'AGRO:00000301',
                    'name' => 'plot',
                    'definition' => 'A site within which an agricultural experimental process is conducted'
                  ]
                ] 
              ]
            ]
          ],
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

    $define_terms = $service->defineTerms();
    $this->assertNotNull($define_terms);
    // Is an array.
    $is_array = (is_array($define_terms)) ? TRUE : FALSE;
    $this->assertTrue($is_array);

    // Get all the terms by name and which configuration name
    // it maps to.
    $map = [];
    foreach($define_terms as $config => $config_prop) {
      $map[ $config ] = $config_prop[ 'name' ];
    }

    // Get term by configuration entity config_map value.
    $config_map_values = array_keys($define_terms);
    foreach($config_map_values as $config_name) {
      $this->assertEquals($define_terms[ $config_name ]['name'], $map[ $config_name ]); 
    }
    
    // Test all terms are in and all values except name are set to 0.
    unset($config_name);
    foreach($config_map_values as $config_name) {
      $has_value = $service->getTermId($config_name);
      $this->assertNotNull($has_value);

      if ($config_name == 'name') {
        // Is 100.
        $this->assertEquals($has_value, 100);
      }
    }
  } 
}