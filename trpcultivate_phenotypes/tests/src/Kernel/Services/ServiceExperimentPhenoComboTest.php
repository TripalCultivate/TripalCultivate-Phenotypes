<?php

namespace Drupal\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests associated with the experiment PhenoCombo service.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceExperimentPhenoComboTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;

  /**
   * Experiment name context that has associated experiment PhenoCombos.
   *
   * @var string
   */
  const EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO = 'Project Awesome';

  /**
   * Experiment name context that has no associated experiment PhenoCombos.
   *
   * @var string
   */
  const EXPERIMENT_NAME_CONTEXT_NO_PHENOCOMBO = 'Project Not So Awesome';

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
   * @var \Drupal\trpcultivate_phenotypes\Services\ExperimentPhenoComboService
   */
  protected ExperimentPhenoComboService $service_PhenoCombo;

  /**
   * Tripal Logger log message.
   *
   * @var string
   */
  protected string $log_message = '';

  /**
   * Research experiment entity to hold TripalEntity experiment identifier.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  protected TripalEntity $exp_entity;

  /**
   * A set of test traits grouped by genus.
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
        'Biomass',
        'The total mass of plant-based or organic matter.',
        'B-MASS',
        'Use quadrant (a marked frame) to define a specific area',
        'kilogram',
        'Quantitative',
      ],
    ],
  ];

  /**
   * The trait-method-unit ids of the test traits after being added.
   *
   * @var array
   */
  protected array $test_trait_pheno_combo_ids = [];

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

    $this->installSchema('trpcultivate_phenotypes', ['trpcultivate_phenocombo']);
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
      ->values(['name' => self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO])
      ->values(['name' => self::EXPERIMENT_NAME_CONTEXT_NO_PHENOCOMBO])
      ->execute();

    $experiment_id = ChadoProjectAutocompleteController::getProjectId(
      self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO
    );

    // Create a tripal entity.
    $entity = TripalEntity::create([
      'id' => 1,
      'type' => 'research_experiment',
      'label' => self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO,
    ]);

    $entity->set(
      'exp_name',
      ['record_id' => $experiment_id, 'value' => self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO]
    );

    $this->exp_entity = $entity;

    // Create configured organism and relate to experiment context with combos.
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

    // Create traits and assign PhenoCombos.
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
    $this->service_PhenoCombo = $this->container->get('trpcultivate_phenotypes.pheno_combo');

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
        $this->test_trait_pheno_combo_ids[$genus][] = $service_trait->insertTrait(array_combine($trait_keys, $trait));

        // Only traits in Lens are installed and assigned to experiment.
        if ($genus != 'Lens') {
          break;
        }

        $field_pheno_combo_ids = [];
        foreach ($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP as $alias => $field) {
          $field_pheno_combo_ids[$field] = $this->test_trait_pheno_combo_ids[$genus][$i][$alias];
        }

        // Insert a PhenoCombo - default all status flags to 0.
        $drupaldb_connection->insert($this->service_PhenoCombo::PHENOCOMBO_TABLE)
          ->fields([
            'project_id' => $experiment_id,
            'uid' => $admin_user = 1,
            'label' => $this->randomString(),
            'timestamp' => time(),
          ] + $field_pheno_combo_ids)
          ->execute();
      }
    }
  }

  /**
   * Test ensureExperimentIsSet().
   */
  public function testEnsureExperimentIsSet() {

    try {
      $this->service_PhenoCombo->getAllExperimentPhenoCombos();
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'Experiment context not set error',
        $e->getMessage(),
        'Experiment context must be set before performing any operation.'
      );
    }
  }

  /**
   * Provides experiment context test cases.
   *
   * Each test experiment context in an array with the following values:
   * - The experiment name or id number to set as the experiment context.
   * - The exception message thrown (or empty string if none).
   */
  public static function provideExperimentContext() {

    return [
      [
        'Experiment Spurious',
        $does_not_exists = 'Missing experiment error. The specified experiment entity/id/name: %s does not exist.',
      ],
      [
        0,
        $does_not_exists,
      ],
      [
        123,
        $does_not_exists,
      ],
      [
        self::EXPERIMENT_NAME_CONTEXT_NO_PHENOCOMBO,
        'Failed to set experiment context. The specified experiment entity/id/name: %s is not configured with a genus.',
      ],
      [
        self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO,
        '',
      ],
      [
        'TripalEntity',
        '',
      ],
    ];
  }

  /**
   * Test setExperiment() with various experiment identifiers.
   *
   * @param int|string $experiment
   *   The experiment name or id number to set as the experiment context.
   *   TripalEntity (string) is resolved to a TripalEntity object.
   * @param string $exception_message
   *   The exception message thrown.
   *
   * @dataProvider provideExperimentContext
   */
  #[DataProvider('provideExperimentContext')]
  public function testSetExperiment(int|string $experiment, string|null $exception_message) {

    try {
      $this->service_PhenoCombo->setExperiment(
        ($experiment == 'TripalEntity') ? $this->exp_entity : $experiment
      );
    }
    catch (\Exception $e) {
      $exp_input = ($experiment instanceof TripalEntity) ? $experiment->label() : $experiment;

      if (!is_null($exception_message)) {
        $exception_message = sprintf($exception_message, (string) $exp_input);
      }

      $this->assertEquals(
        $exception_message,
        $e->getMessage(),
        'Set experiment failed to throw an exception for tests:' . $exp_input
      );
    }
  }

  /**
   * Test assignPhenoComboToExperiment().
   */
  public function testAssignPhenoComboToExperiment() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);
    $this->service_PhenoCombo->setExperiment($experiment);

    // No label.
    try {
      $this->service_PhenoCombo->assignPhenoComboToExperiment([], ['tag' => 'My Label']);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'Missing label key error',
        $e->getMessage(),
        'Label is required in assignment call.'
      );
    }

    $pheno_combo_details = [];
    $pheno_combo_details['label'] = $this->randomString();

    foreach ($this->service_PhenoCombo::PHENOCOMBO_STATUS_FLAG_FIELD_MAP as $field) {
      $pheno_combo_details[$field] = (int) mt_rand(0, 1);
    }

    // Attempt to reassign the same PhenoCombos that have been previously
    // assigned at setUp().
    foreach ($this->test_trait_pheno_combo_ids['Lens'] as $pheno_combo) {
      try {
        $this->service_PhenoCombo
          ->assignPhenoComboToExperiment($pheno_combo, $pheno_combo_details);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Duplicate PhenoCombo error',
          $e->getMessage(),
          'Duplicate PhenoCombo in the same experiment context is not allowed.');
      }
    }

    // Traits in genus other than Lens have not been assigned to an experiment.
    foreach ($this->test_trait_pheno_combo_ids as $genus => $pheno_combos) {
      if ($genus == 'Lens') {
        continue;
      }

      foreach ($pheno_combos as $pheno_combo) {
        $combo_id = $this->service_PhenoCombo
          ->assignPhenoComboToExperiment($pheno_combo, $pheno_combo_details);

        $pheno_combo = $this->container->get('database')
          ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
          ->fields('tbl')
          ->condition('tbl.combo_id', $combo_id, '=')
          ->execute()
          ->fetchAssoc();

        $this->assertGreaterThan(0, $combo_id, 'Failed to assign PhenoCombo to experiment.');
        $this->assertEquals(
          $experiment,
          $pheno_combo['project_id'],
          'Failed to assign PhenoCombo to experiment context.'
        );

        foreach ($pheno_combo_details as $key => $value) {
          $this->assertEquals(
            $value,
            $pheno_combo[$key],
            'Failed to assign PhenoCombo detail - ' . $key . ' to experiment.'
          );
        }
      }
    }
  }

  /**
   * Test getExperimentPhenoCombo().
   */
  public function testGetExperimentPhenoCombo() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);
    $this->service_PhenoCombo->setExperiment($experiment);

    $drupaldb_connection = $this->container->get('database');

    $query_pheno_combos = $drupaldb_connection
      ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchAllAssoc('combo_id');

    // Provide combo argument (combo_id, label, and pheno_combo) to method.
    foreach ($query_pheno_combos as $combo_id => $exp_phenocombo) {
      $combo_by_id = $this->service_PhenoCombo->getExperimentPhenoCombo($combo_id);
      $this->assertEquals(
        $combo_id,
        $combo_by_id->combo_id,
        'PhenoCombo does not match combo returned by get combo with combo_id argument.'
      );

      $combo_by_label = $this->service_PhenoCombo->getExperimentPhenoCombo($exp_phenocombo->label);
      $this->assertEquals(
        $exp_phenocombo->label,
        $combo_by_label->label,
        'PhenoCombo does not match combo returned by get combo with combo label argument.'
      );

      $pheno_combo = [];
      foreach ($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP as $alias => $field) {
        $pheno_combo[$alias] = $exp_phenocombo->{$field};
      }

      $combo_by_pheno_combo = $this->service_PhenoCombo->getExperimentPhenoCombo($pheno_combo);
      foreach ($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP as $field) {
        $this->assertEquals(
          $exp_phenocombo->{$field},
          $combo_by_pheno_combo->{$field},
          'PhenoCombo does not match combo returned by get combo with pheno_combo argument.'
        );
      }

      $this->assertEquals(
        $combo_by_id,
        $combo_by_label,
        'Expected to return the same combo regardless of the argument type [combo_id - label].'
      );

      $this->assertEquals(
        $combo_by_id,
        $combo_by_pheno_combo,
        'Expected to return the same combo regardless of the argument type [combo_id - pheno_combo].'
      );
    }
  }

  /**
   * Test getAllExperimentCombos().
   */
  public function testGetAllExperimentPhenoCombos() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);
    $this->service_PhenoCombo->setExperiment($experiment);

    $drupaldb_connection = $this->container->get('database');

    $query_pheno_combos = $drupaldb_connection
      ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchAllAssoc('label');

    // Get PhenoCombos with options format equals to full.
    $exp_phenocombos = $this->service_PhenoCombo->getAllExperimentPhenoCombos(options: ['format' => 'full']);
    $exp_phenocombos_clone = $this->service_PhenoCombo->getAllExperimentPhenoCombos();

    $this->assertEquals(
      $exp_phenocombos,
      $exp_phenocombos_clone,
      'Failed to return expected experiment combos using full format option or unspecified format.',
    );

    $this->assertCount(
      $row_count = count($query_pheno_combos),
      $exp_phenocombos,
      'Experiment PhenoCombos returned does not match expected record count ' . $row_count,
    );

    foreach ($query_pheno_combos as $label => $combo_details) {
      // Combos queried match the combos returned by the method.
      foreach ($combo_details as $combo_field => $combo_value) {
        $this->assertEquals(
          $combo_value,
          $exp_phenocombos[$label]->$combo_field,
          'Experiment PhenoCombo field value for ' . $label . '/' . $combo_field . ' does not match expected value.',
        );
      }

      // Check the trait-method-unit resolved to the correct cvterm record.
      foreach ($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP as $alias => $field) {
        $this->assertEquals(
          $combo_details->{$field},
          $exp_phenocombos[$label]->{$alias}->cvterm_id,
          $alias . ' does not contain the expected resolved value for PhenoCombo field ' . $field,
        );
      }
    }

    // Get PhenoCombos with options format equals to header.
    $exp_phenocombos = $this->service_PhenoCombo->getAllExperimentPhenoCombos(options: ['format' => 'header']);
    foreach ($query_pheno_combos as $label => $combo_details) {
      $items = [
        $combo_details->combo_id,
        $exp_phenocombos_clone[$label]->trait->name,
        $exp_phenocombos_clone[$label]->trait->definition,
        $combo_details->is_required == 1 ? 'Required' : 'Optional',
      ];

      $this->assertEquals(
        array_combine(array_keys($exp_phenocombos[$label]), $items),
        $exp_phenocombos[$label],
        'Experiment PhenoCombos by header failed to return expected combo for label ' . $label,
      );
    }

    // Get PhenoCombos with options format equals to component.
    $exp_phenocombos = $this->service_PhenoCombo->getAllExperimentPhenoCombos(options: ['format' => 'component']);
    foreach ($query_pheno_combos as $label => $combo_details) {
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
        'Experiment PhenoCombos by component failed to return expected combo for label ' . $label,
      );
    }

    // Pull PhenoCombo using a specific genus.
    $genus = array_keys($this->test_trait_combo)[0];
    $exp_phenocombos = $this->service_PhenoCombo->getAllExperimentPhenoCombos($genus);
    $this->assertEquals(
      count($exp_phenocombos),
      count($this->test_trait_combo[$genus]),
      'Incorrect number of PhenoCombos returned.'
    );

    foreach ($exp_phenocombos as $label => $combo_details) {
      $this->assertContains(
        $combo_details->attr_id,
        array_column($this->test_trait_pheno_combo_ids[$genus], 'trait'),
        'Experiment PhenoCombos contain unexpected combo for genus ' . $genus,
      );
    }

    // Genus has no PhenoCombos yet.
    $genus = array_keys($this->test_trait_combo)[1];
    $exp_phenocombos = $this->service_PhenoCombo->getAllExperimentPhenoCombos($genus);
    $this->assertEmpty($exp_phenocombos, 'Incorrect number of PhenoCombos returned by genus ' . $genus);
  }

  /**
   * Test setExperimentPhenoComboStatusFlags().
   */
  public function testSetExperimentPhenoComboStatusFlags() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);
    $this->service_PhenoCombo->setExperiment($experiment);

    $status_flags = array_values($this->service_PhenoCombo::PHENOCOMBO_STATUS_FLAG_FIELD_MAP);

    $drupaldb_connection = $this->container->get('database');
    $a_pheno_combo = $drupaldb_connection->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
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
      'All the status flags are expected to be set to 0 (No).',
    );

    $pheno_combo = [];
    foreach ($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP as $alias => $field) {
      $pheno_combo[$alias] = $a_pheno_combo[$field];
    }

    // Set status flags by combo input types.
    foreach (['combo_id', 'label', 'pheno_combo'] as $combo_args) {
      foreach ($status_flags as $flag) {
        $status_flags_val[$flag] = mt_rand(0, 1);
      }

      $this->service_PhenoCombo->setExperimentPhenoComboStatusFlags(
        ($combo_args == 'pheno_combo') ? $pheno_combo : $a_pheno_combo[$combo_args],
        $status_flags_val
      );

      $updated_flags = $drupaldb_connection
        ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
        ->fields('tbl', $status_flags)
        ->condition('tbl.combo_id', $a_pheno_combo['combo_id'], '=')
        ->execute()
        ->fetchAssoc();

      $this->assertEquals(
        $status_flags_val,
        $updated_flags,
        'Failed to set the correct status flags for combo: ' . $combo_args
      );
    }

    $this->assertEmpty(
      $this->service_PhenoCombo->setExperimentPhenoComboStatusFlags(1, []),
      'No combo status to set is expected to return void.',
    );
  }

  /**
   * Test removePhenoComboFromExperiment().
   */
  public function testRemovePhenoComboFromExperiment() {

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);
    $this->service_PhenoCombo->setExperiment($experiment);

    $drupaldb_connection = $this->container->get('database');

    $query_pheno_combos = $drupaldb_connection
      ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchAll();

    $this->assertGreaterThanOrEqual(
      $exp_rows = count($this->test_trait_pheno_combo_ids['Lens']),
      count($query_pheno_combos),
      'To test the remove PhenoCombo functionality, at least ' . $exp_rows . ' PhenoCombo rows are expected.'
    );

    // If PhenoCombo has phenotypic records.
    $combo = current($query_pheno_combos);
    $this->chado_connection->insert($tbl_pheno = '1:phenotype')
      ->fields([
        'uniquename' => $this->randomString(),
        'name' => $this->randomString(),
        'observable_id' => $combo->observable_id,
        'attr_id' => $combo->attr_id,
        'assay_id' => $combo->unit_id,
        'cvalue_id' => 1,
      ])
      ->execute();

    try {
      $this->service_PhenoCombo->removePhenoComboFromExperiment($combo->combo_id);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'Failed to remove PhenoCombo from this experiment',
        $e->getMessage(), 'PhenoCombos with associated phenotypic records are protected from the remove operation.'
      );
    }

    $this->chado_connection->delete($tbl_pheno)->execute();

    foreach (['combo_id', 'label', 'pheno_combo'] as $i => $combo_args) {
      if ($combo_args == 'pheno_combo') {
        foreach ($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP as $alias => $field) {
          $combo[$alias] = $query_pheno_combos[$i]->{$field};
        }
      }
      else {
        $combo = $query_pheno_combos[$i]->{$combo_args};
      }

      $this->service_PhenoCombo->removePhenoComboFromExperiment($combo);
      unset($combo);

      $find_combo_id = $drupaldb_connection
        ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
        ->condition('tbl.combo_id', $query_pheno_combos[$i]->combo_id, '=')
        ->execute()
        ->fetchField();

      $this->assertFalse($find_combo_id, 'Failed to remove PhenoCombo #' . $i);
    }
  }

  /**
   * Test sanitizePhenoCombo().
   */
  public function testSanitizePhenoCombo() {

    $trait_combo_alias = array_keys($this->service_PhenoCombo::PHENOCOMBO_FIELD_MAP);

    // Missing alias key.
    foreach ($trait_combo_alias as $i => $alias) {
      $arr_tmp = $trait_combo_alias;
      unset($arr_tmp[$i]);

      try {
        $this->service_PhenoCombo->sanitizePhenoCombo(array_fill_keys($arr_tmp, $this->randomString()));
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Missing PhenoCombo item key error',
          $e->getMessage(),
          'A missing key ' . $alias . ' is expected to throw an exception.'
        );
      }
    }

    // Unexpected value - non integer or string value provided.
    foreach ($trait_combo_alias as $i => $alias) {
      $arr_tmp = $trait_combo_alias;

      try {
        $this->service_PhenoCombo->sanitizePhenoCombo(array_fill_keys($arr_tmp, (($i % 2) ? [] : NULL)));
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Unexpected PhenoCombo item value type error',
          $e->getMessage(),
          'Unexpected data type in PhenoCombo is expected to throw an exception.'
        );
      }
    }

    // trait-method-unit not found.
    try {
      $this->service_PhenoCombo->sanitizePhenoCombo(
        ['trait' => 'Spurious Trait', 'method' => 'Not so methodical', 'unit' => 'unity']
      );
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'Missing PhenoCombo error',
        $e->getMessage(),
        'PhenoCombo not found is expected to throw an exception.'
      );
    }

    // Using trait ids inserted as PhenoCombo, sanitize pheno_combo - the
    // sanitized array will have all values resolved to id form (cvterm id),
    // regardless of whether combo-key values provided were purely ids, name, or
    // a combination of both.
    $service_trait = $this->container->get('trpcultivate_phenotypes.traits');

    foreach ($this->test_trait_pheno_combo_ids as $genus => $trait_ids) {
      $service_trait->setTraitGenus($genus);

      foreach ($trait_ids as $i => $trait_combo_ids) {
        foreach ($trait_combo_alias as $alias) {
          ${$alias} = $trait_combo_ids[$alias];
        }

        $trait_method_unit = $service_trait->getTraitMethodUnitCombo($trait, $method, $unit);

        $pheno_combo_payload = [];
        foreach ($trait_combo_alias as $alias) {
          $pheno_combo_payload['by id'][$alias] = $trait_method_unit[$alias]->cvterm_id;
          $pheno_combo_payload['by name'][$alias] = $trait_method_unit[$alias]->name;
          $pheno_combo_payload['mix'][$alias] = $trait_method_unit[$alias]->{(mt_rand(0, 1)) ? 'cvterm_id' : 'name'};
        }

        foreach ($pheno_combo_payload as $type => $pheno_combo) {
          $sanitized_trait_combo = $this->service_PhenoCombo->sanitizePhenoCombo($pheno_combo);
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

    $experiment = ChadoProjectAutocompleteController::getProjectId(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);
    $this->service_PhenoCombo->setExperiment($experiment);

    $combo_details = [];

    $this->assertEmpty(
      $this->service_PhenoCombo->sanitizePhenoComboDetails($combo_details),
      'An empty array is returned when no PheoCombo details provided to sanitize.'
    );

    $combo_details['label'] = $this->randomString();

    foreach ($this->service_PhenoCombo::PHENOCOMBO_STATUS_FLAG_FIELD_MAP as $field) {
      $combo_details[$field] = mt_rand(0, 1);
    }

    foreach ($combo_details as $key => $value) {
      $arr_tmp = $combo_details;
      // Set an empty label or a flag value that is neither integer nor string.
      $arr_tmp[$key] = ($key == 'label') ? '' : ((mt_rand(0, 1)) ? [] : NULL);

      try {
        $this->service_PhenoCombo->sanitizePhenoComboDetails($arr_tmp);
      }
      catch (\Exception $e) {
        if ($key == 'label') {
          $this->assertStringContainsString(
            'Invalid PhenoCombo label error',
            $e->getMessage(),
            'Invalid label in combo details is expected to throw an exception.'
          );
        }
        else {
          $this->assertStringContainsString(
            'Unexpected PhenoCombo status flag value type error',
            $e->getMessage(),
            'Unexpected status flag value combo details is expected to throw an exception.'
          );
        }
      }
    }

    // Test empty labels and labels already in use.
    $all_labels = $this->container->get('database')
      ->select($this->service_PhenoCombo::PHENOCOMBO_TABLE, 'tbl')
      ->fields('tbl', ['label'])
      ->condition('tbl.project_id', $experiment, '=')
      ->execute()
      ->fetchCol(0);

    array_push($all_labels, ' ');

    foreach ($all_labels as $label) {
      $combo_details['label'] = $label;

      try {
        $this->service_PhenoCombo->sanitizePhenoComboDetails($combo_details);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Invalid PhenoCombo label error',
          $e->getMessage(),
          'Reusing label in combo details is expected to throw an exception.'
        );
      }
    }



    $combo_details['label'] = 'A Unique Label';
    $sanitized_combo_details = $this->service_PhenoCombo->sanitizePhenoComboDetails($combo_details);
    $this->assertEquals($combo_details, $sanitized_combo_details, 'Failed to sanitize PhenoCombo details.');
  }

  /**
   * Test experimentHasPhenoCombo().
   */
  public function testExperimentHasPhenoCombo() {

    $this->assertTrue(
      ExperimentPhenoComboService::experimentHasPhenoCombo(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO),
      'Experiment with pheno combo is expected to return TRUE using the experimentHasPhenoCombo() method.',
    );

    $this->assertTrue(
      ExperimentPhenoComboService::experimentHasPhenoCombo($this->exp_entity),
      'Experiment with pheno combo is expected to return TRUE using the experimentHasPhenoCombo() method.',
    );

    $this->assertFalse(
      ExperimentPhenoComboService::experimentHasPhenoCombo(self::EXPERIMENT_NAME_CONTEXT_NO_PHENOCOMBO),
      'Experiment without pheno combo is expected to return FALSE using the experimentHasPhenoCombo() method.',
    );
  }

  /**
   * Provide invalid arguments to getAllExperimentPhenoCombos().
   *
   * Each test argument is an array with the following values:
   * - The genus used as a filter to restrict PhenoCombo returned.
   * - The options array containing format that will be applied to the result.
   * - The expected exception message.
   */
  public static function provideInvalidArgumentsToGetAllPhenoCombos() {

    $genus_lens = 'Lens';

    return [
      [
        'Spurious Genus',
        ['format' => 'header'],
        'The genus provided is not a configured genus of the experiment',
      ],
      [
        $genus_lens,
        ['formatized' => 'full'],
        'Unsupported options key provided [formatized]',
      ],
      [
        $genus_lens,
        ['format' => 'italicized'],
        'The options format value provided italicized is not a valid format',
      ],
    ];
  }

  /**
   * Test getAllExperimentPhenoCombos() invalid genus and options arguments.
   *
   * @param string $genus
   *   Genus argument.
   * @param array $options
   *   Options argument.
   * @param string $expected_message
   *   The expected exception message thrown.
   *
   * @dataProvider provideInvalidArgumentsToGetAllPhenoCombos
   */
  #[DataProvider('provideInvalidArgumentsToGetAllPhenoCombos')]
  public function testGetAllExperimentPhenoCombosInvaildArguments(string $genus, array $options, string $expected_message) {

    $this->service_PhenoCombo->setExperiment(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);

    try {
      $this->service_PhenoCombo->getAllExperimentPhenoCombos($genus, $options);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString($expected_message, $e->getMessage(), 'The invalid arguments failed to return expected message.');
    }
  }

  /**
   * Provide invalid combo arguments.
   *
   * Each test argument is an array with the following values:
   * - combo argument - PhenoCombo (array), combo_id (int) and label (string).
   * - The expected exception message.
   */
  public static function provideInvalidComboArgument() {

    $error = [
      'missing key' => 'Missing PhenoCombo item key error',
      'invalid id' => 'Invalid combo_id error',
      'invalid label' => 'Invalid label error',
      'unresolved' => 'Missing PhenoCombo error',
    ];

    return [
      [['method' => 1, 'unit' => 1], $error['missing key']],
      [['trait' => 1, 'unit' => 1], $error['missing key']],
      [['trait' => 1, 'method' => 1], $error['missing key']],
      [0, $error['invalid id']],
      [-1, $error['invalid id']],
      ['', $error['invalid label']],
      ['   ', $error['invalid label']],
      [['trait' => 1, 'method' => 1, 'unit' => 1], $error['unresolved']],
      [111, $error['unresolved']],
      ['Spurious Label', $error['unresolved']],
    ];
  }

  /**
   * Test invalid combo argument.
   *
   * @param array|int|string $combo
   *   - combo argument - PhenoCombo (array), combo_id (int) and label (string).
   * @param string $expected_message
   *   - The expected exception message.
   *
   * @dataProvider provideInvalidComboArgument
   */
  #[DataProvider('provideInvalidComboArgument')]
  public function testResolveComboInvalidArgument(array|int|string $combo, string $expected_message) {

    $this->service_PhenoCombo->setExperiment(self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO);

    try {
      $this->service_PhenoCombo->getExperimentPhenoCombo($combo);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        $expected_message,
        $e->getMessage(),
        'The invalid arguments failed to return expected message.'
      );
    }
  }

  /**
   * Test hosted phenotypes module without a configured genus.
   */
  public function testHostPhenoHasNoGenus() {

    // Reset the genus configuration values to 0 (not configured).
    $this->container->get('trpcultivate_phenotypes.genus_ontology')
      ->loadGenusOntology();

    $this->container->set($service = 'trpcultivate_phenotypes.pheno_combo', NULL);
    $this->service_PhenoCombo = $this->container->get($service);

    $test_exceptions = [
      'setExperiment' => self::EXPERIMENT_NAME_CONTEXT_WITH_PHENOCOMBO,
      'sanitizePhenoCombo' => $this->test_trait_pheno_combo_ids['Lens'][0],
    ];

    foreach ($test_exceptions as $method => $args) {
      try {
        $this->service_PhenoCombo->{$method}($args);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'The Phenotypes module is not configured with a genus',
          $e->getMessage(),
          'The Phenotypes module hosted must have a configured genus to be able to set an experiment context.'
        );
      }
    }
  }

}
