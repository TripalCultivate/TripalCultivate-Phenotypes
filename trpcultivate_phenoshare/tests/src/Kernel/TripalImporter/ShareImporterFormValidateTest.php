<?php

namespace Drupal\Tests\trpcultivate_phenoshare\Kernel\TripalImporter;

use Drupal\Core\Form\FormState;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;

/**
 * Tests the formValidate() functionality of the Share Importer.
 *
 * @group shareImporter
 */
class ShareImporterFormValidateTest extends ChadoTestKernelBase {

  use UserCreationTrait;
  use TripalCultivateImporterTestTrait;
  use PhenotypeImporterTestTrait;

  /**
   * A project name.
   *
   * @var string
   */
  private const TEST_PROJECT = 'Test Project';

  /**
   * A genus name.
   *
   * @var string
   */
  private const TEST_GENUS = 'Test Genus';

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected string $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
    'trpcultivate_phenoshare',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $form_builder;

  /**
   * The path to tripalcultivate_phenotypes module.
   *
   * @var string
   */
  private $module_path;

  /**
   * A default listing of annotations associated with our importer.
   *
   * @var array
   */
  protected array $definitions = [
    'test-share-importer' => [
      'id' => 'trpcultivate-phenotypes-share-importer',
      'label' => 'Tripal Cultivate: Phenotypic Share Importer',
      'description' => 'Imports phenotypic data which has already been published or which is ready to be freely shared.',
      'file_types' => ["tsv"],
      'use_analysis' => FALSE,
      'require_analysis' => FALSE,
      'upload_title' => 'Phenotypic Share Data File*',
      'upload_description' => 'This should not be visible!',
      'button_text' => 'Import',
      'file_upload' => TRUE,
      'file_load' => FALSE,
      'file_remote' => FALSE,
      'file_required' => FALSE,
      'cardinality' => 1,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Open connection to Chado.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Ensure we can access file_managed related functionality from Drupal.
    // ... users need access to system.action config?
    $this->installConfig([
      'system',
      'trpcultivate',
      'trpcultivate_phenotypes',
      'trpcultivate_phenoshare',
    ]);
    // ... managed files are associated with a user.
    $this->installEntitySchema('user');
    // ... Finally the file module + tables itself.
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('tripal_chado', ['tripal_custom_tables']);
    // Ensure we have our tripal import tables.
    $this->installSchema('tripal', ['tripal_import', 'tripal_jobs']);
    // Create and log-in a user.
    $this->setUpCurrentUser();

    // We need to mock the logger to test the progress reporting.
    $container = \Drupal::getContainer();
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['notice', 'error'])
      ->getMock();
    $mock_logger->method('error')
      ->willReturnCallback(function ($message, $context, $options) {
        // @todo Revisit print out of log messages, but perhaps setting an option
        // for log messages to not print to the UI?
        // print str_replace(array_keys($context), $context, $message);
        return NULL;
      });
    $container->set('tripal.logger', $mock_logger);

    $this->form_builder = $container->get('form_builder');

    // Configure module.
    $this->setTermConfig();

    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => self::TEST_GENUS,
        'species' => 'some species',
      ])
      ->execute();

    $this->setOntologyConfig(self::TEST_GENUS);

    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => self::TEST_PROJECT,
        'description' => 'some description',
      ])
      ->execute();

    // Create test project-genus pair.
    $container->get('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject($project_id, self::TEST_GENUS);

    $this->module_path = $this->container->get('module_handler')
      ->getModule('trpcultivate_phenoshare')
      ->getPath();
  }

  /**
   * Data Provider: provides input values and expected validation result.
   */
  public function provideFormInputValues() {
    return [
      // #0: Project not provided.
      [
        'no project',
        [
          'project' => '',
          'genus' => self::TEST_GENUS,
          'filename' => 'importer_share_data_file.tsv',
        ],
        [
          'project_genus_match' => [
            'title' => 'Project exists and project-genus match the genus provided',
            'status' => 'fail',
            'details' => 'The project provided does not exist. Please contact your administrator to have this added.'
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
        ],
        1,
      ],

      // #1: No project and genus provided.
      [
        'no project and genus',
        [
          'project' => '',
          'genus' => '',
          'filename' => 'importer_share_data_file.tsv',
        ],
        [
          'project_genus_match' => [
            'title' => 'Project exists and project-genus match the genus provided',
            'status' => 'fail',
            'details' => 'The project provided does not exist. Please contact your administrator to have this added.'
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
        ],
        2,
      ],

      // #3: Project does not exists
      [
        'project does not exist',
        [
          'project' => 'A spurious project',
          'genus' => self::TEST_GENUS,
          'filename' => 'importer_share_data_file.tsv',
        ],
        [
          'project_genus_match' => [
            'title' => 'Project exists and project-genus match the genus provided',
            'status' => 'fail',
            'details' => 'The project provided does not exist. Please contact your administrator to have this added.'
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
        ],
        0,
      ],
    ];
  }

  /**
   * Test Stage 1 validation aspect of Phenotypes Share Importer form.
   *
   * @param string $scenario
   * @param array $input_values
   * @param array $expected
   *
   * @dataProvider provideFormInputValues
   */
  public function testShareImporterFormValidateStage1(string $scenario, array $input_values, array $expected_validator_results, int $expected_num_form_validation_errors) {

    // Setup form_state.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$this->definitions['test-share-importer']['id']]);

    $form_state->setValue('current_stage', 1);
    $form_state->setValue('project', $input_values['project']);
    $form_state->setValue('genus', $input_values['genus']);

    $test_file = $this->createTestFile([
      'filename' => $input_values['filename'],
      'content' => [
        'file' => $input_values['filename'],
        'fixturepath' => $this->module_path . '/tests/src/Fixtures/ShareImporterFiles/',
      ],
    ]);

    $form_state->setValue('file_upload', $test_file->id());

    // Submit form for validation.
    $form_id = 'Drupal\tripal\Form\TripalImporterForm';
    $this->form_builder->submitForm($form_id, $form_state);
    $form = $this->form_builder->retrieveForm($form_id, $form_state);

    // Validation result window is in accordion stage 1.
    $form_stage1 = $form['accordion_stage1'];

    // Looking for form validation errors.
    $form_validation_messages = $form_state->getErrors();
    $helpful_output = [];
    foreach ($form_validation_messages as $element => $markup) {
      $helpful_output[] = $element . " => " . (string) $markup;
    }

    $this->assertCount(
      $expected_num_form_validation_errors,
      $form_validation_messages,
      "The number of form state errors we expected (" . $expected_num_form_validation_errors . ") does not match what we received: " . implode(" AND ", $helpful_output) . ' in scenario: ' . $scenario
    );

    // Confirm that there is a validation window open.
    $this->assertArrayHasKey('validation_result', $form_stage1,
      "We expected a validation failure reported via our plugin setup but it's not showing up in the form in scenario: $scenario.");

    $validation_element_data = $form_stage1['validation_result']['#data']['validation_result'];

    // Now check our expectations are met.
    foreach ($expected_validator_results as $validation_plugin => $expected) {
      // Check status.
      $this->assertEquals(
        $expected['status'],
        $validation_element_data[$validation_plugin]['status'],
        "We expected the form validation element to indicate the $validation_plugin plugin had the specified status in scenario: $scenario."
      );

      // We don't want the value of 'details' in $expectations (from the data
      // provider) to be empty since assertStringContainsString() will evaluate
      // to true in that scenario. It can be tempting to set it to empty and
      // then come back to it when you figure out what the expected string
      // should be- just don't do it!
      if (array_key_exists('details', $expected)) {
        $this->assertNotEmpty(
          $expected['details'],
          "An empty string was provided with a 'details' key within the data provider - trust me, don't do that! in scenario: $scenario"
        );

        // Now check details.
        $this->assertIsArray(
          $validation_element_data[$validation_plugin]['details'],
          "We expected the details for $validation_plugin to be an array, but it is not in scenario: $scenario."
        );

        // Check for the key #type which is common in all render arrays.
        $this->assertArrayHasKey('#type', $validation_element_data[$validation_plugin]['details'], "We expected the details for $validation_plugin to be a render array by having the #type key, but it does not in scenario: $scenario.");

        // Walk recursively through the render array, and report whether our
        // 'details' item is present in the array or not.
        $item_to_find = $expected['details'];
        $found = FALSE;
        array_walk_recursive(
          $validation_element_data[$validation_plugin]['details'],
          function ($item, $key) use (&$found, $item_to_find) {
            if ($item == $item_to_find) {
              $found = TRUE;
            }
          }
        );

        $this->assertTrue($found, "We expected to find \"$item_to_find\" in the
        resulting render array for $validation_plugin failures, but did not in scenario: $scenario.");
      }
    }

    // Assert that the default value of genus field is the genus
    // entered/selected, indicating that on form validate error, the form was
    // not submitted and reloaded with the genus value as default.
    $this->assertEquals(
      $form_state->getValue('genus'),
      $input_values['genus'],
      'The import form should set the default value of genus to the genus entered if the form was not submitted due to validation error in scenario: $scenario.'
    );

    // If the form was not submitted due to validation error, check to ensure
    // that no Tripal Job was created in the process.
    $tripal_jobs = $this->chado_connection->query(
      'SELECT job_id FROM {tripal_jobs} ORDER BY job_id DESC LIMIT 1'
    )
      ->fetchField();

    $this->assertFalse(
      $tripal_jobs,
      'A failed import due to validation error that did not submit should not create a job request in scenario: $scenario.'
    );
  }

}
