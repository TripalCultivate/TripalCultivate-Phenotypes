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
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentTraitSelectorForm;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests associated with PhenoExperimentTraitSelectorForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('phenotypes_configuration')]
#[RunTestsInSeparateProcesses]
class PhenoExperimentTraitSelectorFormTest extends ChadoTestKernelBase {

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
   * Test genus with a set of test traits.
   *
   * The genus with empty trait is used to test multiple genus features of the
   * trait selector form.
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
    'Triticum' => [],
  ];

  /**
   * Trait selector route machine name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_traitselector';

  /**
   * Container/wrapper element name.
   */
  const FORM_WRAPPER = 'form_wrapper';

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
      ->onlyMethods(['defineTerms', 'getTermId'])
      ->getMock();

    $mock_terms_service->method('defineTerms')
      ->willReturn($terms);

    $mock_terms_service->method('getTermId')
      ->willReturn(1);

    $this->container->set('trpcultivate_phenotypes.terms', $mock_terms_service);

    // Create test records.
    $experiment = 'A Good Research Experiment';
    $genus = array_keys($this->trait_set)[0];

    // Create Research Experiment Tripal content.
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $experiment])
      ->execute();

    $mock_ontology_service = $this->getMockBuilder(TripalCultivatePhenotypesGenusOntologyService::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('tripal_chado.database'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['getGenusOntologyConfigValues'])
      ->getMock();

    $entity = TripalEntity::create([
      'id' => 1,
      'type' => 'research_experiment',
      'label' => $experiment,
    ]);

    $entity->set('exp_name', ['record_id' => $project_id, 'value' => $experiment]);

    $mock_return_map = [];
    // Pair both test genus to the experiment.
    foreach (array_keys($this->trait_set) as $i => $ins_genus) {
      $this->chado_connection->insert('1:organism')
        ->fields(['genus', 'species', 'type_id'])
        ->values([
          'genus' => $ins_genus,
          'species' => $this->getRandomGenerator()->word(10),
          'type_id' => 1,
        ])
        ->execute();

      $config_genus = $this->setOntologyConfig($ins_genus);
      $genus_config = [];
      foreach ($config_genus as $config => $config_value) {
        $genus_config[$config] = ($config == 'database') ? $config_value['db_id'] : $config_value['cv_id'];
      }

      $mock_return_map[$ins_genus] = $genus_config;

      $this->chado_connection->insert('1:projectprop')
        ->fields([
          'project_id' => $project_id,
          'type_id' => $config_terms['genus'],
          'value' => $ins_genus,
          'rank' => $i + 1,
        ])
        ->execute();

      $entity->set('exp_germgenus', ['record_id' => $project_id, 'value' => $ins_genus]);
    }

    $entity->save();

    // Set this class property to reference the experiment entity created.
    $this->exp_entity = $entity;

    $mock_ontology_service->method('getGenusOntologyConfigValues')
      ->willReturnMap([
      ['Lens', $mock_return_map['Lens']],
      ['Triticum', $mock_return_map['Triticum']],
      ]);

    $this->container->set('trpcultivate_phenotypes.genus_ontology', $mock_ontology_service);

    // Install trait combos.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $trait_service->setTraitGenus($genus);

    // Only Lens genus gets a set of traits.
    foreach ($this->trait_set[$genus] as $combo) {
      $trait_service->insertTrait($combo);
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
      'pheno_experiment_trait_selector_form',
      PhenoExperimentTraitSelectorForm::create($this->container)->getFormId(),
      'The trait selector form id returned does not match expected form id.'
    );
  }

  /**
   * Test buildForm().
   */
  public function testBuildForm() {

    $genus = array_keys($this->trait_set)[0];
    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    $request->attributes->set('genus', $genus);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $select_form = PhenoExperimentTraitSelectorForm::create($this->container)
      ->buildForm([], new FormState());

    // Form has a reminder.
    $reminder = 'reminder';
    $this->assertArrayHasKey(
      $reminder,
      $select_form,
      'The trait selector form is expected to contain a status message reminder element.',
    );

    $this->assertEquals(
      $select_form[$reminder]['#message_list']['warning'][0],
      'Read trait details carefully to ensure you are selecting the correct trait, as some traits may appear similar or have subtle differences.',
      'The reminder status message in the trait selector form does not match expected message.',
    );

    // Has a container element used as the main AJAX form wrapper element.
    $this->assertArrayHasKey(
      self::FORM_WRAPPER,
      $select_form,
      'The trait selector form is expected to contain a container element.',
    );

    $this->assertEquals(
      'tcp-form-wrapper',
      $select_form[self::FORM_WRAPPER]['#attributes']['id'],
      'The container element is expected to contain an element attribute id.',
    );

    // Elements in the search toolbar.
    $search_toolbar = $select_form[self::FORM_WRAPPER]['search_toolbar'];

    $this->assertEquals(
      'link',
      $search_toolbar['import_traits']['#type'],
      'The trait selector form is expected to contain a link element.',
    );

    $this->assertStringContainsString(
      $title = 'Import Traits',
      $search_toolbar['import_traits']['#title'],
      'The trait selector form is expected to contain a link titled ' . $title,
    );

    $this->assertEquals(
      'button',
      $search_toolbar['show_all']['#type'],
      'The trait selector form is expected to contain a button element.',
    );

    $this->assertStringContainsString(
      $title = 'Show all Traits',
      $search_toolbar['show_all']['#value'],
      'The trait selector form is expected to contain a button titled ' . $title,
    );

    $this->assertEquals(
      'button',
      $search_toolbar['close']['#type'],
      'The trait selector form is expected to contain a button element.',
    );

    $this->assertStringContainsString(
      $title = 'Close & Refresh Table',
      $search_toolbar['close']['#value'],
      'The trait selector form is expected to contain a button titled ' . $title,
    );

    // Elements in the search fieldset.
    $search_fieldset = $select_form[self::FORM_WRAPPER]['search_fieldset']['flex_container'];

    $genus_field = 'genus';
    $this->assertEquals(
      'select',
      $search_fieldset[$genus_field]['#type'],
      'The trait selector form is expected to contain a genus field of type option select.',
    );

    $this->assertEquals(
      $genus,
      $search_fieldset[$genus_field]['#default_value'],
      'The trait selector form is expected to contain a genus field of type option select default to ' . $genus,
    );

    $trait_field = 'trait';
    $this->assertEquals(
      'textfield',
      $search_fieldset[$trait_field]['#type'],
      'The trait selector form is expected to contain a search trait field of type textfield.',
    );

    $this->assertEquals(
      'tripal_chado.cvterm_autocomplete',
      $search_fieldset[$trait_field]['#autocomplete_route_name'],
      'The trait selector form is expected to contain a trait search field configured as autocomplete element.',
    );

    $this->assertEquals(
      $this->container->get('trpcultivate_phenotypes.genus_ontology')
        ->getGenusOntologyConfigValues($genus)['trait'],
      $search_fieldset[$trait_field]['#autocomplete_route_parameters']['cv_id'],
      'The trait autocomplete search field is expected to be set to the default genus cv configuration.',
    );

    // The trait selector form includes a helpful note to guide user in
    // getting started with search.
    $this->assertStringContainsString(
      'Start typing part of the trait name into the search field to search for specific traits, or click "Show all Traits" button, to explore all available traits for the selected genus.',
      $select_form[self::FORM_WRAPPER]['search_tooltips']['#children']['a_tip']['#value'],
      'The trait selector form is expected to contain a search tip.',
    );
  }

  /**
   * Test setGenus().
   *
   * Setting a genus will update the cv_id route parameter of the trait
   * autocomplete search field.
   */
  public function testSetGenus() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    // No particular genus provided - the genus field is open for selection.
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $form_state = new FormState();
    $form = [];

    foreach (array_keys($this->trait_set) as $genus) {
      $form_state->setValue('genus', $genus);

      $form_state->setTriggeringElement([
        '#name' => 'genus',
      ]);

      $select_form = PhenoExperimentTraitSelectorForm::create($this->container)
        ->buildForm($form, $form_state);

      // Elements in the search fieldset.
      $search_fieldset = $select_form[self::FORM_WRAPPER]['search_fieldset']['flex_container'];

      $this->assertEquals(
        $genus,
        $search_fieldset['genus']['#default_value'],
        'The default genus does not match expected genus - ' . $genus,
      );

      $this->assertEquals(
        $this->container->get('trpcultivate_phenotypes.genus_ontology')
          ->getGenusOntologyConfigValues($genus)['trait'],
        $search_fieldset['trait']['#autocomplete_route_parameters']['cv_id'],
        'The trait autocomplete search field route parameter cv_id does not match expected value.',
      );
    }
  }

  /**
   * Test loadGenusTrait().
   */
  public function testLoadGenusTraits() {

    $genus = array_keys($this->trait_set)[0];
    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    $request->attributes->set('genus', $genus);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $form_state = new FormState();
    $form = [];

    foreach ($this->trait_set[$genus] as $trait) {
      $trait_name = $trait['Trait Name'];
      $form_state->setValue('trait', $search_key = substr($trait_name, 0, 5));

      $form_state->setTriggeringElement([
        '#name' => 'trait',
        '#value' => $search_key,
        '#attributes' => [
          'class' => [
            'trigger-element',
          ],
        ],
      ]);

      $select_form = PhenoExperimentTraitSelectorForm::create($this->container)
        ->buildForm($form, $form_state);

      // Verify raw render array.
      $this->assertEquals(
        $trait_name,
        $select_form[self::FORM_WRAPPER]['table'][0]['combo']['#props']['name'],
        'The raw render array of the search result does not contain the item trait name.',
      );

      $this->assertEquals(
        $trait['Trait Description'],
        $select_form[self::FORM_WRAPPER]['table'][0]['combo']['#props']['definition'],
        'The raw render array of the search result does not contain the item trait definition.',
      );

      // Group trait that have identical names to organize methods under same
      // trait name.
      $trait_group = array_filter($this->trait_set[$genus], function ($t) use ($trait_name) {
        return $t['Trait Name'] == $trait_name;
      });

      $combo_items = $select_form[self::FORM_WRAPPER]['table'][0]['combo']['#props']['method_unit_combo'];

      $this->assertEquals(
        count($trait_group),
        count($combo_items),
        'The raw render array of the search result does not contain the exact number of method-unit combo items.',
      );

      $trait_group_methods = array_column($trait_group, 'Method Short Name');
      $this->assertEquals(
        $trait_group_methods,
        array_column($combo_items, 'method_shortname'),
        'The raw render array of the search result does not contain the exact method-unit combo items.',
      );

      // Verify the rendered markup.
      $rendered_form = $this->container->get('renderer')->renderRoot($select_form);
      $this->setRawContent($rendered_form);

      $trait_name = $this->cssSelect('tbody tr td div def')[0];
      $this->assertStringContainsString(
        $trait['Trait Name'],
        (string) $trait_name,
        'The rendered markup of the search result does not contain the item trait name.',
      );

      $trait_definition = $this->cssSelect('tbody tr td div em')[0];
      $this->assertStringContainsString(
        $trait['Trait Description'],
        (string) $trait_definition,
        'The rendered markup of the search result does not contain the item trait definition.',
      );

      $trait_methods = $this->cssSelect('tbody tr td div div section def');
      foreach ($trait_methods as $render_method) {
        $method_name = explode(':', strip_tags($render_method->asXML()))[0];

        $this->assertContains(
          trim($method_name),
          $trait_group_methods,
          'The rendered markup of the search result does not contain the exact method-unit combo items.',
        );
      }
    }
  }

  /**
   * Test addTraitCombo().
   */
  public function testAddTraitCombo() {

    // Load and execute test sequence:
    // - Each test trait is searched (partial keyword) and added to experiment.
    // - Successful assignement to experiment is verified (input label etc.).
    // - The same trait is re-added to validate handling of duplicate label.
    $genus = array_keys($this->trait_set)[0];
    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->exp_entity);
    $request->attributes->set('genus', $genus);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $database = $this->container->get('database');
    $form_state = new FormState();
    $form = [];

    $experiment_id = $this->exp_entity->get('exp_name')
      ->getValue()[0]['record_id'];

    foreach ($this->trait_set[$genus] as $trait) {
      $trait_name = $trait['Trait Name'];
      $form_state->setValue('trait', $search_key = substr($trait_name, 0, 5));

      $form_state->setTriggeringElement([
        '#name' => 'trait',
        '#value' => $search_key,
        '#attributes' => [
          'class' => [
            'trigger-element',
          ],
        ],
      ]);

      $select_form = PhenoExperimentTraitSelectorForm::create($this->container)
        ->buildForm($form, $form_state);

      $traits_table = $select_form[self::FORM_WRAPPER]['table'];

      $combo_key = array_keys($traits_table[0]['combo']['content'])[0];
      $combo = $traits_table[0]['combo']['content'][$combo_key][$combo_key . '_combo']['#value'];
      $form_state->setValue($combo_key . '_combo', $combo);

      $input_label = $this->getRandomGenerator()->word(10);
      $form_state->setValue($combo_key . '_label', $input_label);

      $input_required = mt_rand(0, 1);
      $form_state->setValue($combo_key . '_required', $input_required);

      $form_state->setTriggeringElement([
        '#name' => $combo_key . '_add',
        '#value' => 'Add',
        '#id' => $combo_key,
        '#attributes' => [
          'class' => [
            'trigger-element',
          ],
        ],
      ]);

      PhenoExperimentTraitSelectorForm::create($this->container)
        ->buildForm($form, $form_state);

      [$attr_id, $observable_id, $unit_id] = explode(':', $combo);

      $combo = $database->select('trpcultivate_phenocombo', 'tc')
        ->fields('tc', ['label', 'is_required'])
        ->condition('project_id', $experiment_id, '=')
        ->condition('attr_id', $attr_id, '=')
        ->condition('observable_id', $observable_id, '=')
        ->condition('unit_id', $unit_id, '=')
        ->execute()
        ->fetchAssoc();

      $this->assertNotNull($combo, 'Failed to add a trait to the experiment.');

      $this->assertEquals(
        $input_label,
        $combo['label'],
        'The trait label of the trait added does not match label provided.',
      );

      $this->assertEquals(
        $input_required,
        $combo['is_required'],
        'The trait status is_required of the trait added does not match the status is_required provided.',
      );

      // Test error - case if label already in use.
      $form_state->setValue($combo_key . '_label', $input_label);
      $form_state->setValue($combo_key . '_required', 1);

      $form_state->setTriggeringElement([
        '#name' => $combo_key . '_add',
        '#value' => 'Add',
        '#id' => $combo_key,
        '#attributes' => [
          'class' => [
            'trigger-element',
          ],
        ],
      ]);

      $select_form = PhenoExperimentTraitSelectorForm::create($this->container)
        ->buildForm($form, $form_state);

      $rendered_form = $this->container->get('renderer')->renderRoot($select_form);
      $this->assertStringContainsString(
        'The label is already used in the experiment.',
        (string) $rendered_form,
        'The add trait functionality is expected to show warning message if a label is reused in the same experiment.',
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
