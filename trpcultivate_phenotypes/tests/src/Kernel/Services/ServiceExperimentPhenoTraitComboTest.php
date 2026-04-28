<?php

namespace Drupal\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests associated with the Experiment pheno trait combo Service.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceExperimentPhenoTraitComboTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;

  /**
   * The table name that holds the experiment trait combos.
   *
   * @var string
   */
  const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Experiment name context.
   *
   * @var string
   */
  const EXPERIMENT_NAME_CONTEXT = 'Project Awesome';

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
   * PhenoTraitCombo service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Services\ExperimentPhenoTraitComboService
   */
  protected ExperimentPhenoTraitComboService $service_PhenoTraitCombo;

  /**
   * A test plant trait combo.
   *
   * @var array
   */
  protected array $test_trait_combo = [
    'Lens' => [
      [
        'Plant Height',
        'Total vertical height of the vegetative part of the plant.',
        'PLANT_HEIGHT',
        'Measure the height',
        'meters',
        'Quantitative',
      ],
      [
        'Days To Flower',
        'The number of days elapsed from sowing to the appearance of flower.',
        'DTF',
        'Count the days',
        'days',
        'Quantitative',
      ],
    ],
  ];

  /**
   * Tripal Logger log message.
   *
   * @var string
   */
  private string $log_message = '';

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

    $this->installSchema('trpcultivate_phenotypes', [self::PHENO_COMBO_TABLE]);
    $this->installEntitySchema('user');

    $this->container->get('trpcultivate.setup_module_service')
      ->installTerms();

    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    $this->container->get('trpcultivate.setup_module_service')
      ->importContenttypes();

    $this->setTermConfig();

    // Create research experiment content.
    $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => self::EXPERIMENT_NAME_CONTEXT])
      ->execute();

    // Insert traits.
    $trait_keys = [
      'Trait Name',
      'Trait Description',
      'Method Short Name',
      'Collection Method',
      'Unit',
      'Type',
    ];

    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');

    foreach ($this->test_trait_combo as $genus => $traits) {
      $this->chado_connection->insert('1:organism')
        ->fields(['genus', 'species', 'type_id'])
        ->values([
          'genus' => $genus,
          'species' => $this->randomString(10),
          'type_id' => $null_term = 1,
        ])
        ->execute();

      $this->setOntologyConfig($genus);
      $trait_service->setTraitGenus($genus);

      foreach ($traits as $trait) {
        $trait_service->insertTrait(array_combine($trait_keys, $trait));
      }
    }

    // Mock Tripal Logger.
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['error'])
      ->getMock();

    $mock_logger->method('error')
      ->willReturnCallback(function ($message) {
        $this->log_message = $message;
        return NULL;
      }
    );

    $this->container->set('tripal.logger', $mock_logger);
    $this->service_PhenoTraitCombo = $this->container->get('trpcultivate_phenotypes.pheno_combo');
  }

  /**
   * Test experiment context setter method.
   */
  public function testSetExperiment() {

    // Invalid experiment identifier.
    foreach (['Experiment Spurious', 999] as $experiment) {
      try {
        $this->service_PhenoTraitCombo->setExperiment($experiment);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'received experiment identifier - ' . $experiment . ', that does not exist.',
          $e->getMessage(),
          'Setting invalid experiment context is expected to trigger invalid indentifier exception message.',
        );
      }
    }

    // Experiment has no genus.
    $experiment = self::EXPERIMENT_NAME_CONTEXT;

    try {
      $this->service_PhenoTraitCombo->setExperiment($experiment);
    }
    catch (\Exception $e) {
      foreach ([$this->log_message, $e->getMessage()] as $error_message) {
        $this->assertStringContainsString(
          'received experiment identifier - ' . $experiment . ', that is not configured with a Genus.',
          $error_message,
          'Setting experiment context not paired with genus is expected to trigger invalid indentifier exception message.',
        );
      }
    }

    // Set experiment.
    $this->container->get('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject(
        ChadoProjectAutocompleteController::getProjectId($experiment),
        array_keys($this->test_trait_combo)[0]
      );

    $this->service_PhenoTraitCombo->setExperiment($experiment);
  }

  /**
   * Test sanitize trait combo.
   */
  public function testSanitizeTraitCombo() {

    $trait_combo_alias = array_keys($this->service_PhenoTraitCombo::TRAIT_COMBO_KEY_MAP);

    // Missing alias key.
    foreach ($trait_combo_alias as $i => $alias) {
      $arr_tmp = $trait_combo_alias;
      unset($arr_tmp[$i]);

      $trait_combo = array_fill_keys($arr_tmp, $this->randomString(10));
      try {
        $this->service_PhenoTraitCombo->sanitizeTraitCombo($trait_combo);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Value must have keys [' . implode(', ', $trait_combo_alias) . ']. You provided [' . implode(', ', $arr_tmp) . ']',
          $e->getMessage(),
          'Sanitize trait combo with missing key is expected to trigger a missing key exception error.',
        );
      }
    }

    // Unexpected value - not integer or string.
    foreach ($trait_combo_alias as $i => $alias) {
      $arr_tmp = $trait_combo_alias;
      $arr_tmp[$i] = ($i % 2) ? [] : NULL;

      try {
        $this->service_PhenoTraitCombo->sanitizeTraitCombo($trait_combo);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'received trait combo with invalid data type for key(s) - [' . implode(', ', $arr_tmp) . ']',
          $e->getMessage(),
          'Sanitize trait combo with missing key is expected to trigger a missing key exception error.',
        );
      }
    }
  }

}
