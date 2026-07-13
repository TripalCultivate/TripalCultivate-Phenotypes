<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentConfigurationForm;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests associated with PhenoExperimentConfigurationForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('configuration')]
#[RunTestsInSeparateProcesses]
class PhenoExperimentConfigurationFormTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;
  use TripalTestTrait;

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
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $exp_entity;

  /**
   * Tripal Logger log message.
   *
   * @var string
   */
  private string $log_message = '';

  /**
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_configuration';

  /**
   * The table name that holds the experiment trait combos.
   *
   * @var string
   */
  const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * The name of the field that contains the genus.
   *
   * @var string
   */
  public const FIELD_ORGANISM = 'exp_organism';

  /**
   * Test genus with a set of test traits.
   *
   * @var array
   */
  private $trait_set = [];

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

    // Create test records.
    $trait_keys = [
      'Trait Name',
      'Trait Description',
      'Method Short Name',
      'Collection Method',
      'Unit',
      'Type',
    ];

    $trait_values = [
      'Lens' => [
        [
          'Days to Flower',
          'DTF trait description text',
          'DTF',
          'DTF trait collection method text',
          'days',
          'Quantitative',
        ],
        [
          'Plant Height',
          'PH trait description text',
          'PHT',
          'PH trait collection method text',
          'cm',
          'Quantitative',
        ],
        [
          'Is Dead',
          'ID trait description text',
          'IS_D',
          'ID trait collection method text',
          'text',
          'Qualitative',
        ],
      ],
      'Triticum' => [
        [
          'Green Cotyledon Colour',
          'GCC trait description text',
          'GCC',
          'GCC trait collection method text',
          'colour',
          'Qualitative',
        ],
      ],
    ];

    foreach ($trait_values as $trait_genus => $values) {
      foreach ($values as $value) {
        $this->trait_set[$trait_genus][] = array_combine($trait_keys, $value);
      }
    }

    $good_research = 'A Good Research Experiment';
    $experiments = [
      $good_research => [
        'id' => 1,
        'genus' => ['Lens', 'Triticum'],
        'configure_genus' => TRUE,
        'content_type' => 'research_experiment',
      ],
    ];

    foreach ($experiments as $exp_name => $exp_values) {
      // Create Research Experiment Tripal content.
      $project_id = $this->chado_connection->insert('1:project')
        ->fields(['name'])
        ->values(['name' => $exp_name])
        ->execute();

      $entity = TripalEntity::create([
        'id' => $exp_values['id'],
        'type' => $exp_values['content_type'],
        'label' => $exp_name,
      ]);

      // Create and configure genus.
      if ($exp_values['genus'] && $exp_values['content_type'] == 'research_experiment') {
        $entity
          ->set('exp_name', ['record_id' => $project_id, 'value' => $exp_name]);

        foreach ($exp_values['genus'] as $i => $genus) {
          $this->chado_connection->insert('1:organism')
            ->fields(['genus', 'species', 'type_id'])
            ->values([
              'genus' => $genus,
              'species' => $this->getRandomGenerator()->word(10),
              'type_id' => 1,
            ])
            ->execute();

          if ($exp_values['configure_genus']) {
            $this->setOntologyConfig($genus);
          }

          $this->chado_connection->insert('1:projectprop')
            ->fields([
              'project_id' => $project_id,
              'type_id' => $config_terms['genus'],
              'value' => $genus,
              'rank' => $i + 1,
            ])
            ->execute();

          $entity
            ->set(self::FIELD_ORGANISM, ['record_id' => $project_id, 'genus_value' => $genus]);
        }
      }

      $entity->save();

      if ($exp_values['id'] == 1) {
        // Save the one entity with genus configured properly.
        $this->exp_entity = $entity;
      }
    }

    // Install trait combos to good research experiment.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $db_service = $this->container->get('database');
    $experiment = $this->exp_entity->get('exp_name')->getValue()[0];

    foreach ($experiments[$good_research]['genus'] as $genus) {
      $trait_service->setTraitGenus($genus);

      $insert_combo = $db_service->insert('trpcultivate_phenocombo')
        ->fields([
          'project_id',
          'attr_id',
          'observable_id',
          'unit_id',
          'label',
          'is_archived',
          'is_required',
          'was_collected',
          'was_shared',
          'uid',
          'timestamp',
        ]);

      foreach ($this->trait_set[$genus] as $combo) {
        $ids = $trait_service->insertTrait($combo);

        $insert_combo->values([
          $experiment['record_id'],
          $ids['trait'],
          $ids['method'],
          $ids['unit'],
          $combo['Trait Name'] . ':' . $combo['Method Short Name'],
          mt_rand(0, 1),
          mt_rand(0, 1),
          mt_rand(0, 1),
          mt_rand(0, 1),
          $this->container->get('current_user')->id(),
          time(),
        ]);
      }

      $insert_combo->execute();
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
  }

  /**
   * Test getFormId().
   */
  public function testGetFormId() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $this->assertEquals(
      'pheno_experiment_configuration_form',
      PhenoExperimentConfigurationForm::create($this->container)->getFormId(),
      'The form id returned does not match expected form id of experiment configuration form.'
    );
  }

  /**
   * Test buildForm().
   */
  public function testBuildForm() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $this->setCurrentUser(
      $this->createUser([$route->getRequirements()['_permission']])
    );

    // Experiment configuration form.
    // Prepare the route with slug value before loading the form.
    // @see drupal/core/tests/Drupal/Tests/Core/Routing/RouteMatchTest.php
    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $config_form = PhenoExperimentConfigurationForm::create($this->container)
      ->buildForm([], new FormState());

    ['record_id' => $experiment_id, 'value' => $experiment_name] = $this->exp_entity->get('exp_name')->getValue()[0];

    $this->assertEquals(
      $config_form['#title'],
      'Configure Phenotypes for ' . $experiment_name,
      'The page does not contain the phrase "Configure Phenotypes for" followed by the experiment name as the title of the page.'
    );

    // Test the raw render array.
    // Verify summary table toolbar components.
    $config_toolbar = $config_form['config_toolbar'];

    $this->assertEquals(
      'link',
      $config_toolbar['add_trait']['#type'],
      'The trait summary configuration form is expected to contain a link element',
    );

    $this->assertEquals(
      $title = 'Add Trait',
      $config_toolbar['add_trait']['#title'],
      'The trait summary configuration form is expected to contain a link titled ' . $title,
    );

    $this->assertEquals(
      'link',
      $config_toolbar['refresh']['#type'],
      'The trait summary configuration form is expected to contain a link element',
    );

    $this->assertEquals(
      $title = 'Refresh Table',
      $config_toolbar['refresh']['#title'],
      'The trait summary configuration form is expected to contain a link titled ' . $title,
    );

    $this->assertEquals(
      'select',
      $config_toolbar['filter_genus']['#type'],
      'The trait summary configuration form is expected to contain a select element',
    );

    $this->assertEquals(
      0,
      $config_toolbar['filter_genus']['#default_value'],
      'The trait summary configuration form is expected to contain a select field default to a genus',
    );

    $this->assertEquals(
      array_values($config_toolbar['filter_genus']['#options']),
      array_keys($this->trait_set),
      'The filter by genus field does not contain the expected filter options.'
    );

    // The main trait summary table.
    $summary_table_name = 'experiment_traits_summary_table';
    $this->assertArrayHasKey(
      $summary_table_name,
      $config_form,
      'The form expects a key ' . $summary_table_name . ' that holds the table render array.'
    );

    $this->assertEquals(
      $config_form[$summary_table_name]['#type'],
      'table',
      'The render array is expected to have a type table.'
    );

    $db_trait_count = $this->container->get('database')
      ->select(self::PHENO_COMBO_TABLE, 'c')
      ->fields('c', ['attr_id'])
      ->condition('c.project_id', $experiment_id, '=')
      ->groupBy('c.attr_id')
      ->countQuery()
      ->execute()
      ->fetchField();

    $setup_trait_count = array_reduce($this->trait_set, function ($prev, $cur) {
      return $prev + count(array_unique(array_column($cur, 'Trait Name')));
    });

    $this->assertEquals(
      $db_trait_count,
      $setup_trait_count,
      'The setup failed to insert the expected number of traits.'
    );

    $table_items = array_filter(array_keys($config_form[$summary_table_name]), function ($i) {
      return is_int($i);
    });

    $this->assertEquals(
      $setup_trait_count,
      count($table_items),
      'The table render array #rows property key does not contain the expected number of traits.'
    );

    // Test the summary table rendered markup.
    $render_config_form = $this->container->get('renderer')
      ->renderRoot($config_form);

    $genus_ontology_service = $this->container->get('trpcultivate_phenotypes.genus_ontology');

    $genus_map = [];
    foreach (array_keys($this->trait_set) as $genus) {
      $cv_id = $genus_ontology_service->getGenusOntologyConfigValues($genus)['trait'];
      $genus_map[$cv_id] = $genus;
    }

    // Using the same query setup as in the frontend, test that the expected row
    // is at the same table item row number in the markup.
    $query = $this->chado_connection->select(self::PHENO_COMBO_TABLE, 'tc');
    $query->join('1:cvterm', 't', 'tc.attr_id = t.cvterm_id');
    $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

    $query->fields('t', ['name', 'definition']);
    $query->fields('v', ['cv_id']);

    $query->addExpression(
      "JSON_AGG(JSON_BUILD_OBJECT(
        'combo_id', tc.combo_id,
        'attr_id', tc.attr_id,
        'observable_id', tc.observable_id,
        'unit_id', tc.unit_id,
        'label', tc.label,
        'is_archived', tc.is_archived,
        'is_required', tc.is_required,
        'was_shared', tc.was_shared,
        'was_collected', tc.was_collected
      ) ORDER BY tc.label ASC)", 'combos'
    );

    $query
      ->condition('tc.project_id', $experiment_id, '=')
      ->orderBy('v.name', 'ASC')
      ->orderBy('t.name', 'ASC')
      ->groupBy('t.name')
      ->groupBy('t.definition')
      ->groupBy('v.cv_id')
      ->groupBy('v.name');

    $query_result = $query->execute();

    $this->setRawContent($render_config_form);
    $table_rows = $this->cssSelect('table#tcp-phenocombo-summary-table tbody tr');

    $status_class = [
      'tcp-pheno-archived',
      'tcp-pheno-required',
      'tcp-pheno-shared',
      'tcp-pheno-collected',
    ];

    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');

    $genus_set = [];
    $row_i = 0;

    foreach ($query_result as $i => $trait_row) {
      $genus = $genus_map[$trait_row->cv_id];
      if (!in_array($genus, $genus_set)) {
        $trait_service->setTraitGenus($genus);
        array_push($genus_set, $genus);
      }

      $current_trait_row = $this->cssSelect('td p', $table_rows[$i])[$i]
        ->asXML();

      // The trait name and definition.
      $this->assertStringContainsString(
        $trait_row->name,
        $current_trait_row,
        'The trait name ' . $trait_row->name . ' is expected in table row #' . $i
      );

      $this->assertStringContainsString(
        $trait_row->definition,
        $current_trait_row,
        'The trait definition ' . $trait_row->definition . ' is expected in table row #' . $i
      );

      // Trait methods table.
      $current_method_row = $this->cssSelect('table.tcp-exp-phenocombo tbody tr', $table_rows[$i]);

      foreach (json_decode($trait_row->combos, TRUE) as $combo) {
        $method_markup = $current_method_row[$row_i]->asXML();

        $this->assertStringContainsString(
          $combo['label'],
          $method_markup,
          'The trait combo label ' . $combo['label'] . ' is expected in table row #' . $i
        );

        ['trait' => $trait, 'method' => $method, 'unit' => $unit] = $trait_service->getTraitMethodUnitCombo(
          $combo['attr_id'],
          $combo['observable_id'],
          $combo['unit_id'],
        );

        $this->assertStringContainsString(
          $trait->name,
          $method_markup,
          'The trait combo name ' . $trait->name . ' is expected in table row #' . $i
        );

        $this->assertStringContainsString(
          $method->name,
          $method_markup,
          'The trait combo method ' . $method->name . ' is expected in table row #' . $i
        );

        $this->assertStringContainsString(
          $unit->name,
          $method_markup,
          'The trait combo unit ' . $unit->name . ' is expected in table row #' . $i
        );

        $trait_status = [
          $combo['is_archived'],
          $combo['is_required'],
          $combo['was_shared'],
          $combo['was_collected'],
        ];

        foreach ($trait_status as $j => $status) {
          if ($status) {
            // A value 1 will render a coressponding trait status icon.
            $this->assertStringContainsString(
              $status_class[$j],
              $method_markup,
              'The trait combo is expected to have the icon status CSS class name ' . $status_class[$j] . ' in table row #' . $i
            );
          }
          else {
            $this->assertStringNotContainsString(
              $status_class[$j],
              $method_markup,
              'The trait combo is expected not to have the icon status CSS class name ' . $status_class[$j] . ' in table row #' . $i
            );
          }
        }

        // Operation options available to item depend on trait status values.
        $this->assertStringContainsString(
          'Remove',
          $method_markup,
          'The trait item is expected to contain a remove operation option.',
        );

        $this->assertStringContainsString(
          $set_to = 'Set as ' . (($combo['is_required']) ? 'Optional' : 'Required'),
          $method_markup,
          'The trait item is expected to contain operation option to ' . $set_to,
        );

        $this->assertStringContainsString(
          $set_to = (($combo['is_archived']) ? 'Restore' : 'Archive') . ' Trait',
          $method_markup,
          'The trait item is expected to contain operation option to ' . $set_to,
        );

        $row_i++;
      }
    }
  }

  /**
   * Test genus filter.
   */
  public function testGenusFilter() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $this->setCurrentUser(
      $this->createUser([$route->getRequirements()['_permission']])
    );

    $summary_table_name = 'experiment_traits_summary_table';
    $all_trait = [];

    // Switch between the genus.
    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);

    foreach ($this->trait_set as $organism => $traits) {
      $genus = $organism;

      $request->attributes->set('genus', $genus);
      $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

      $config_form = PhenoExperimentConfigurationForm::create($this->container)
        ->buildForm([], new FormState());

      $this->assertEquals(
        $config_form['config_toolbar']['filter_genus']['#default_value'],
        $genus,
        'The default selected option of the filter genus field does not match expected value.'
      );

      // All the trait names in a genus.
      $all_trait[$genus] = array_map(function ($t) {
        return $t['Trait Name'];
      }, $traits);

      $table_items = array_filter(array_keys($config_form[$summary_table_name]), function ($i) {
        return is_int($i);
      });

      $this->assertEquals(
        $count = count(array_unique($all_trait[$genus])),
        count($table_items),
        'The number of traits in genus ' . $genus . ' does not match expected count - ' . $count
      );

      foreach ($table_items as $delta) {
        preg_match('/<p class="tcp-trait-combo">(.*?)<br \/>/', $config_form[$summary_table_name][$delta]['trait_combo']['#prefix'], $match);

        $this->assertContains(
          $trait_name = $match[1],
          array_unique($all_trait[$genus]),
          'The trait name ' . $trait_name . ' is expected in genus ' . $genus . ' filter result.'
        );
      }
    }
  }

  /**
   * Test handleOperation().
   */
  public function testHandleOperation() {

    if (!$this->container->get('current_user')->id()) {
      $route = $this->container->get('router.route_provider')
        ->getRouteByName(self::ROUTE_NAME);

      $this->setCurrentUser(
        $this->createUser([$route->getRequirements()['_permission']])
      );
    }

    // Load and execute test sequence:
    // - Set is_required, was_collected etc. trait status to 0 for combo #1.
    // - Perform Set Required then revert value, repeat for other two status.
    // - Remove trait from experiment.
    // - Remove a restricted trait - (archived, collected or shared).
    $combo_one = 1;
    $db = $this->container->get('database');

    $db
      ->update(self::PHENO_COMBO_TABLE)
      ->fields([
        'is_required' => 0,
        'was_collected' => 0,
        'was_shared' => 0,
        'is_archived' => 0,
      ])
      ->condition('combo_id', $combo_one, '=')
      ->execute();

    $actions = [
      'require' => 'Set as Required',
      'optional' => 'Set as Optional',
      'archive' => 'Archive Trait',
      'active' => 'Restore Trait',
    ];

    foreach (array_keys($actions) as $operation) {
      $request = Request::create(
        Url::fromRoute(
          self::ROUTE_NAME,
          [
            'tripal_entity' => $this->exp_entity->id(),
          ],
          [
            'query' => [
              $operation => $combo_one,
            ],
          ]
        )->toString()
      );

      $this->assertStringContainsString(
        htmlentities('"' . $actions[$operation] . '"') . ' completed successfully.',
        $this->container->get('http_kernel')->handle($request)->getContent(),
        'The requested operation failed to set a status value',
      );
    }

    // Remove the combo.
    $request = Request::create(
      Url::fromRoute(
        self::ROUTE_NAME,
        [
          'tripal_entity' => $this->exp_entity->id(),
        ],
        [
          'query' => [
            'remove' => $combo_one,
          ],
        ]
      )->toString()
    );

    $this->assertStringContainsString(
      htmlentities('"Remove Trait"') . ' completed successfully.',
      $this->container->get('http_kernel')->handle($request)->getContent(),
      'The requested operation failed to remove a trait combo from the experiment.',
    );

    // Test removal of restricted trait.
    $db
      ->update(self::PHENO_COMBO_TABLE)
      ->fields([
        'was_collected' => 1,
        'was_shared' => 1,
        'is_archived' => 1,
      ])
      ->execute();

    $restricted_traits = $db
      ->select(self::PHENO_COMBO_TABLE, 't')
      ->fields('t', ['combo_id'])
      ->execute()
      ->fetchCol();

    // At this point, combo one has vanished.
    $this->assertNotContains(
      1,
      $restricted_traits,
      'The operation to remove combo 1 was not successful.',
    );

    foreach ($restricted_traits as $combo_id) {
      $request = Request::create(
        Url::fromRoute(
          self::ROUTE_NAME,
          [
            'tripal_entity' => $this->exp_entity->id(),
          ],
          [
            'query' => [
              'remove' => $combo_id,
            ],
          ]
        )->toString()
      );

      $this->assertStringContainsString(
        'Invalid request: Not allowed to remove trait marked archived, shared, or collected.',
        (string) $this->container->get('http_kernel')->handle($request)->getContent(),
        'The requested operation failed to display an error if removing a restricted trait.',
      );
    }
  }

  /**
   * Provide user with various access permission to test page access.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An array of values that describes the test user account setup. The
   *     following keys are used.
   *     - 'uid': the Drupal user id number.
   *     - 'name': user account name.
   *     - 'permissions': an array of permission requirements.
   *     - 'is_admin': indicates if the account is administrator account.
   *   - An array of expected values, with the following keys:
   *     - 'access_code': access status code returned when accessing a route.
   */
  public static function provideTestUser() {

    return [
      // #0: Anonymous user.
      [
        'anonymous user',
        [
          'uid' => 0,
          'name' => 'Anonymous',
          'permissions' => [],
          'is_admin' => FALSE,
        ],
        [
          'access_code' => 403,
        ],
      ],

      // #1: Drupal administrator.
      [
        'Drupal admin',
        [
          'uid' => 1,
          'name' => 'Administrator',
          'permissions' => [],
          'is_admin' => TRUE,
        ],
        [
          'access_code' => 200,
        ],
      ],

      // #2: Authenticated user.
      [
        'authenticated user',
        [
          'uid' => 2,
          'name' => 'Authenticated User',
          'permissions' => ['access content'],
          'is_admin' => FALSE,
        ],
        [
          'access_code' => 403,
        ],
      ],

      // #3: Tripal content administrator.
      [
        'Tripal content administrator',
        [
          'uid' => 3,
          'name' => 'Tripal Content Administrator',
          'permissions' => ['administer tripal content'],
          'is_admin' => FALSE,
        ],
        [
          'access_code' => 200,
        ],
      ],
    ];
  }

  /**
   * Test access permission requirements.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param array $user
   *   An array of values that describes the test user account setup. The
   *   following keys are used.
   *     - 'uid': the Drupal user id number.
   *     - 'name': user account name.
   *     - 'permissions': an array of permission requirements.
   *     - 'is_admin': indicates if the account is administrator account.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'access_code': access status code returned when accessing a route.
   *
   * @dataProvider provideTestUser
   */
  #[DataProvider('provideTestUser')]
  public function testPageAccess(string $scenario, array $user, array $expected) {

    user_logout();

    $new_user = $this->createUser(
      $user['permissions'],
      $user['name'],
      $user['is_admin'],
      ['uid' => $user['uid']]
    );

    if (!$user['is_admin']) {
      // Ensure non-administrator account was not assigned 1 as user id.
      $this->assertNotEquals($new_user->id(), 1, 'Non-admin test user must not have the magic user id of 1.');
    }

    $this->setCurrentUser($new_user);

    $page_url = Url::fromRoute(self::ROUTE_NAME, [
      'tripal_entity' => $this->exp_entity->id(),
      'genus' => 0,
    ])
      ->toString();

    $request = Request::create($page_url);

    $this->assertEquals(
      $expected['access_code'],
      $this->container->get('http_kernel')->handle($request)->getStatusCode(),
      'User access permission does not match expected access permission in scenario ' . $scenario
    );
  }

  /**
   * Provide test values to route parameters.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An array of values that will plug into the parameter requirements
   *     of the route. The following keys are used.
   *     - 'tripal_entity': the research experiment Tripal entity.
   *     - 'genus': the genus to be used a filter value.
   *   - An array of expected values, with the following key:
   *     - 'message': the expected message for a every set of parameter values.
   */
  public static function provideInvalidValues() {

    return [
      // #0: Genus is not configured for use by the experiment.
      [
        'unsupported genus',
        [
          'tripal_entity' => 1,
          'genus' => 'Spurious Genus',
        ],
        [
          'message' => 'The genus is not supported by the experiment.',
        ],
      ],

      // #1: Entity does not exist.
      [
        'entity not found',
        [
          'tripal_entity' => 999,
          'genus' => 'Lens',
        ],
        [
          'message' => 'The requested page could not be found.',
        ],
      ],

      // #2: Valid parameters.
      [
        'valid request',
        [
          'tripal_entity' => 1,
          'genus' => 'Lens',
        ],
        [
          'message' => '',
        ],
      ],
    ];
  }

  /**
   * Test valid route parameter and page exceptions.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param array $slug_values
   *   An array of values that will plug into the parameter requirements
   *   of the route. The following keys are used.
   *     - 'tripal_entity': the research experiment Tripal entity id.
   *     - 'genus': the genus to be used a filter value.
   * @param array $expected
   *   An array of expected values, with the following key:
   *     - 'message': the expected message for a every set of parameter values.
   *
   * @dataProvider provideInvalidValues
   */
  #[DataProvider('provideInvalidValues')]
  public function testPageExceptions(string $scenario, array $slug_values, array $expected) {

    if (!$this->container->get('current_user')->id()) {
      $route = $this->container->get('router.route_provider')
        ->getRouteByName(self::ROUTE_NAME);

      $this->setCurrentUser(
        $this->createUser([$route->getRequirements()['_permission']])
      );
    }

    $page_url = Url::fromRoute(self::ROUTE_NAME, [
      'tripal_entity' => $slug_values['tripal_entity'],
      'genus' => $slug_values['genus'],
    ])
      ->toString();

    $request = Request::create($page_url);
    $page = $this->container->get('http_kernel')->handle($request)
      ->getContent();

    if ($this->log_message) {
      $this->assertEquals(
        $expected['message'],
        $this->log_message,
        'The exception message does not match expected message in scenario: ' . $scenario
      );
    }
    else {
      $this->assertStringContainsString(
        $expected['message'],
        (string) $page,
        'The page does not contain expected error message in scenario ' . $scenario
      );
    }
  }

}
