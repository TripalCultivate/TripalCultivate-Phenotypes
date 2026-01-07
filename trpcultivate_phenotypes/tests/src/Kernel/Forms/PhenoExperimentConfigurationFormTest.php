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
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentConfigurationForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with PhenoExperimentConfigurationForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('configuration')]
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
    'markup',
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
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_configuration';

  /**
   * The table name.
   *
   * @var string
   */
  const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Expected research experiment entity id to describe a setup.
   *
   * @var array.
   */
  const ENTITY_ID = [
    'good' => 1,
    'incomplete' => 2,
    'no_trait' => 3,
    'is_not' => 4,
  ];

  /**
   * Test genus with a set of test traits.
   *
   * @var array
   */
  private $trait_set = [
    'Lens' => [
      [
        'Trait Name' => 'Days to Flower',
        'Trait Description' => 'DTF trait description text',
        'Method Short Name' => 'DTF',
        'Collection Method' => 'DTF trait collection method text',
        'Unit' => 'days',
        'Type' => 'Quantitative',
      ],
      [
        'Trait Name' => 'Plant Height',
        'Trait Description' => 'PH trait description text',
        'Method Short Name' => 'PHT',
        'Collection Method' => 'PH trait collection method text',
        'Unit' => 'cm',
        'Type' => 'Quantitative',
      ],
      [
        'Trait Name' => 'Is Dead',
        'Trait Description' => 'ID trait description text',
        'Method Short Name' => 'IS_D',
        'Collection Method' => 'ID trait collection method text',
        'Unit' => 'text',
        'Type' => 'Qualitative',
      ],
    ],
    'Triticum' => [
      [
        'Trait Name' => 'Green Cotyledon Colour',
        'Trait Description' => 'GCC trait description text',
        'Method Short Name' => 'GCC',
        'Collection Method' => 'GCC trait collection method text',
        'Unit' => 'colour',
        'Type' => 'Qualitative',
      ],
    ],
  ];

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

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $config_terms = $this->setTermConfig();

    // Create test records.
    $good_research = 'A Good Research Experiment';
    $experiments = [
      $good_research => [
        'id' => self::ENTITY_ID['good'],
        'genus' => ['Lens', 'Triticum'],
        'configure_genus' => TRUE,
        'content_type' => 'research_experiment',
      ],
      'Incomplete Research Experiment' => [
        'id' => self::ENTITY_ID['incomplete'],
        'genus' => ['NOT_CONFIGURED_GENUS'],
        'configure_genus' => FALSE,
        'content_type' => 'research_experiment',
      ],
      'Research Experiment without a Trait' => [
        'id' => self::ENTITY_ID['no_trait'],
        'genus' => ['Rosa'],
        'configure_genus' => TRUE,
        'content_type' => 'research_experiment',
      ],
      'Not a Research Experiment' => [
        'id' => self::ENTITY_ID['is_not'],
        'genus' => [],
        'configure_genus' => FALSE,
        'has_traits' => FALSE,
        'content_type' => 'research_study',
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
            ->set('exp_germgenus', ['record_id' => $project_id, 'value' => $genus]);
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

    $headers = ['label', 'combo', 'operations'];
    $this->assertEquals(
      array_keys($config_form[$summary_table_name]['#header']),
      $headers,
      'The summary listing table render array is missing expected header.',
    );

    $db_trait_count = $this->container->get('database')
      ->select(self::PHENO_COMBO_TABLE, 'c')
      ->condition('c.project_id', $experiment_id, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    $setup_trait_count = array_reduce($this->trait_set, function ($prev, $cur) {
      return $prev + count($cur);
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

    // Using the same query setup, test that the expected row is at the same
    // table item row number in the markup.
    $query = $this->chado_connection->select(self::PHENO_COMBO_TABLE, 'tc');
    $query->join('1:cvterm', 't', 'tc.attr_id = t.cvterm_id');
    $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

    $query
      ->fields('tc', [
        'combo_id',
        'attr_id',
        'observable_id',
        'unit_id',
        'label',
        'is_archived',
        'is_required',
        'was_shared',
        'was_collected',
      ])
      ->fields('t', ['cv_id', 'name'])
      ->condition('tc.project_id', $experiment_id, '=')
      ->orderBy('v.name', 'ASC')
      ->orderBy('t.name', 'ASC')
      ->orderBy('label', 'ASC');

    $query_result = $query->execute();

    $this->setRawContent($render_config_form);
    $table_rows = $this->cssSelect('tbody tr');

    $status_class = [
      'tcp-pheno-archived',
      'tcp-pheno-required',
      'tcp-pheno-shared',
      'tcp-pheno-collected',
    ];

    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');

    $genus_set = [];
    foreach ($query_result as $i => $trait_row) {
      $genus = $genus_map[$trait_row->cv_id];
      if (!in_array($genus, $genus_set)) {
        $trait_service->setTraitGenus($genus);
        array_push($genus_set, $genus);
      }

      $current_row = $table_rows[$i]->asXML();

      $this->assertStringContainsString(
        $trait_row->label,
        $current_row,
        'The trait combo label ' . $trait_row->label . ' is expected in table row #' . $i
      );

      ['trait' => $trait, 'method' => $method, 'unit' => $unit] = $trait_service->getTraitMethodUnitCombo(
        $trait_row->attr_id,
        $trait_row->observable_id,
        $trait_row->unit_id,
      );

      $this->assertStringContainsString(
        $trait->name,
        $current_row,
        'The trait combo name ' . $trait->name . ' is expected in table row #' . $i
      );

      $this->assertStringContainsString(
        $method->name,
        $current_row,
        'The trait combo method ' . $method->name . ' is expected in table row #' . $i
      );

      $this->assertStringContainsString(
        $unit->name,
        $current_row,
        'The trait combo unit ' . $unit->name . ' is expected in table row #' . $i
      );

      $trait_status = [
        $trait_row->is_archived,
        $trait_row->is_required,
        $trait_row->was_shared,
        $trait_row->was_collected,
      ];

      foreach ($trait_status as $j => $status) {
        if ($status) {
          // A value 1 will insert a coressponding trait status icon.
          $this->assertStringContainsString(
            $status_class[$j],
            $current_row,
            'The trait combo is expected to have the icon status CSS class name ' . $status_class[$j] . ' in table row #' . $i
          );
        }
        else {
          $this->assertStringNotContainsString(
            $status_class[$j],
            $current_row,
            'The trait combo is expected not to have the icon status CSS class name ' . $status_class[$j] . ' in table row #' . $i
          );
        }
      }
    }
  }

  /**
   * Test handleOperation().
   */
  public function testHandleOperation() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));
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

}
