<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentTraitSelectorForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with PhenoExperimentTraitSelectorForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('phenotypes_configuration')]
class PhenoExperimentTraitSelectorFormTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
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

      $this->setOntologyConfig($ins_genus);

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
    $this->research_experiment_entity = $entity;

    // Install trait combos.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $trait_service->setTraitGenus($genus);

    // Only Lens genus gets a set of traits.
    foreach ($this->trait_set[$genus] as $combo) {
      $trait_service->insertTrait($combo);
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
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
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
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
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

    $this->AssertEquals(
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
      $search_toolbar['suggest']['#type'],
      'The trait selector form is expected to contain a link element.',
    );

    $this->assertStringContainsString(
      $title = 'Suggest a Trait',
      $search_toolbar['suggest']['#title'],
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
      $title = 'Close & Update Traits',
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
      'Start typing part of the trait name into the search field to search for specific traits, or click - Show all Traits button, to explore all available traits for the selected genus.',
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
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
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
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
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
          $method_name,
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

    $genus = array_keys($this->trait_set)[0];
    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $request = new Request();
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, self::ROUTE_NAME);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
    $request->attributes->set('genus', $genus);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $database = $this->container->get('database');
    $form_state = new FormState();
    $form = [];

    $experiment_id = $this->research_experiment_entity->get('exp_name')
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

}
