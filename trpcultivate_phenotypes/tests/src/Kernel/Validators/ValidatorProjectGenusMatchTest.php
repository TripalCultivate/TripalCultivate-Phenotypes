<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests Tripal Cultivate Phenotypes Metadata Validator Plugins.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
class ValidatorProjectGenusMatchTest extends ChadoTestKernelBase {
  use PhenotypeImporterTestTrait;

  /**
   * The Validators plugin manager for creating new validator instances.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * An array of genus for testing.
   *
   * Has the following keys:
   * - 'configured': a configured genus.
   * - 'configured-secondary': a configured genus used as secondary genus.
   * - 'not-configured': a genus that is not configured.
   *
   * @var array
   */
  protected array $test_genus;

  /**
   * An array of projects for testing.
   *
   * Has the following keys:
   * - 'project-with-configgenus': a project paired with a configured genus.
   * - 'project-with-2-configgenus': a project paired with 2 configured genus.
   * - 'project-with-genus': a project paired with a non-configured genus.
   * - 'project-genus-not-tru-service': a project paired with a configured genus
   *   without using the genus_project service.
   * - 'just-project': a project record not paired to a genus.
   *
   * @var array
   */
  protected array $test_project;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes', 'trpcultivate']);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    $genus = 'Tripalus';
    // Create our organism and configure it.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (configured).');
    $this->test_genus['configured'] = $genus;
    $this->setOntologyConfig($this->test_genus['configured']);

    $genus = 'Plantanus';
    // Create our organism and configure it.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (configured).');
    $this->test_genus['configured-secondary'] = $genus;
    $this->setOntologyConfig($this->test_genus['configured-secondary']);

    // Create another organism and not configure.
    $genus = 'notconfiggenus';
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (not configured).');
    $this->test_genus['not-configured'] = $genus;

    // Set terms configuration.
    $this->setTermConfig();

    // Create test project with a genus set.
    $projects = [
      'project-with-configgenus' => $this->test_genus['configured'],
      'project-with-2-configgenus' => $this->test_genus['configured-secondary'],
      'project-with-genus' => $this->test_genus['not-configured'],
      'project-genus-not-tru-service' => $this->test_genus['configured'],
      'just-project' => '',
    ];

    $genus_term = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.ontology.terms.genus');

