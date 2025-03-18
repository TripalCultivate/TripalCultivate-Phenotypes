<?php

namespace Drupal\Tests\trpcultivate_phenoshare\Kernel\TripalImporter;

use Drupal\Core\Form\FormState;
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
  use PhenotypeImporterTestTrait;

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

    $genus = 'TEST GENUS';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'some species',
      ])
      ->execute();

    $this->setOntologyConfig($genus);

    $project = 'TEST PROJECT';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => 'some description',
      ])
      ->execute();

    $container->get('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject($project_id, $genus);
  }

  /**
   * Data Provider: provides input values and expected validation result.
   */
  public function provideFormInputValues() {

    $test_project = 'TEST PROJECT';
    $test_genus   = 'TEST GENUS';

    return [
      // #0: Project does not exists.
      [
        'project does not exist',
        [
          'project' => 'A Spurious Project',
          'genus' => $test_genus,
          'filename' => 'simple_example.txt',
        ],
        [
          'validation_result' => [
            'project_exists' => [],
            'genus_exists' => [],
            'project_genus_match' => [],
            'valid_data_file' => [],
          ],
          'failed_count' => 1,
        ],
      ],
    ];
  }

  /**
   * Test the validation aspect of Phenotypes Share Importer form.
   *
   * @param string $scenario
   * @param array $input_values
   * @param array $expected
   *
   * @dataProvider provideFormInputValues
   */
  public function testShareImporterFormValidate(string $scenario, array $input_values, array $expected) {

    // Setup form_state.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$this->definitions['test-share-importer']['id']]);

    $form_state->setValue('project', $input_values['project']);
    $form_state->setValue('genus', $input_values['genus']);

    $test_file = $this->createTestFile([
      'filename' => $input_values['filename'],
      'content' => ['file' => 'TraitImporterFiles/' . $input_values['filename']],
    ]);

    $form_state->setValue('file_upload', $test_file->id());

    // Submit form for validation.
    $form_id = 'Drupal\tripal\Form\TripalImporterForm';
    $this->form_builder->submitForm($form_id, $form_state);
    $form = $this->form_builder->retrieveForm($form_id, $form_state);

    print_r($form);
  }

}
