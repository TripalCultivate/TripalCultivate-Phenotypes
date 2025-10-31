<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentTraitPickerForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with PhenoExperimentTraitPickerForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('configuration')]
class PhenoExperimentTraitPickerFormTest extends ChadoTestKernelBase {

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
  private TripalEntity $research_experiment_entity;

  /**
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_trait_picker';

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
        'Trait Name' => 'Days to Flower',
        'Trait Description' => 'DTF trait description text',
        'Method Short Name' => 'DTF60',
        'Collection Method' => 'DTF @60 trait collection method text',
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
    $config_terms = $this->setTermConfig();

    // Create test records.
    $experiment = 'A Good Research Experiment';
    $genus = array_keys($this->trait_set)[0];

    // Create Research Experiment Tripal content.
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $experiment])
      ->execute();

    $entity = TripalEntity::create([
      'id' => 1,
      'type' => 'research_experiment',
      'label' => $experiment,
    ]);

    $entity->set('exp_name', ['record_id' => $project_id, 'value' => $experiment]);

    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $genus,
        'species' => $this->getRandomGenerator()->word(10),
        'type_id' => 1,
      ])
      ->execute();

    $this->setOntologyConfig($genus);

    $this->chado_connection->insert('1:projectprop')
      ->fields([
        'project_id' => $project_id,
        'type_id' => $config_terms['genus'],
        'value' => $genus,
        'rank' => 1,
      ])
      ->execute();

    $entity->set('exp_germgenus', ['record_id' => $project_id, 'value' => $genus]);
    $entity->save();

    // Save the one entity with genus configured properly.
    $this->research_experiment_entity = $entity;

    // Install trait combos to good research experiment.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $trait_service->setTraitGenus($genus);

    $db_service = $this->container->get('database');
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
        $project_id,
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

  /**
   * Test getFormId().
   */
  public function testGetFormId() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $this->assertEquals(
      'content_bio_data_research_experiment_trait_picker_form',
      PhenoExperimentTraitPickerForm::create($this->container)->getFormId(),
      'The trait picker form id returned does not match expected form id.'
    );
  }

  /**
   * Test buildForm().
   */
  public function testBuildForm() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
    $request->attributes->set('genus', $genus = array_keys($this->trait_set)[0]);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $picker_form = PhenoExperimentTraitPickerForm::create($this->container)
      ->buildForm([], new FormState());

    // Form has a reminder.
    $reminder = 'reminder';
    $this->assertArrayHasKey(
      $reminder,
      $picker_form,
      'The trait picker form is expected to contain a reminder.',
    );

    $this->AssertEquals(
      $picker_form[$reminder]['#message_list']['warning'][0],
      'Read trait details carefully to ensure you are selecting the correct trait, as some traits may appear similar or have subtle differences.',
      'The warning message in the trait picker form reminder does not match expected message.',
    );

    // Search components.
    $search_components = $picker_form['dialog_wrapper']['search_fieldset'];

    $this->assertStringContainsString(
      'Suggest A Trait',
      $search_components['controls']['#markup'],
      'The trait picker form is expected to contain a link to suggest a trait.'
    );

    $this->assertStringContainsString(
      'Show All Traits',
      $search_components['controls']['#markup'],
      'The trait picker form is expected to contain a link to show all traits.'
    );

    $genus_field = 'genus';
    $this->assertArrayHasKey(
      $genus_field,
      $search_components,
      'The trait picker form is expected to contain a genus field.',
    );

    $this->assertEquals(
      $search_components[$genus_field]['#type'],
      'select',
      'The trait picker form is expected to contain a genus field of type option select.'
    );

    $this->assertEquals(
      $search_components[$genus_field]['#default_value'],
      $genus,
      'The trait picker form is expected to contain a genus field of type option select default to ' . $genus
    );

    $trait_field = 'trait';
    $this->assertArrayHasKey(
      $trait_field,
      $search_components,
      'The trait picker form is expected to contain a search trait field.'
    );

    $this->assertEquals(
      $search_components[$trait_field]['#type'],
      'textfield',
      'The trait picker form is expected to contain a search trait field of type text.'
    );

    $this->assertNotNull(
      $search_components[$trait_field]['#autocomplete_route_name'],
      $genus,
      'The trait picker form is expected to contain a trait search field configured as autocomplete element'
    );

    $this->assertEquals(
      $search_components[$trait_field]['#autocomplete_route_parameters']['cv_id'],
      $this->container->get('trpcultivate_phenotypes.genus_ontology')
        ->getGenusOntologyConfigValues($genus)['trait'],
      'The trait autocomplete search field is expected to be set to the default genus cv configuration.'
    );

    // Trait search result container.
    $result_container = 'result_wrapper';
    $this->assertArrayHasKey(
      $result_container,
      $picker_form['dialog_wrapper'],
      'The trait picker form is expected to contain a container element used as wrapper to render search trait result.'
    );

    $this->assertEquals(
      $picker_form['dialog_wrapper'][$result_container]['#type'],
      'container',
      'The trait picker form is expected to contain a container element of type container.',
    );

    $this->assertStringContainsString(
      'Show All Traits',
      $picker_form['dialog_wrapper'][$result_container]['#markup'],
      'The search result container is expected to contain a suggestion titled - Show All Traits.'
    );
  }

  /**
   * Test trait autocomplete search field callback method.
   */
  public function testMatchTrait() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
    $request->attributes->set('genus', $genus = array_keys($this->trait_set)[0]);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $form_state = new FormState();
    $form = PhenoExperimentTraitPickerForm::create($this->container)
      ->buildForm([], $form_state);

    $form_state->set('genus', $genus);
    $form_state->set('result_wrapper', 'tcp-result-wrapper');
    $form_state->set(
      'genus_config',
      $this->container->get('trpcultivate_phenotypes.genus_ontology')
        ->getGenusOntologyConfigValues($genus)['trait']
    );

    $form_state->set(
      'project_id',
      $this->research_experiment_entity->get('exp_name')
        ->getValue()[0]['record_id']
    );

    // Show all available traits - none found since all traits have been
    // assigned to the experiment in setUp().
    $form_state->setValue('trait', 'All');
    $search_result = PhenoExperimentTraitPickerForm::create($this->container)
      ->matchTrait($form, $form_state);

    $this->assertStringContainsString(
      'No traits found or traits may have already been included in the experiment.',
      (string) $search_result->getCommands()[0]['data'],
      'The search result is expected to return empty result since traits have already been assigned to experiment.'
    );

    // Desociate all previously assigned traits from the experiment
    // and repeat the search trait.
    $this->chado_connection->delete('trpcultivate_phenocombo')
      ->execute();

    $form_state->setValue('trait', 'All');
    $search_result = PhenoExperimentTraitPickerForm::create($this->container)
      ->matchTrait($form, $form_state);

    $this->setRawContent($search_result->getCommands()[0]['data']);
    $result_items = $this->cssSelect('tbody tr');

    $trait_names = array_column($this->trait_set[$genus], 'Trait Name');
    asort($trait_names);

    foreach (array_values(array_unique($trait_names)) as $i => $trait) {
      $this->assertStringContainsString(
        $trait,
        (string) $result_items[$i]->asXML(),
        'The search result is expected to contain the trait ' . $trait . ' at line ' . $i
      );
    }

    // Suggest one mathing trait.
    $key = 'Day';
    $form_state->setValue('trait', $key);
    $search_result = PhenoExperimentTraitPickerForm::create($this->container)
      ->matchTrait($form, $form_state);

    $this->setRawContent($search_result->getCommands()[0]['data']);
    $result_items = $this->cssSelect('tbody tr');

    $this->assertEquals(
      1,
      count($result_items),
      'The search result is expected to contain just one trait.'
    );

    $this->assertStringContainsString(
      $key,
      (string) $result_items[0]->asXML(),
      'The search result is expected to contain the key ' . $key
    );

    $result_item_methods = $this->cssSelect('section');
    $this->assertEquals(
      2,
      count($result_item_methods),
      'The search result is expected to contain just one trait with 2 methods (DTFs).'
    );

    // Trait not found.
    $form_state->setValue('trait', 'Spurious Trait');
    $search_result = PhenoExperimentTraitPickerForm::create($this->container)
      ->matchTrait($form, $form_state);

    $this->assertStringContainsString(
      'No traits found or traits may have already been included in the experiment.',
      (string) $search_result->getCommands()[0]['data'],
      'The search result is expected to return empty result since trait key does not match any trait.'
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
   *     - 'tripal_entity': the research experiment Tripal entity id.
   *     - 'genus': the genus to be used a filter value.
   *   - An array of expected values, with the following key:
   *     - 'message': the expected message for a every set of parameter values.
   */
  public static function provideInvalidValues() {

    return [
      // #0: Experiment does not exist.
      [
        'research experiment does not exists',
        [
          'tripal_entity' => 999,
          'genus' => 0,
        ],
        [
          'message' => 'Page not found',
        ],
      ],

      // #1: Valid parameters, default to specific genus.
      [
        'valid research experiment and a genus',
        [
          'tripal_entity' => 1,
          'genus' => 'Lens',
        ],
        [
          'message' => '',
        ],
      ],

      // #2: Valid parameters, no genus default value.
      [
        'valid research experiment and user to select a genus',
        [
          'tripal_entity' => 1,
          'genus' => 0,
        ],
        [
          'message' => '',
        ],
      ],
    ];
  }

  /**
   * Test page exceptions.
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

    $this->assertStringContainsString(
      $expected['message'],
      (string) $page,
      'The page does not contain expected error message in scenario ' . $scenario
    );
  }

}
