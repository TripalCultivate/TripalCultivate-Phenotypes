<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Plugin\TripalImporter\TripalCultivatePhenotypesTraitsImporter;

/**
 * Tests the functionality of the run() method of the Traits Importer.
 *
 * @group traitsImporter
 */
class TraitImporterRunTest extends ChadoTestKernelBase {

  use UserCreationTrait;
  use PhenotypeImporterTestTrait;

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * Our instance of the Traits Importer for testing.
   *
   * @var Drupal\trpcultivate_phenotypes\Plugin\TripalImporter\TripalCultivatePhenotypesTraitsImporter
   */
  protected TripalCultivatePhenotypesTraitsImporter $importer;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Saves details regarding the config.
   *
   * @var array
   */
  protected array $cvdbon;

  /**
   * The terms required by this module mapped to the cvterm_ids they are set to.
   *
   * @var array
   */
  protected array $terms;

  /**
   * A default listing of annotations associated with our importer.
   *
   * @var array
   */
  protected $definitions = [
    'test-trait-importer' => [
      'id' => 'trpcultivate-phenotypes-traits-importer',
      'label' => 'Tripal Cultivate: Phenotypic Trait Importer',
      'description' => 'Loads Traits for phenotypic data into the system. This is useful for large phenotypic datasets to ease the upload process.',
      'file_types' => ["tsv", "txt"],
      'use_analysis' => FALSE,
      'require_analysis' => FALSE,
      'upload_title' => 'Phenotypic Trait Data File*',
      'upload_description' => 'This should not be visible!',
      'button_text' => 'Import',
      'file_upload' => TRUE,
      'file_load' => FALSE,
      'file_remote' => FALSE,
      'file_required' => FALSE,
      'cardinality' => 1,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Open connection to Chado.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Ensure we can access file_managed related functionality from Drupal.
    // ... users need access to system.action config?
    $this->installConfig(['system', 'trpcultivate_phenotypes', 'trpcultivate']);
    // ... managed files are associated with a user.
    $this->installEntitySchema('user');
    // ... Finally the file module + tables itself.
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('tripal_chado', ['tripal_custom_tables']);
    // Ensure we have our tripal import tables.
    $this->installSchema('tripal', ['tripal_import', 'tripal_jobs']);
    // Create and log-in a user.
    $this->setUpCurrentUser();

    // We need to mock the logger to test the progress reporting.
    $container = \Drupal::getContainer();
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['notice', 'error'])
      ->getMock();
    $mock_logger->method('notice')
      ->willReturnCallback(function ($message, $context, $options) {
         print str_replace(array_keys($context), $context, $message);
         return NULL;
      });
    $mock_logger->method('error')
      ->willReturnCallback(function ($message, $context, $options) {
        print str_replace(array_keys($context), $context, $message);
        return NULL;
      });
    $container->set('tripal.logger', $mock_logger);

    // Create our organism and configure it.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => 'Tripalus',
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");

    $this->cvdbon = $this->setOntologyConfig('Tripalus');
    $this->terms = $this->setTermConfig();

    $this->importer = new TripalCultivatePhenotypesTraitsImporter(
      [],
      'trpcultivate-phenotypes-traits-importer',
      $this->definitions,
      $this->chado_connection,
      $this->container->get('trpcultivate_phenotypes.genus_ontology'),
      $this->container->get('trpcultivate_phenotypes.traits'),
      $this->container->get('plugin.manager.trpcultivate_phenotypes_validator'),
      $this->container->get('plugin.manager.trpcultivate_validator'),
      $this->container->get('trpcultivate.template_generator'),
      $this->container->get('entity_type.manager'),
      $this->container->get('renderer'),
      $this->container->get('messenger'),
    );
  }

  /**
   * Tests the run() function using a simple example file.
   *
   * Example file located at:
   *   tests/src/Fixtures/TraitImporterFiles/simple_example.txt.
   */
  public function testTraitImporterRunSimple() {

    $file = $this->createTestFile([
      'filename' => 'simple_example.txt',
      'content' => ['file' => 'TraitImporterFiles/simple_example.txt'],
    ]);

    $genus = 'Tripalus';
    $run_args = ['genus' => $genus];
    $file_details = ['fid' => $file->id()];

    $this->importer->createImportJob($run_args, $file_details);
    $this->importer->prepareFiles();
    $this->importer->run();
    $this->importer->postRun();

  }

}
