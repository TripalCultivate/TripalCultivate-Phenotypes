<?php

namespace Drupal\trpcultivate_phenotypes\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoIntegrationSettingsForm;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests associated to PhenoIntegrationSettingsForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('configuration')]
#[RunTestsInSeparateProcesses]
class PhenoIntegrationSettingsFormTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;
  use TripalTestTrait;

  /**
   * Content type with data.
   *
   * @var string
   */
  const PROTECTED_CONTENT_TYPE = 'research_experiment';

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
   * Class container instance of integration settings form.
   *
   * @var \Drupal\trpcultivate_phenotypes\Form\PhenoIntegrationSettingsForm
   */
  protected PhenoIntegrationSettingsForm $integration_form;

   /**
   * Phenotypes integration service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings
   */
  protected $service_PhenoIntegration;

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

    // Creaate research experiment entity and assign trait-combo and data file.
    $project = 'Project Awesome';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $project])
      ->execute();

    $genus = 'Lens';
    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $genus,
        'species' => $this->randomString(),
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

    $research_entity = TripalEntity::create([
      'type' => self::PROTECTED_CONTENT_TYPE,
      'exp_name' => [
        'record_id' => $project_id,
        'name' => $project,
      ],
      'exp_germgenus' => [
        'value' => $genus,
        'type_id' => $config_terms['genus'],
      ],
    ]);

    $research_entity->save();

    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $trait_service->setTraitGenus($genus);
    $trait_ids = $trait_service->insertTrait(
      [
        'Trait Name' => 'Days to Flower',
        'Trait Description' => 'DTF trait description',
        'Method Short Name' => 'DTF',
        'Collection Method' => 'DTF trait collection method',
        'Unit' => 'days',
        'Type' => 'Quantitative',
      ]
    );

    $this->container->get('database')
      ->insert('trpcultivate_phenocombo')
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
        $trait_ids['trait'],
        $trait_ids['method'],
        $trait_ids['unit'],
        $this->randomString(),
        $status = mt_rand(0, 1),
        $status,
        $status,
        $status,
        $this->container->get('current_user')->id(),
        time(),
      ])
      ->execute();

    $pheno_backup = $this->container->get('entity_type.manager')
      ->getStorage('phenodata_backup')
      ->create([
        'id' => 1,
        'file_id' => 1,
        'project_id' => $project_id,
        'comments' => 'A backup file',
        'backup_date' => date('Y-m-d'),
        'user_id' => $this->container->get('current_user')->id(),
      ]);

    $pheno_backup->save();

    $this->setCurrentUser($this->createUser(['administer tripal']));

    $this->service_PhenoIntegration = $this->container->get('trpcultivate_phenotypes.pheno_integration');
    $this->integration_form = PhenoIntegrationSettingsForm::create($this->container);
  }

  /**
   * Test getFormId() method.
   */
  public function testGetFormId() {

    $this->assertEquals(
      'trpcultivate_phenotypes_pheno_integration_form',
      $this->integration_form->getFormId(),
      'Integraion settings form failed to return the expected form id.',
    );
  }

  /**
   * Test buildForm() method.
   */
  public function testBuildForm() {

    $form = [];
    $form_state = new FormState();

    $config_form = $this->integration_form->buildForm($form, $form_state);

    $this->assertCount(
      1,
      $this->container->get('messenger')->messagesByType('warning'),
      'Integration form is expected to contain a warning message about protected content types.',
    );

    $field_detail = 'integration_detail';
    $this->assertArrayHasKey(
      $field_detail,
      $config_form,
      'The config form is expected to contain a field named ' . $field_detail,
    );

    $field_container = $config_form[$field_detail];

    $field_options = $this->service_PhenoIntegration->getProjectBasedContentTypes();
    // Mark protected content type (research_experiment) and append content type
    // machine names ie. Research Experiment [research_experiment]*.
    array_walk($field_options, function (&$name, $key) {
      $name = $name . ' [' . $key . ']' . ($key == self::PROTECTED_CONTENT_TYPE ? '*' : '');
    });

    // Assertions pertaining to the integration multi-select fields.
    foreach ($this->service_PhenoIntegration->getPhenoIntegrations() as $integration) {
      $this->assertArrayHasKey(
        $integration,
        $field_container,
        'The configuration form is expected to contain a field named ' . $integration,
      );

      $this->assertEquals(
        'select',
        $field_container[$integration]['#type'],
       'Form is expected to contain a select field in ' . $integration,
      );

      $this->assertTrue(
        $field_container[$integration]['#multiple'],
        'Select field is configured as a multi-select field in ' . $integration,
      );

      $this->assertEquals(
        $field_options,
        $field_container[$integration]['#options'],
        'Multi-select field options does not match expected options in ' . $integration,
      );

      $this->assertEquals(
        $this->service_PhenoIntegration->getPhenoIntegratedContentTypes($integration),
        $field_container[$integration]['#default_value'],
        'Multi-select field defaults to Research Experiment in ' . $integration,
      );

      // Has expected protected content types.
      $this->assertEquals(
        [self::PROTECTED_CONTENT_TYPE],
        $form_state->get('protected_content_types')[$integration],
        'Field has protected content types defined in form_state variable.',
      );
    }

    $this->assertEquals(
      'Save Configuration',
      $config_form['save_configuration']['#value'],
      'Integration form is expected to contain a submit button to save configuration values.',
    );
  }

  /**
   * Test validateForm() method.
   */
  public function testValidateForm() {

    $form = [];
    $form_state = new FormState();

    // Build protected content type listing.
    $this->integration_form->buildForm($form, $form_state);

    $new_selections = [
      'genome_project' => 'genome_project',
      'research_study' => 'research_study',
      self::PROTECTED_CONTENT_TYPE => self::PROTECTED_CONTENT_TYPE,
    ];

    $pheno_integrations = $this->service_PhenoIntegration->getPhenoIntegrations();

    foreach ($pheno_integrations as $integration) {
      $form_state->setValue($integration, $new_selections);
    }

    $this->integration_form->validateForm($form, $form_state);
    $this->assertFalse(
      $form_state->hasAnyErrors(),
      'No errors when protected content type is maintained.',
    );

    // Excluding protected content type.
    array_pop($new_selections);
    foreach ($pheno_integrations as $integration) {
      $form_state->setValue($integration, $new_selections);
    }

    $this->integration_form->validateForm($form, $form_state);
    $this->assertTrue(
      $form_state->hasAnyErrors(),
      'Unselecting protected content types is expected to trigger an error.',
    );

    $validation_error = $form_state->getErrors();
    $this->assertStringContainsString(
      'Content types marked with asterisk symbol (*)',
      reset($validation_error),
      'Unselecting procted content types is expected to trigger an error.',
    );
  }

  /**
   * Test submitForm() method.
   */
  public function testSubmitForm() {

    $form = [];
    $form_state = new FormState();

    $new_selections = [
      'research_study' => 'research_study',
      self::PROTECTED_CONTENT_TYPE => self::PROTECTED_CONTENT_TYPE,
    ];

    $pheno_integrations = $this->service_PhenoIntegration->getPhenoIntegrations();

    foreach ($pheno_integrations as $integration) {
      // Before update.
      $this->assertEquals(
        $this->service_PhenoIntegration->getPhenoIntegratedContentTypes($integration),
        [self::PROTECTED_CONTENT_TYPE],
        'Before Update: Content types do not match expected types in ' . $integration,
      );

      $form_state->setValue($integration, $new_selections);
    }

    // After update.
    $this->integration_form->submitForm($form, $form_state);

    foreach ($pheno_integrations as $integration) {
      $this->assertEquals(
        $this->service_PhenoIntegration->getPhenoIntegratedContentTypes($integration),
        array_keys($new_selections),
        'After Update: Content types do not match expected types in ' . $integration,
      );
    }
  }

  /**
   * Test getProtectedContentTypes() method.
   */
  public function testGetProtectedContentTypes() {

    $form = [];
    $form_state = new FormState();

    // Build protected content type listing.
    $this->integration_form->buildForm($form, $form_state);

    foreach ($this->service_PhenoIntegration->getPhenoIntegrations() as $integration) {
      $this->assertEquals(
        $form_state->get('protected_content_types')[$integration],
        [self::PROTECTED_CONTENT_TYPE],
        'Failed to determine expected list of protected content types in ' . $integration,
      );
    }
  }

}
