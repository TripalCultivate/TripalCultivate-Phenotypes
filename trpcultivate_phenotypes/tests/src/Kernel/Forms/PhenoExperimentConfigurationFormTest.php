<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Core\Url;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentConfigurationForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with PhenoExperimentConfigurationForm class.
 */
class PhenoExperimentConfigurationFormTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;

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
  private $research_experiment_entity;

  /**
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_configuration';

  /**
   * Test genus with a set of test traits.
   *
   * @var array
   */
  private $trait_set = [
    'Lens:culinaris' => [
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
    'Triticum:durum' => [
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
    $this->installSchema('trpcultivate_phenotypes', ['trpcultivate_phenocombo']);
    $this->installEntitySchema('user');

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $this->setTermConfig();

    // Create experiment (project).
    $experiment_name = 'My Research Experiment';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $experiment_name])
      ->execute();

    // Create tripal entity.
    $this->research_experiment_entity = TripalEntity::create([
      'id' => uniqid(),
      'type' => 'research_experiment',
      'label' => $experiment_name,
    ]);

    $this->research_experiment_entity
      ->set('exp_name', ['record_id' => $project_id, 'value' => $experiment_name])
      ->save();

    // Configure the genus.
    $genus_key = [];
    foreach ($this->trait_set as $organism => $traits) {
      [$genus, $species] = explode(':', $organism);
      $genus_key[$organism] = $genus;

      $this->chado_connection->insert('1:organism')
        ->fields(['genus', 'species', 'type_id'])
        ->values([
          'genus' => $genus,
          'species' => $species,
          'type_id' => 1,
        ])
        ->execute();

      $this->setOntologyConfig($genus);
    }

    // Insert trait-method-unit then add combo to an experiment.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $genus_project_service = $this->container->get('trpcultivate_phenotypes.genus_project');
    $database_service = $this->container->get('database');

    foreach ($genus_key as $organism => $genus) {
      $trait_service->setTraitGenus($genus);

      foreach ($this->trait_set[$organism] as $trait) {
        $ids = $trait_service->insertTrait($trait);

        $database_service->insert('trpcultivate_phenocombo')
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
          ])
          ->values([
            $project_id,
            $ids['trait'],
            $ids['method'],
            $ids['unit'],
            $trait['Trait Name'] . ':' . $trait['Method Short Name'],
            mt_rand(0, 1),
            mt_rand(0, 1),
            mt_rand(0, 1),
            mt_rand(0, 1),
            $this->container->get('current_user')->id(),
            time(),
          ])
          ->execute();
      }

      $genus_project_service->setGenusToProject($project_id, $genus);
    }
  }

  /**
   * Test getFormId().
   */
  public function testGetFormId() {

    $this->assertEquals(
      'content_bio_data_research_experiment_configure_form',
      PhenoExperimentConfigurationForm::create($this->container)->getFormId(),
      'The form id returned does not match expected form id of experiment configuration form.'
    );
  }

  /**
   * Test access permission requirements.
   */
  public function testPageAccess() {

    $http_kernel_service = $this->container->get('http_kernel');

    $page_url = Url::fromRoute(self::ROUTE_NAME, [
      'tripal_entity' => $this->research_experiment_entity->id(),
      'genus' => 0,
    ])
      ->toString();

    // Permissions and page status access codes.
    // 403 - unauthorized access.
    // 200 - Ok.
    // @see trpcultivate_phenotypes.experiment_configuration route.
    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $users = [
      0 => [
        'name' => 'Anonymous',
        'permissions' => [],
        'access' => 403,
        'is_admin' => FALSE,
      ],
      1 => [
        'name' => 'Administrator',
        'permissions' => [],
        'access' => 200,
        'is_admin' => TRUE,
      ],
      2 => [
        'name' => 'Authenticated User',
        'permissions' => ['access content'],
        'access' => 403,
        'is_admin' => FALSE,
      ],
      3 => [
        'name' => 'Tripal Content Administrator',
        'permissions' => [$route->getRequirements()['_permission']],
        'access' => 200,
        'is_admin' => FALSE,
      ],
    ];

    foreach ($users as $uid => $user) {
      $new_user = $this->createUser($user['permissions'], $user['name'], $user['is_admin'], ['uid' => $uid]);
      if (!$user['is_admin']) {
        // Ensure non-administrator account was not assigned 1 as user id.
        $this->assertNotEquals($new_user->id(), 1, 'Test user must not have the magic user id of 1');
      }

      $this->setCurrentUser($new_user);
      $request = Request::create($page_url);

      $this->assertEquals(
        $user['access'],
        $http_kernel_service->handle($request)->getStatusCode(),
        'User access permission does not match expected access permission.'
      );
    }
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
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $config_form = PhenoExperimentConfigurationForm::create($this->container)
      ->buildForm([], new FormState());

    ['record_id' => $experiment_id, 'value' => $experiment_name] = $this->research_experiment_entity->get('exp_name')->getValue()[0];

    // Test table render array.
    $this->assertEquals(
      $config_form['#title'],
      'Phenotypes: ' . $experiment_name,
      'The page does not contain the word Phenotypes: followed by the experiment name as the title of the page.'
    );

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

    $headers = ['label', 'trait_combo', 'remove'];
    $this->assertEquals(
      array_keys($config_form[$summary_table_name]['#header']),
      $headers,
      'The summary listing table render array is missing expected header.',
    );

    $filter_genus_options = array_map(function ($g) {
      return explode(':', $g)[0];
    }, array_keys($this->trait_set));

    array_unshift($filter_genus_options, 'All Genus');

    $this->assertEquals(
      array_values($config_form[$summary_table_name]['#header'][$headers[1]]['data']['#options']),
      $filter_genus_options,
      'The filter by genus field does not contain the expected filter options.'
    );

    $db_trait_count = $this->container->get('database')
      ->select('trpcultivate_phenocombo', 'c')
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

    $render_array_count = count($config_form[$summary_table_name]['#rows']);
    $this->assertEquals(
      $render_array_count,
      $setup_trait_count,
      'The table render array #rows property key does not contain the expected number of traits.'
    );
  }

  /**
   * Test page markup.
   */
  public function testPageMarkup() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $this->setCurrentUser(
      $this->createUser([$route->getRequirements()['_permission']])
    );

    $page_url = Url::fromRoute(self::ROUTE_NAME, [
      'tripal_entity' => $this->research_experiment_entity->id(),
      'genus' => 0,
    ])
      ->toString();

    $request = Request::create($page_url);
    $page = $this->container->get('http_kernel')->handle($request)
      ->getContent();

    ['record_id' => $experiment_id, 'value' => $experiment_name] = $this->research_experiment_entity->get('exp_name')
      ->getValue()[0];

    $this->assertStringContainsString(
      'Phenotypes: ' . $experiment_name,
      (string) $page,
      'The page does not contain the expected page title containing the experiment name.'
    );

    $genus_ontology_service = $this->container->get('trpcultivate_phenotypes.genus_ontology');

    $genus_map = [];
    foreach (array_keys($this->trait_set) as $genus) {
      $genus = explode(':', $genus)[0];
      $cv_id = $genus_ontology_service->getGenusOntologyConfigValues($genus)['trait'];
      $genus_map[$cv_id] = $genus;
    }

    // Using the same query setup, test that the expected row is at the same
    // table item row number in the markup.
    $query = $this->chado_connection->select('trpcultivate_phenocombo', 'tc');
    $query->join('1:cvterm', 't', 'tc.attr_id = t.cvterm_id');
    $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

    $query
      ->fields('tc', [
        'combo_id',
        'attr_id',
        'observable_id',
        'unit_id', 'label',
        'is_archived',
        'is_required',
        'was_shared',
        'was_collected',
      ])
      ->fields('t', ['cv_id'])
      ->condition('tc.project_id', $experiment_id, '=')
      ->orderBy('v.name', 'ASC')
      ->orderBy('is_required', 'DESC')
      ->orderBy('label', 'ASC');

    $query_result = $query->execute();

    $this->setRawContent($page);
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

      $this->assertStringContainsString(
        $remove = ($trait_row->is_archived || $trait_row->was_collected || $trait_row->was_shared) ? '-' : 'x',
        $current_row,
        'The trait combo is expected to have the character ' . $remove . ' as the remove trait option in table row #' . $i
      );
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
    foreach ($this->trait_set as $organism => $traits) {
      $genus = explode(':', $organism)[0];

      $request = new Request();
      $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
      $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
      $request->attributes->set('tripal_entity', $this->research_experiment_entity);
      $request->attributes->set('genus', $genus);
      $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

      $config_form = PhenoExperimentConfigurationForm::create($this->container)
        ->buildForm([], new FormState());

      $this->assertEquals(
        $config_form[$summary_table_name]['#header']['trait_combo']['data']['#value'],
        $genus,
        'The default selected option of the filter genus field does not match expected value.'
      );

      // All the trait names in a genus.
      $all_trait[$genus] = array_map(function ($t) {
        return $t['Trait Name'];
      }, $traits);

      $this->assertEquals(
        $count = count($all_trait[$genus]),
        count($config_form[$summary_table_name]['#rows']),
        'The number of traits in genus ' . $genus . ' does not match expected count - ' . $count
      );

      foreach ($config_form[$summary_table_name]['#rows'] as $row) {
        $this->assertContains(
          $trait_name = $row['data'][1]['data']['#props']['name'],
          $all_trait[$genus],
          'The trait name ' . $trait_name . ' is expected in genus ' . $genus . ' filter result.'
        );
      }
    }
  }

}
