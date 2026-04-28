<?php

namespace Drupal\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService;
use PHPUnit\Framework\Attributes\DataProvider;
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
      [
        'Cotyledon Colour',
        'Refers to the observable trait of plant cotyledon',
        'COTYCOLR',
        'Collect measurements',
        'colour',
        'Qualitative',
      ],
    ],
  ];

  /**
   * The pheno trait combo ids of the test traits after being added.
   *
   * @var array
   */
  protected array $test_trait_combo_ids = [];

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

    $service_trait = $this->container->get('trpcultivate_phenotypes.traits');

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
      $service_trait->setTraitGenus($genus);

      foreach ($traits as $trait) {
        // Key traits by trait name.
        $this->test_trait_combo_ids[$trait[0]] = $service_trait->insertTrait(array_combine($trait_keys, $trait));
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
  }

  /**
   * Data provided assign pheno combo.
   *
   * Each pheno combo test input is an array witht the following values:
   * - The trait key to fetch trait combo in the resolved test trait property.
   * - An array of pheno combo details.
   * - Exception message.
   */
  public static function providePhenoCombo() {

    return [
      [
        'Plant Height',
        [
          'label' => 'FAVOURITE TRAIT',
          'is_archived' => 0,
          'is_required' => 1,
          'was_collected' => 0,
          'was_shared' => 0,
        ],
        '',
      ],
      [
        'Days To Flower',
        ['label' => 'Flower days', 'is_required' => 1],
        '',
      ],
      [
        'Days To Flower',
        ['label' => 'Flower'],
        'trait combo already exists in the experiment',
      ],
      [
        'Cotyledon Colour',
        ['label' => 'favourite trait', 'was_collected' => 0],
        'combo label must exists, unique with in the experiment, and must not be an empty string',
      ],
      [
        'Cotyledon Colour',
        ['label' => 'cotyledon.'],
        '',
      ],
    ];
  }

  /**
   * Test assignPhenoComboToExperiment().
   *
   * @param string $trait_key
   *   The trait key to fetch trait combo in the resolved test trait property.
   * @param array $combo_experiment_details
   *   An array of pheno combo details.
   * @param string $exception_message
   *   Exception message.
   */
  #[DataProvider('providePhenoCombo')]
  public function testAssignPhenoComboToExperiment($trait_key, $combo_experiment_details, $exception_message) {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT);

    // Set experiment.
    $this->container->get('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject($experiment, array_keys($this->test_trait_combo)[0]);

    $this->service_PhenoTraitCombo->setExperiment($experiment);

    try {
      $combo_id = $this->service_PhenoTraitCombo->assignPhenoComboToExperiment(
        $this->test_trait_combo_ids[$trait_key],
        $combo_experiment_details
      );
    }
    catch (\Exception $e) {
      if ($exception_message) {
        $this->assertStringContainsString(
          $exception_message,
          $e->getMessage(),
          'Assign pheno combo failed to throw the exception message - ' . $exception_message,
        );
      }
      else {
        $pheno_combo = $this->container->get('database')->select(self::PHENO_COMBO_TABLE, 'tbl')
          ->fields('tbl')
          ->condition('tbl.combo_id', $combo_id, '=')
          ->execute()
          ->fetchAssoc();

        $this->assertGreaterThan(0, $pheno_combo, 'Failed to assign pheno combo to experiment.');
        $this->assertEquals($experiemnt, $pheno_combo['project_id'], 'Failed to assign pheno combo to experiment context.');

        foreach ($combo_experiment_details as $key => $value) {
          $this->assertEquals($value, $pheno_combo[$key], 'Failed to assign pheno combo detail - ' . $key . ' to experiment.');
        }
      }
    }
  }

  /**
   * Tes getExperimentPhenoCombo() and getAllExperimentPhenoCombos().
   */
  public function testExperimentPhenoComboGetters() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT);

    // Set experiment.
    $this->container->get('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject($experiment, array_keys($this->test_trait_combo)[0]);

    $this->service_PhenoTraitCombo->setExperiment($experiment);

    foreach ($this->test_trait_combo_ids as $trait_combo_ids) {
      $label = $this->randomString(10);
      $combo_id = $this->service_PhenoTraitCombo
        ->assignPhenoComboToExperiment($trait_combo_ids, ['label' => $label]);

      $by_trait_combo = $this->service_PhenoTraitCombo->getExperimentPhenoCombo($trait_combo_ids);
      $by_combo_id = $this->service_PhenoTraitCombo->getExperimentPhenoCombo($combo_id);
      $by_label = $this->service_PhenoTraitCombo->getExperimentPhenoCombo($label);

      $this->assertEquals($by_trait_combo[$label], $by_combo_id[$label], 'Get combo failed to fetch the expected pheno combo record.');
      $this->assertEquals($by_label[$label], $by_combo_id[$label], 'Get combo failed to fetch the expected pheno combo record.');
    }
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

      $trait_combo = array_fill_keys($arr_tmp, (($i % 2) ? [] : NULL));
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

    // trait-method-unit not found.
    try {
      $this->service_PhenoTraitCombo->sanitizeTraitCombo([
        'trait' => 'Spurious Trait',
        'method' => 'Not so methodical',
        'unit' => 'unity',
      ]);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'received trait combo that does not match a trait-method-unit record.',
        $e->getMessage(),
        'Sanitize trait combo with non-existent comno is expected to trigger a missing combo exception error.',
      );
    }

    // Sanitize trait combo compatible with pheno combo table: always cvterm id.
    $service_trait = $this->container->get('trpcultivate_phenotypes.traits');
    $service_trait->setTraitGenus(array_keys($this->test_trait_combo)[0]);

    foreach ($this->test_trait_combo_ids as $trait => $trait_combo_ids) {
      foreach ($trait_combo_alias as $alias) {
        ${$alias} = $trait_combo_ids[$alias];
      }
      $trait_combo = $service_trait->getTraitMethodUnitCombo($trait, $method, $unit);

      // Prepare trait combo where key is all ids (int), names (string) and mix.
      $trait_combo_payload = ['by_id' => [], 'by_name' => [], 'mix' => []];
      foreach ($trait_combo_alias as $alias) {
        $trait_combo_payload['by_id'][$alias] = $trait_combo[$alias]->cvterm_id;
        $trait_combo_payload['by_name'][$alias] = $trait_combo[$alias]->name;
        $trait_combo_payload['mix'][$alias] = mt_rand(0, 1) ? $trait_combo[$alias]->cvterm_id : $trait_combo[$alias]->name;
      }

      $expected_combo_ids = $trait_combo_payload['by_id'];

      foreach ($trait_combo_payload as $type => $trait_combo_payload) {
        $sanitized_trait_combo = $this->service_PhenoTraitCombo->sanitizeTraitCombo($trait_combo_payload);

        $this->assertEquals(
          $expected_combo_ids,
          $sanitized_trait_combo,
          'sanitizeTraitCombo() failed to return a sanitized trait combo with all keys resolved to cvterm ids input by: ' . $type,
        );
      }
    }
  }

  /**
   * Test sanitize combo.
   */
  public function testSanitizeCombo() {

    $broken_combo = [
      'combo id' => -999,
      'label' => '',
    ];

    foreach ($broken_combo as $type => $combo) {
      try {
        $this->service_PhenoTraitCombo->sanitizeCombo($combo);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString('Invalid ' . $type, $e->getMessage(), 'sanitizeCombo() is expected to throw an exception with invalid ' . $type);
      }
    }

    $sanitized_combo = $this->service_PhenoTraitCombo->sanitizeCombo($combo = $this->test_trait_combo_ids['Plant Height']);
    $this->assertEquals($combo, $sanitized_combo, 'sanitizedCombo() failed to sanitize combo of type array - trait combo.');

    $sanitized_combo = $this->service_PhenoTraitCombo->sanitizeCombo($combo = 99);
    $this->assertEquals($combo, $sanitized_combo, 'sanitizedCombo() failed to sanitize combo of type integer - combo id.');

    $sanitized_combo = $this->service_PhenoTraitCombo->sanitizeCombo($combo = '  Label  ');
    $this->assertEquals(trim($combo), $sanitized_combo, 'sanitizedCombo() failed to sanitize combo of type string - combo label.');
  }

  /**
   * Test sanitize status flags.
   */
  public function testSanitizeStatusFlags() {

    $status_flags = array_values($this->service_PhenoTraitCombo::PHENO_COMBO_STATUS_FLAG_MAP);

    // Zero flag provided and required flag is set to TRUE (require at least 1).
    try {
      $this->service_PhenoTraitCombo->sanitizeStatusFlags(['zero flag here'], $require_flag = TRUE);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'at least one combo status flag is required',
        $e->getMessage(),
        'sanitizeStatusFlags() is expected to throw an exception with required flag is TRUE and no flags provided.');
    }

    // A flag with value that is neither 1 nor 0.
    foreach ($status_flags as $flag) {
      try {
        $this->service_PhenoTraitCombo->sanitizeStatusFlags([$flag => 'NOT 0||1']);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'combo status has a unexpected value. Replace value to 0 or 1 in - [' . $flag . ']',
          $e->getMessage(),
          'sanitizeStatusFlags() is expected to throw an exception with unexpected flag value.');
      }
    }
  }

}
