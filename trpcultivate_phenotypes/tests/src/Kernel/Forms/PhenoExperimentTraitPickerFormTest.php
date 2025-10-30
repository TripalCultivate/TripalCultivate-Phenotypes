<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteObjectInterface;
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
   * Expected research experiment entity id to describe a setup.
   *
   * @var int.
   */
  const ENTITY_ID = 1;

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
    $good_research = 'A Good Research Experiment';
    $experiments = [
      $good_research => [
        'id' => self::ENTITY_ID,
        'genus' => ['Lens'],
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
            ->set('exp_germgenus', ['record_id' => $project_id, 'value' => $genus]);
        }
      }

      $entity->save();

      if ($exp_values['id'] == 1) {
        // Save the one entity with genus configured properly.
        $this->research_experiment_entity = $entity;
      }
    }

    // Install trait combos to good research experiment.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $db_service = $this->container->get('database');
    $experiment = $this->research_experiment_entity->get('exp_name')->getValue()[0];

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
    $request->attributes->set('tripal_entity', $this->research_experiment_entity);
    $this->container->set('current_route_match', RouteMatch::createFromRequest($request));

    $this->assertEquals(
      'content_bio_data_research_experiment_trait_picker_form',
      PhenoExperimentTraitPickerForm::create($this->container)->getFormId(),
      'The form id returned does not match expected form id of experiment configuration form.'
    );
  }

  /**
   * Test buildForm().
   */
  public function testBuildForm() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    // Experiment configuration form.
    // Prepare the route with slug value before loading the form.
    // @see drupal/core/tests/Drupal/Tests/Core/Routing/RouteMatchTest.php
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
      'The warning message in the trait picker form does not match expected message.',
    );

    // Search components.
    $search_components = $picker_form['dialog_wrapper']['search_fieldset'];

    $this->assertStringContainsString(
      'Suggest A Trait',
      $search_components['controls']['#markup'],
      'The trait picker is expected to contain a link to suggest a trait.'
    );

    $this->assertStringContainsString(
      'Show All Traits',
      $search_components['controls']['#markup'],
      'The trait picker is expected to contain a link to show all traits.'
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
      'The trait picker form is expected to contain a genus field of type option select.',
    );

    $this->assertEquals(
      $search_components[$genus_field]['#default_value'],
      $genus,
      'The trait picker form is expected to contain a genus field of type option select default to ' . $genus,
    );

    $trait_field = 'trait';
    $this->assertArrayHasKey(
      $trait_field,
      $search_components,
      'The trait picker form is expected to contain a search trait field.',
    );

    $this->assertEquals(
      $search_components[$trait_field]['#type'],
      'textfield',
      'The trait picker form is expected to contain a trait field of type text.',
    );

    $this->assertNotNull(
      $search_components[$trait_field]['#autocomplete_route_name'],
      $genus,
      'The trait picker form is expected to contain a trait field with autocomplete settings',
    );

    $this->assertEquals(
      $search_components[$trait_field]['#autocomplete_route_parameters']['cv_id'],
      $this->container->get('trpcultivate_phenotypes.genus_ontology')
        ->getGenusOntologyConfigValues($genus)['trait'],
      'The trait autocomplete search field is not set to the expected genus configuration value.'
    );

    // Trait search result container.
    $result_container = 'result_wrapper';
    $this->assertArrayHasKey(
      $result_container,
      $picker_form['dialog_wrapper'],
      'The trait picker form is expected to contain a container element to render search trait result.',
    );

    $this->assertEquals(
      $picker_form['dialog_wrapper'][$result_container]['#type'],
      'container',
      'The trait picker form is expected to contain a container element to render search trait result of type container.',
    );

    $this->assertStringContainsString(
      'Show All Traits',
      $picker_form['dialog_wrapper'][$result_container]['#markup'],
      'With default genus, the search result container is expected to contain a suggestion title - Show All Traits.'
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

    // Show all available traits - none found since all traits have been
    // assigned to the experiment in setUp().
    $form_state->setValue('trait', 'All');

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
      ->condition('combo_id', 0, '>')
      ->execute();

    $form_state->setValue('trait', 'All');
    $search_result = PhenoExperimentTraitPickerForm::create($this->container)
      ->matchTrait($form, $form_state);

    $trait_names = array_column($this->trait_set['Lens'], 'Trait Name');
    asort($trait_names);

    $this->setRawContent($search_result->getCommands()[0]['data']);
    $result_items = $this->cssSelect('tbody tr');

    foreach (array_values($trait_names) as $i => $trait) {
      $this->assertStringContainsString(
        $trait,
        (string) $result_items[$i]->asXML(),
        'The search result is expected to contain the trait ' . $trait . ' at line ' . $i
      );
    }

    // Suggest one mathing trait.
    $form_state->setValue('trait', 'Days');
    $search_result = PhenoExperimentTraitPickerForm::create($this->container)
      ->matchTrait($form, $form_state);

    $this->setRawContent($search_result->getCommands()[0]['data']);
    $result_items = $this->cssSelect('tbody tr');

    $this->assertEquals(
      1,
      count($result_items),
      'The search result is expected to contain just one trait'
    );
  }

}
