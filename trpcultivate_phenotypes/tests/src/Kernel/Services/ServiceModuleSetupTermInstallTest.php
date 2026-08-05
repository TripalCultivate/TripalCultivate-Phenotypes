<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the setup of the trpcultivate_phenotypes module.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceModuleSetupTermInstallTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'path',
    'path_alias',
    'system',
    'user',
    'views',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig([
      'tripal_chado',
      'trpcultivate_phenotypes',
      'trpcultivate',
    ]);

    $this->container->get('trpcultivate.setup_module_service')
      ->installTerms();

    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    $default_terms = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.default_terms.term_set');

    $terms = [];
    foreach ($default_terms as $cv) {
      foreach ($cv['terms'] as $term_set) {
        $term_set['cv'] = ['name' => $cv['name'], 'definition' => $cv['definition']];
        $terms[$term_set['config_map']] = $term_set;
      }
    }

    $mock_terms_service = $this->getMockBuilder(TripalCultivatePhenotypesTermsService::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('tripal_chado.chado_buddy'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['defineTerms'])
      ->getMock();

    $mock_terms_service->method('defineTerms')
      ->willReturn($terms);

    $genus = 'Lens';
    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $genus,
        'species' => $this->getRandomGenerator()->word(10),
        'type_id' => 1,
      ])
      ->execute();

    $config_genus = $this->setOntologyConfig($genus);

    $genus_config = [];
    foreach ($config_genus as $config => $config_value) {
      $genus_config[$config] = ($config == 'database') ? $config_value['db_id'] : $config_value['cv_id'];
    }

    $mock_return_map[$genus] = $genus_config;

    $mock_ontology_service = $this->getMockBuilder(TripalCultivatePhenotypesGenusOntologyService::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('tripal_chado.database'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['getGenusOntologyConfigValues'])
      ->getMock();

    $mock_ontology_service->method('getGenusOntologyConfigValues')
      ->willReturnMap([
        [$genus, $mock_return_map[$genus]],
      ]);

    $this->container->set('trpcultivate_phenotypes.genus_ontology', $mock_ontology_service);
  }

  /**
   * Test installOntologyTerms() method.
   */
  public function testInstallOntologyTerms() {
    // Call the installOntologyTerms() method.
    \Drupal::service('trpcultivate_phenotypes.setup_module_service')
      ->installOntologyTerms();

    // Call defineTerms in Term Service.
    $terms = \Drupal::service('trpcultivate_phenotypes.terms')
      ->defineTerms();

    foreach ($terms as $term) {
      // Check if the term exists in the database.
      $exists = $this->chado_connection->query('SELECT * FROM {1:cvterm} WHERE name = :name', [':name' => $term['name']])->fetchField();

      // Assert that the term exists.
      $this->assertNotEmpty($exists, "The term '{$term['name']}' should exist in the database.");
    }
  }

}
