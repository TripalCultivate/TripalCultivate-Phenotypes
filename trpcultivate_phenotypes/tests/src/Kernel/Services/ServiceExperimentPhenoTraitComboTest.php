<?php

namespace Drupal\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
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
   * Experiment name context - has experiment pheno combo.
   *
   * @var string
   */
  const EXPERIMENT_NAME_CONTEXT_WITH_COMBO = 'Project Awesome';

  /**
   * Experiment name context - no experiment pheno combo.
   *
   * @var string
   */
  const EXPERIMENT_NAME_CONTEXT_NO_COMBO = 'Project Not So Awesome';

  /**
   * Pheno combo alias to table field (id) mapping.
   *
   * @var array
   */
  const TRAIT_COMBO_KEY_MAP = [
    'trait' => 'attr_id',
    'method' => 'observable_id',
    'unit' => 'unit_id',
  ];

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
        'Refers to the observable trait of plant cotyledon.',
        'COTYCOLR',
        'Collect measurements',
        'colour',
        'Qualitative',
      ],
    ],
    'Triticum' => [
      [
        'Biomas',
        'The total mass of plant-based or organic matter.',
        'B-MASS',
        'Use quadrant (a marked frame) to define a specific area',
        'kilogram',
        'Quantitative',
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
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $exp_entity;

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

    $config_terms = $this->setTermConfig();

    // Create test experiment context where one has pheno combo while the other
    // has none.
    $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO])
      ->values(['name' => self::EXPERIMENT_NAME_CONTEXT_NO_COMBO])
      ->execute();

    $experiment_id = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);

    // Create a tripal entity.
    $entity = TripalEntity::create([
      'id' => 1,
      'type' => 'research_experiment',
      'label' => self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO,
    ]);

    $entity->set('exp_name', ['record_id' => $experiment_id, 'value' => self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO]);
    $this->exp_entity = $entity;

    // Create configured organism and relate to experiment context with combo.
    $rank = 0;
    foreach ($this->test_trait_combo as $genus => $traits) {
      $this->chado_connection->insert('1:organism')
        ->fields(['genus', 'species', 'type_id'])
        ->values([
          'genus' => $genus,
          'species' => $this->randomString(),
          'type_id' => $null_term = 1,
        ])
        ->execute();

      $this->setOntologyConfig($genus);

      // Set genus to experiment context with combo.
      $this->chado_connection->insert('1:projectprop')
        ->fields([
          'project_id' => $experiment_id,
          'type_id' => $config_terms['genus'],
          'value' => $genus,
          'rank' => $rank++,
        ])
        ->execute();
    }

    // Create traits and assign pheno combo.
    $service_trait = $this->container->get('trpcultivate_phenotypes.traits');
    $drupaldb_connection = $this->container->get('database');

    // Insert traits.
    $trait_keys = [
      'Trait Name',
      'Trait Description',
      'Method Short Name',
      'Collection Method',
      'Unit',
      'Type',
    ];

    foreach ($this->test_trait_combo as $genus => $traits) {
      $service_trait->setTraitGenus($genus);

      foreach ($traits as $i => $trait) {
        $this->test_trait_combo_ids[$genus][] = $service_trait->insertTrait(array_combine($trait_keys, $trait));

        // Only traits in Lens are installed and assigned to experiment.
        if ($genus != 'Lens') {
          break;
        }

        $field_combo_ids = [];
        foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
          $field_combo_ids[$field] = $this->test_trait_combo_ids[$genus][$i][$alias];
        }

        // Insert a pheno combo - default all status flags to 0.
        $drupaldb_connection->insert(self::PHENO_COMBO_TABLE)
          ->fields([
            'project_id' => $experiment_id,
            'uid' => $admin_user = 1,
            'label' => $this->randomString(),
            'timestamp' => time(),
          ] + $field_combo_ids)
          ->execute();
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
   * Provide experiment context.
   *
   * Each test experiment context in an array with the following values:
   * - The experiment name or id number to set as the experiment context.
   * - The exception message thrown.
   */
  public static function provideExperimentContext() {

    $does_not_exists = 'Failed to set experiment context. The specified experiment entity/id/name: %s does not exist.';

    return [
      ['Experiment Spurious', $does_not_exists],
      [0, $does_not_exists],
      [123, $does_not_exists],
      [
        self::EXPERIMENT_NAME_CONTEXT_NO_COMBO,
        'Failed to set experiment context. The specified experiment entity/id/name: %s is not configured with a genus.',
      ],
      [
        self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO,
        '',
      ],
      [
        'TripalEntity',
        '',
      ],
    ];
  }

  /**
   * Test setExperiment().
   *
   * @param int|string $experiment
   *   The experiment name or id number to set as the experiment context.
   * @param string $exception_message
   *   The exception message thrown.
   *
   * @dataProvider provideExperimentContext
   */
  #[DataProvider('provideExperimentContext')]
  public function testSetExperiment(int|string $experiment, string|null $exception_message) {

    try {
      $this->service_PhenoTraitCombo->setExperiment(
        ($experiment == 'TripalEntity') ? $this->exp_entity : $experiment
      );
    }
    catch (\Exception $e) {
      $exp_input = ($experiment instanceof TripalEntity) ? $experiment->label() : $experiment;

      if (!is_null($exception_message)) {
        $exception_message = sprintf($exception_message, $exp_input);
      }

      $this->assertEquals($exception_message, $e->getMessage(), 'Set experiment failed to throw an exception for tests:' . $exp_input);
    }
  }

  /**
   * Test assignPhenoComboToExperiment().
   */
  public function testAssignPhenoComboToExperiment() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);
    $this->service_PhenoTraitCombo->setExperiment($experiment);

    $combo_details = [];
    $combo_details['label'] = $this->randomString();

    foreach ($this->service_PhenoTraitCombo::PHENO_COMBO_STATUS_FLAG_MAP as $field) {
      $combo_details[$field] = (int) mt_rand(0, 1);
    }

    // Traits in Lens genus have been assigned to the experiment.
    foreach ($this->test_trait_combo_ids['Lens'] as $trait_combo) {
      try {
        $this->service_PhenoTraitCombo->assignPhenoComboToExperiment($trait_combo, $combo_details);
      }
      catch (\Exception $e) {
        $this->assertEquals(
          'Failed to assign trait combo. The trait combo is already in use in the experiment.',
          $e->getMessage(),
          'Assignment of an existing trait combo is expected to throw an exception.'
        );
      }
    }

    // Traits in genus other than Lens have not been assigned to an experiment.
    foreach ($this->test_trait_combo_ids as $genus => $trait_combo_ids) {
      if ($genus == 'Lens') {
        continue;
      }

      foreach ($trait_combo_ids as $trait_combo) {
        $combo_id = $this->service_PhenoTraitCombo->assignPhenoComboToExperiment($trait_combo, $combo_details);

        $pheno_combo = $this->container->get('database')->select(self::PHENO_COMBO_TABLE, 'tbl')
          ->fields('tbl')
          ->condition('tbl.combo_id', $combo_id, '=')
          ->execute()
          ->fetchAssoc();

        $this->assertGreaterThan(0, $combo_id, 'Failed to assign pheno combo to experiment.');
        $this->assertEquals($experiment, $pheno_combo['project_id'], 'Failed to assign pheno combo to experiment context.');

        foreach ($combo_details as $key => $value) {
          $this->assertEquals($value, $pheno_combo[$key], 'Failed to assign pheno combo detail - ' . $key . ' to experiment.');
        }
      }
    }
  }

  /**
   * Test getExperimentPhenoCombo().
   */
  public function testGetExperimentPhenoCombo() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);
    $this->service_PhenoTraitCombo->setExperiment($experiment);

    $drupaldb_connection = $this->container->get('database');

    $assigned_combos = $drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchAllAssoc('combo_id');

    foreach ($assigned_combos as $exp_phenocombo) {
      $exp_phenocombo = (array) $exp_phenocombo;
      $combo_by_id = $this->service_PhenoTraitCombo->getExperimentPhenoCombo($exp_phenocombo['combo_id']);
      $this->assertEquals($exp_phenocombo, $combo_by_id, 'Returned experiment combo does not match combo returned using combo id.');

      $combo_by_label = $this->service_PhenoTraitCombo->getExperimentPhenoCombo($exp_phenocombo['label']);
      $this->assertEquals($exp_phenocombo, $combo_by_label, 'Returned experiment combo does not match combo returned using combo label.');

      $trait_combo = [];
      foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
        $trait_combo[$alias] = $exp_phenocombo[$field];
      }

      $combo_by_trait_combo = $this->service_PhenoTraitCombo->getExperimentPhenoCombo($trait_combo);
      $this->assertEquals($exp_phenocombo, $combo_by_trait_combo, 'Returned experiment combo does not match combo returned using trait combo.');
    }
  }

  /**
   * Test getAllExperimentCombos().
   */
  public function testGetAllExperimentPhenoCombos() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);
    $this->service_PhenoTraitCombo->setExperiment($experiment);

    $drupaldb_connection = $this->container->get('database');

    $assigned_combos = $drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchAllAssoc('label');

    // Get pheno combos and using format - full.
    $exp_phenocombos = $this->service_PhenoTraitCombo->getAllExperimentPhenoCombos(options: ['format' => 'full']);
    $exp_phenocombos_clone = $this->service_PhenoTraitCombo->getAllExperimentPhenoCombos();

    $this->assertEquals(
      $exp_phenocombos,
      $exp_phenocombos_clone,
      'Failed to return expected experiment combos using full format option or unspecified format.',
    );

    $this->assertCount(
      $combo_count = count($assigned_combos),
      $exp_phenocombos,
      'Experiment combos returned does not match expected combo count ' . $combo_count,
    );

    foreach ($assigned_combos as $label => $combo_details) {
      foreach ($combo_details as $combo_field => $combo_value) {
        $this->assertEquals(
          $combo_value,
          $exp_phenocombos[$label]->$combo_field,
          'Experiment pheno combo field value for ' . $label . '/' . $combo_field . ' does not match expected value.',
        );
      }

      foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
        $this->assertEquals(
          $combo_details->{$field},
          $exp_phenocombos[$label]->{$alias}->cvterm_id,
          $alias . ' does not contain the expected resolved value for trait combo field ' . $field,
        );
      }
    }

    // Get pheno combos and using format - header.
    $exp_phenocombos = $this->service_PhenoTraitCombo->getAllExperimentPhenoCombos(options: ['format' => 'header']);
    foreach ($assigned_combos as $label => $combo_details) {
      $items = [
        $combo_details->combo_id,
        $exp_phenocombos_clone[$label]->trait->name,
        $exp_phenocombos_clone[$label]->trait->definition,
        $combo_details->is_required == 1 ? 'Required' : 'Optional',
      ];

      $this->assertEquals(
        array_combine(array_keys($exp_phenocombos[$label]), $items),
        $exp_phenocombos[$label],
        'Experiment pheno combos by header failed to return expected combo for label ' . $label,
      );
    }

    // Get pheno combos and using format - component.
    $exp_phenocombos = $this->service_PhenoTraitCombo->getAllExperimentPhenoCombos(options: ['format' => 'component']);
    foreach ($assigned_combos as $label => $combo_details) {
      $items = [
        $combo_details->combo_id,
        $exp_phenocombos_clone[$label]->trait->name,
        $exp_phenocombos_clone[$label]->trait->definition,
        FALSE,
        [
          'method_shortname' => $exp_phenocombos_clone[$label]->method->name,
          'unit' => $exp_phenocombos_clone[$label]->unit->name,
          'type' => $exp_phenocombos_clone[$label]->unit_type,
          'collection_method' => $exp_phenocombos_clone[$label]->method->definition,
        ],
      ];

      $this->assertEquals(
        array_combine(array_keys($exp_phenocombos[$label]), $items),
        $exp_phenocombos[$label],
        'Experiment pheno combos by component failed to return expected combo for label ' . $label,
      );
    }

    // Pull specific genus.
    $exp_phenogenus = $this->container->get('trpcultivate_phenotypes.genus_project')->getGenusOfProject($experiment);
    foreach ($exp_phenogenus as $genus) {
      $exp_phenocombos = $this->service_PhenoTraitCombo->getAllExperimentPhenoCombos($genus);

    }
  }

  /**
   * Test setExperimentPhenoComboStatus().
   */
  public function testSetExperimentPhenoComboStatus() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);
    $this->service_PhenoTraitCombo->setExperiment($experiment);

    $status_flags = array_values($this->service_PhenoTraitCombo::PHENO_COMBO_STATUS_FLAG_MAP);

    $drupaldb_connection = $this->container->get('database');
    $a_pheno_combo = $drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->orderRandom()
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    // Check that all status flags are set to no (0) at the beginning.
    $this->assertEquals(
      array_fill_keys($status_flags, 0),
      array_filter($a_pheno_combo, function ($i) use ($status_flags) {
        return in_array($i, $status_flags);
      }, ARRAY_FILTER_USE_KEY),
      'The status flags are expected to all be set to no (0).',
    );

    $trait_combo = [];
    foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
      $trait_combo[$alias] = $a_pheno_combo[$field];
    }

    // Set status flags by combo input types.
    foreach (['combo_id', 'label', 'trait_combo'] as $combo_args) {
      foreach ($status_flags as $flag) {
        $status_flags_val[$flag] = mt_rand(0, 1);
      }
      $this->service_PhenoTraitCombo->setExperimentPhenoComboStatus(
        ($combo_args == 'trait_combo') ? $trait_combo : $a_pheno_combo[$combo_args],
        $status_flags_val
      );

      $updated_flags = $drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
        ->fields('tbl', $status_flags)
        ->condition('tbl.combo_id', $a_pheno_combo['combo_id'], '=')
        ->execute()
        ->fetchAssoc();
      $this->assertEquals($status_flags_val, $updated_flags, 'Failed to set the correct status flags for combo: ' . $combo_args);
    }
  }

  /**
   * Test removePhenoComboFromExperiment().
   */
  public function testRemovePhenoComboFromExperiment() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);
    $this->service_PhenoTraitCombo->setExperiment($experiment);

    $drupaldb_connection = $this->container->get('database');
    $pheno_combos = $drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchAll();

    $this->assertGreaterThanOrEqual(3, count($pheno_combos), 'To test the remove combo functionality, at least 3 pheno combo rows are expected.');

    foreach (['combo_id', 'label', 'trait_combo'] as $i => $combo_args) {
      if ($combo_args == 'trait_combo') {
        foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
          $combo[$alias] = $pheno_combos[$i]->$field;
        }
      }
      else {
        $combo = $pheno_combos[$i]->$combo_args;
      }

      $this->service_PhenoTraitCombo->removePhenoComboFromExperiment($combo);
      unset($combo);

      $find_combo_id = $drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
        ->condition('tbl.combo_id', $pheno_combos[$i]->combo_id, '=')
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEquals(0, $find_combo_id, 'Failed to remove combo ' . $i);
    }
  }

  /**
   * Test sanitizeTraitCombo().
   */
  public function testSanitizeTraitCombo() {

    $trait_combo_alias = array_keys(self::TRAIT_COMBO_KEY_MAP);

    // Missing alias key.
    foreach ($trait_combo_alias as $i => $alias) {
      $arr_tmp = $trait_combo_alias;
      unset($arr_tmp[$i]);

      try {
        $this->service_PhenoTraitCombo->sanitizeTraitCombo(array_fill_keys($arr_tmp, $this->randomString()));
      }
      catch (\Exception $e) {
        $this->assertEquals(
          'Failed to sanitize trait combo. Trait combo must have keys [' . implode(', ', $trait_combo_alias) . ']. You provided [' . implode(', ', $arr_tmp) . '].',
          $e->getMessage(),
          'A missing key in trait combo is expected to throw an exception.',
        );
      }
    }

    // Unexpected value - not integer or string.
    foreach ($trait_combo_alias as $i => $alias) {
      $arr_tmp = $trait_combo_alias;

      try {
        $this->service_PhenoTraitCombo->sanitizeTraitCombo(array_fill_keys($arr_tmp, (($i % 2) ? [] : NULL)));
      }
      catch (\Exception $e) {
        $this->assertEquals(
          'Failed to sanitize trait combo. Trait combo has invalid data type in key(s) - [' . implode(', ', $arr_tmp) . ']. Use integer or string value.',
          $e->getMessage(),
          'Unexpected data type in trait combo is expected to throw an exception.',
        );
      }
    }

    // trait-method-unit not found.
    try {
      $this->service_PhenoTraitCombo->sanitizeTraitCombo(
        ['trait' => 'Spurious Trait', 'method' => 'Not so methodical', 'unit' => 'unity']
      );
    }
    catch (\Exception $e) {
      $this->assertEquals(
        'Failed to sanitize trait combo. The trait combo does not exist.', $e->getMessage(), 'Trait combo not found is expected to throw an exception.'
      );
    }

    // Using test traits profile as trait combo, sanitize the trait combo - the
    // sanitized array will have all values resolved to id form (cvterm id),
    // regardless of whether combo-key values provided were purely ids, name, or
    // a combination of both.
    $service_trait = $this->container->get('trpcultivate_phenotypes.traits');

    foreach ($this->test_trait_combo_ids as $genus => $traits) {

      $service_trait->setTraitGenus($genus);

      foreach ($traits as $i => $trait_combo_ids) {
        foreach ($trait_combo_alias as $alias) {
          ${$alias} = $trait_combo_ids[$alias];
        }
        $trait_method_unit = $service_trait->getTraitMethodUnitCombo($trait, $method, $unit);

        $trait_combo_payload = [];
        foreach ($trait_combo_alias as $alias) {
          $trait_combo_payload['by id'][$alias] = $trait_method_unit[$alias]->cvterm_id;
          $trait_combo_payload['by name'][$alias] = $trait_method_unit[$alias]->name;
          $trait_combo_payload['mix'][$alias] = $trait_method_unit[$alias]->{(mt_rand(0, 1)) ? 'cvterm_id' : 'name'};
        }

        foreach ($trait_combo_payload as $type => $trait_combo) {
          $sanitized_trait_combo = $this->service_PhenoTraitCombo->sanitizeTraitCombo($trait_combo);
          $this->assertEquals(
            $trait_combo_ids, $sanitized_trait_combo, 'Failed to sanitize trait combo in genus ' . $genus . ' - ' . $type
          );
        }
      }
    }
  }

  /**
   * Test sanitizePhenoComboDetails().
   */
  public function testSanitizePhenoComboDetails() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO);
    $this->service_PhenoTraitCombo->setExperiment($experiment);

    $combo_details = [];
    $combo_details['label'] = $this->randomString();

    foreach ($this->service_PhenoTraitCombo::PHENO_COMBO_STATUS_FLAG_MAP as $field) {
      $combo_details[$field] = mt_rand(0, 1);
    }

    foreach ($combo_details as $key => $value) {
      $arr_tmp = $combo_details;
      // Set an empty label or a flag value that is neither integer nor string.
      $arr_tmp[$key] = ($key == 'label') ? '' : ((mt_rand(0, 1)) ? [] : NULL);

      try {
        $this->service_PhenoTraitCombo->sanitizePhenoComboDetails($arr_tmp);
      }
      catch (\Exception $e) {
        if ($key == 'label') {
          $this->assertEquals(
            'Failed to sanitize pheno-combo \'label\' detail. Label provided must be a unique entry within the experiment and not an empty string.',
            $e->getMessage(),
            'Invalid label in combo details is expected to throw an exception.',
          );
        }
        else {
          $this->assertEquals(
            'Failed to sanitize pheno-combo details. The following status flag(s) contains invalid value in key(s) [' . $key . '].',
            $e->getMessage(),
            'Unexpected status flag value combo details is expected to throw an exception.',
          );
        }
      }
    }

    // Test labels already in use.
    $all_labels = $this->container->get('database')->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['label'])
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchCol(0);

    foreach ($all_labels as $label) {
      $combo_details['label'] = $label;

      try {
        $this->service_PhenoTraitCombo->sanitizePhenoComboDetails($combo_details);
      }
      catch (\Exception $e) {
        $this->assertEquals(
          'Failed to sanitize pheno-combo \'label\' detail. Label provided must be a unique entry within the experiment and not an empty string.',
          $e->getMessage(),
          'Reusing label in combo details is expected to throw an exception.',
        );
      }
    }

    $combo_details['label'] = 'A Unique Label';
    $sanitized_combo_details = $this->service_PhenoTraitCombo->sanitizePhenoComboDetails($combo_details);
    $this->assertEquals($combo_details, $sanitized_combo_details, 'Failed to sanitize pheno-combo details.');
  }

  /**
   * Test experimentHasPhenoCombo().
   */
  public function testExperimentHasPhenoCombo() {

    $this->assertTrue(
      ExperimentPhenoTraitComboService::experimentHasPhenoCombo(self::EXPERIMENT_NAME_CONTEXT_WITH_COMBO),
      'Experiment with pheno combo is expected to return FALSE using the experimentHasPhenoCombo() method.',
    );

    $this->assertTrue(
      ExperimentPhenoTraitComboService::experimentHasPhenoCombo($this->exp_entity),
      'Experiment with pheno combo is expected to return FALSE using the experimentHasPhenoCombo() method.',
    );

    $this->assertFalse(
      ExperimentPhenoTraitComboService::experimentHasPhenoCombo(self::EXPERIMENT_NAME_CONTEXT_NO_COMBO),
      'Experiment without pheno combo is expected to return FALSE using the experimentHasPhenoCombo() method.',
    );
  }

}