    foreach ($projects as $test_case => $project_genus) {
      $project = 'Research Project: ' . $test_case;
      $project_id = $this->chado_connection->insert('1:project')
        ->fields([
          'name' => $project,
          'description' => 'A test project',
        ])
        ->execute();

      $this->assertIsNumeric($project_id, 'We were not able to create a project ' . $test_case . ' for testing.');
      $this->test_project[$test_case]['id']    = $project_id;
      $this->test_project[$test_case]['name']  = $project;
      $this->test_project[$test_case]['genus'] = $project_genus;

      if ($test_case == 'project-with-configgenus') {
        // Create project - genus relationship.
        $project_prop = \Drupal::service('trpcultivate_phenotypes.genus_project')
          ->setGenusToProject($this->test_project[$test_case]['id'], $this->test_project[$test_case]['genus']);

        $this->assertTrue($project_prop, 'We were not able to create a project-genus (for: ' . $test_case . ') property for testing.');
      }

      if ($test_case == 'project-with-2-configgenus') {
        // Create project - genus relationship.
        $project_prop = \Drupal::service('trpcultivate_phenotypes.genus_project')
          ->setGenusToProject($this->test_project[$test_case]['id'], $this->test_project[$test_case]['genus']);

        // Assign a scondary genus to project.
        $this->chado_connection->insert('1:projectprop')
          ->fields([
            'project_id' => $this->test_project[$test_case]['id'],
            'type_id' => $genus_term,
            'value' => $this->test_genus['configured'],
            'rank' => 2,
          ])
          ->execute();
      }

      if ($test_case == 'project-genus-not-tru-service') {
        // Create project-genus relationship not using the project-genus service
        // to relate a project to a genus.
        // Using term: null.
        $project_prop = $this->chado_connection->insert('1:projectprop')
          ->fields([
            'project_id' => $project_id,
            'type_id' => 1,
            'value' => $project_genus,
          ])
          ->execute();

        $this->assertNotEmpty($project_prop, 'We were not able to create a project-genus (for: ' . $test_case . ') property for testing.');
      }
    }
  }

  /**
   * Test project and genus input - project exists and configured genus matches.
   */
  public function testProjectGenusInput() {
    // Create a plugin instance for this validator.
    $validator_id = 'project_genus_match';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Test items that will throw exception:
    // 1. Passing a string value.
    // 2. Failed to implement form field element with project and genus.
    // 3. Passing object or the entire $form_state.
    // Test passing a string value.
    $form_values = 'Not a valid form values';

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a string to project genus match metadata validator.');
    $this->assertStringContainsString(
      'Argument #1 ($form_values) must be of type array, string given', $exception_message,
      'Expected exception message does not match message when passing string to project genus match metadata validator.');

    // No project field.
    $form_values = ['genus' => 'Lens'];

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no project form field was implemented.');
    $this->assertStringContainsString('Failed to locate project field element', $exception_message,
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key project.');

    // No genus field.
    $form_values = ['project' => 'Test Project'];

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no genus form field was implemented.');
    $this->assertStringContainsString('Failed to locate genus field element', $exception_message,
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key genus.');

    // A Drupal $form_state object.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['project' => uniqid()]);

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to project genus match metadata validator.');
    $this->assertStringContainsString(
      'Argument #1 ($form_values) must be of type array, Drupal\Core\Form\FormState given', $exception_message,
      'Expected exception message does not match message when passing $form_state to project genus match metadata validator.');

    // Other tests:
    // Each test checks if projectGenusMatch generated the correct validation
    // items.
    // Failed item is the failed project/genus value. Failed information is
    // contained in the case to indicate project and genus match.
    // Test project does not exist.
    $project = 'project-' . uniqid();
    $genus = $this->test_genus['configured'];

    $form_values = ['project' => $project, 'genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Project does not exist', $validation_status['case'],
      'Project genus match validator case title does not match expected title for non-existent project.');
    $this->assertFalse($validation_status['valid'], 'A failed project must return a FALSE valid status.');
    $this->assertEquals($project, $validation_status['failedItems']['project_provided'], 'Failed project value is expected in failed items.');

    // Test project exists but not attached to any genus.
    $project = $this->test_project['just-project']['id'];
    $genus = $this->test_project['just-project']['genus'];

    $form_values = ['project' => $project, 'genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Project has no genus set and could not compare with the genus provided', $validation_status['case'],
      'Project genus match validator case title does not match expected title for a valid project+genus.');
    $this->assertFalse($validation_status['valid'], 'A failed project-genus must return a FALSE valid status.');
    $this->assertEquals($genus, $validation_status['failedItems']['genus_provided'], 'Failed genus value is expected in failed items.');

    // Test project exists but is attached to a different genus.
    $project = $this->test_project['project-with-configgenus']['id'];
    $genus = $this->test_project['project-with-genus']['genus'];

    $form_values = ['project' => $project, 'genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Genus does not match the genus set to the project', $validation_status['case'],
      'Project genus match validator case title does not match expected title for a valid project+genus.');
    $this->assertFalse($validation_status['valid'], 'A failed project-genus must return a FALSE valid status.');
    $this->assertEquals($genus, $validation_status['failedItems']['genus_provided'], 'Failed genus value is expected in failed items.');

    // Test a project-genus created not using the project-genus service.
    $project = $this->test_project['project-genus-not-tru-service']['id'];
    $genus = $this->test_project['project-genus-not-tru-service']['genus'];

    $form_values = ['project' => $project, 'genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Project has no genus set and could not compare with the genus provided', $validation_status['case'],
      'Project genus match validator case title does not match expected title for a valid project+genus.');
    $this->assertFalse($validation_status['valid'], 'A failed project-genus must return a FALSE valid status.');
    $this->assertEquals($genus, $validation_status['failedItems']['genus_provided'], 'Failed genus value is expected in failed items.');

    // Test project with genus set.
    $project = $this->test_project['project-with-configgenus']['id'];
    $genus = $this->test_project['project-with-configgenus']['genus'];

    $form_values = ['project' => $project, 'genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Project exists and project-genus match the genus provided', $validation_status['case'],
      'Project genus match validator case title does not match expected title for a valid project+genus.');
    $this->assertTrue($validation_status['valid'], 'A valid project+genus must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid project+genus does not return a failed item value.');

    // Test a project with multiple genus.
    $project = $this->test_project['project-with-2-configgenus']['id'];
    $genus = $this->test_genus['configured-secondary'];

    $form_values = ['project' => $project, 'genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Project exists and project-genus match the genus provided', $validation_status['case'],
      'Project genus match validator case title does not match expected title for a valid project+genus (project with multiple genus).');
    $this->assertTrue($validation_status['valid'], 'A valid project+genus must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid project+genus does not return a failed item value.');
  }

}
