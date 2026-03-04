<?php

namespace Drupal\Tests\trpcultivate_phenoshare\Kernel\TripalImporter;

use Drupal\Core\Form\FormState;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the formValidate() functionality of the Share Importer.
 *
 * @group shareImporter
 */
#[Group('shareImporter')]
#[RunTestsInSeparateProcesses]
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
   * Location of file fixtures relative to a module.
   *
   * Keys:
   *   - 'share': the test fixtures directory located in PhenoShare module.
   *   - 'trait': the test fixtures directory located in Phenotypes module.
   *   - 'base' : the test fixtures directory located in TripalCultivate module.
   *
   * @var array
   */
  private array $fixture_source;

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

    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => self::TEST_GENUS,
        'species' => 'some species',
      ])
      ->execute();

    // Insert a test germplasm.
    // Stock-1 appears as value of Gerplasm Name column-row combination in
    // valid_header_valid_row.tsv file fixture.
    $this->chado_connection->insert('1:stock')
      ->fields([
        'organism_id' => $organism_id,
        'name' => 'Stock-1',
        'dbxref_id' => 1,
        'uniquename' => 'STOCK:1',
        'description' => 'A test germplasm used by valid_header_valid_row.tsv test file fixture',
        'type_id' => 1,
        'is_obsolete' => 'f',
      ])
      ->execute();

    // This genus is not paired with a project.
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => 'NOT:' . self::TEST_GENUS,
        'species' => 'some species',
      ])
      ->execute();

    $this->setOntologyConfig(self::TEST_GENUS);
    $this->setOntologyConfig('NOT:' . self::TEST_GENUS);

    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => self::TEST_PROJECT,
        'description' => 'some description',
      ])
      ->execute();

    // This project is not paired with a genus.
    $this->chado_connection->insert('1:project')
      ->fields([
        'name' => 'NOT:' . self::TEST_PROJECT,
        'description' => 'some description',
      ])
      ->execute();

    // Create test project-genus pair.
    $container->get('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject($project_id, self::TEST_GENUS);

    $sources = [
      'trait' => [
        'module' => 'trpcultivate_phenotypes',
        'dir' => 'TraitImporterFiles',
      ],
      'share' => [
        'module' => 'trpcultivate_phenoshare',
        'dir' => 'ShareImporterFiles',
      ],
    ];

    foreach ($sources as $source => $prop) {
      $fixture_path = $this->container->get('module_handler')
        ->getModule($prop['module'])
        ->getPath();

      $this->fixture_source[$source] = $fixture_path . '/tests/src/Fixtures/' . $prop['dir'] . '/';
    }
  }

  /**
   * Data Provider: provides input values and expected validation result.
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - A short description of the test scenario.
   *   - An array of importer share input values with the following keys.
   *     - 'project': the project name.
   *     - 'genus': the genus name that gets selected in the genus dropdown.
   *     - 'file': the data file with the following properties:
   *       - 'filename': the filename of the data collection file.
   *       - 'source': indicates the location of the test file fixture relative
   *         to a module. A source of 'trait' points to phenotypes module
   *         fixture whereas a source of 'share' refers to the fixtures in
   *         phenotypes share module. Fixtures in base can be reference
   *         by an empty string.
   *   - An array indicating the expected validation results:
   *     - Each key is the unique name of a feedback line provided to the UI
   *       through processValidationMessages(). Currently, there is a feedback
   *       line for each unique validator instance that was instantiated by the
   *       configureValidators() method in the Share Importer.
   *       - 'status': [REQUIRED] One of 'pass', 'todo', or 'fail'
   *       - 'title': [REQUIRED if 'status' = 'fail'] A string that matches the
   *         title set in processValidationMessages() method in the Share
   *         Importer for this validator instance.
   *       - 'details': [REQUIRED if 'status' = 'fail'] A string that is ideally
   *         unique to the scenario that is expected to be in the render array.
   *   - an integer indicating the number of form validation messages we expect
   *     to see when the form is submitted.
   *     NOTE: These validation messages are produced by the form via Drupal and
   *     are not related to this module's use of validator plugins.
   */
  public static function provideFormInputValues() {
    return [
      // #0: Project not provided.
      [
        'no project',
        [
          'project' => '',
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_data_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => [
            'title' => 'Research Experiment exists and has been configured with selected genus',
            'status' => 'fail',
            'details' => 'The selected Research Experiment does not exist. Please contact your administrator to have this added.',
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        1,
        FALSE,
      ],

      // #1: Not the genus the project was paired to.
      [
        'not the expected genus',
        [
          'project' => self::TEST_PROJECT,
          'genus' => 'NOT:' . self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_data_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => [
            'title' => 'Research Experiment exists and has been configured with selected genus',
            'status' => 'fail',
            'details' => 'The selected genus has not been paired to the selected Research Experiment. Please select a paired genus or contact your administrator if you think one is missing.',
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #2: Project does not exists.
      [
        'project does not exist',
        [
          'project' => 'A spurious project',
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_data_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => [
            'title' => 'Research Experiment exists and has been configured with selected genus',
            'status' => 'fail',
            'details' => 'The selected Research Experiment does not exist. Please contact your administrator to have this added.',
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #3: Project exists but is not paired to a genus.
      [
        'project has no genus',
        [
          'project' => 'NOT:' . self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_data_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => [
            'title' => 'Research Experiment exists and has been configured with selected genus',
            'status' => 'fail',
            'details' => 'The selected Research Experiment does not have a genus paired to it. Please contact your administrator to have this set up.',
          ],
          'valid_data_file' => ['status' => 'todo'],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #4: File is empty
      [
        'file is empty',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_file.tsv',
            'source' => 'trait',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => [
            'title' => 'File is valid and not empty',
            'status' => 'fail',
            'details' => 'The file provided has no contents in it to import. Please ensure your file has the expected header row and at least one row of data.',
          ],
          'valid_delimited_file' => ['status' => 'todo'],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #5: Header is improperly delimited, with proper data rows.
      [
        'header row not properly delimited',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'improperly_delimited_header_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => [
            'title' => 'Lines are properly delimited',
            'status' => 'fail',
            'details' => 'This importer requires a minimum number of 7 columns for each line. The following lines do not contain the expected number of columns.',
          ],
          'valid_header' => ['status' => 'todo'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #6: Data row of file is improperly delimited.
      [
        'data row not properly delimited',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'improperly_delimited_data_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => [
            'title' => 'Lines are properly delimited',
            'status' => 'fail',
            'details' => 'This importer requires a minimum number of 7 columns for each line. The following lines do not contain the expected number of columns.',
          ],
          'valid_header' => ['status' => 'pass'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #7: Contains correct header but no data.
      // Never reaches the validators for data-row since file content is empty.
      [
        'no data row',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_data_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => ['status' => 'pass'],
          'valid_header' => ['status' => 'pass'],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #8: Contains incorrect header and one line of correct data.
      [
        'incorrect header',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'incorrect_header_with_data.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => ['status' => 'pass'],
          'valid_header' => [
            'title' => 'File has all of the column headers expected',
            'status' => 'fail',
            'details' => 'One or more of the column headers in the input file does not match what was expected. Please check if your column header is in the correct order and matches the template exactly.',
          ],
          'empty_cell' => ['status' => 'todo'],
          'germplasm_name_exists' => ['status' => 'todo'],
        ],
        0,
        FALSE,
      ],

      // #9: Contains correct header and one line of correct data.
      // 3rd line has an empty 'Replicate'.
      [
        'empty column',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_cell.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => ['status' => 'pass'],
          'valid_header' => ['status' => 'pass'],
          'empty_cell' => [
            'title' => 'Required cells contain a value',
            'status' => 'fail',
            'details' => 'The following line number and column header combinations were empty, but a value is required.',
          ],
          'germplasm_name_exists' => ['status' => 'fail'],
        ],
        0,
        FALSE,
      ],

      // #10: Contains correct header and one line of correct data.
      // 3rd line has empty line (not a validation error).
      // 4th line has an empty 'Replicate'.
      [
        'empty column after empty line',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'empty_cell_after_empty_line.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => ['status' => 'pass'],
          'valid_header' => ['status' => 'pass'],
          'empty_cell' => [
            'title' => 'Required cells contain a value',
            'status' => 'fail',
            'details' => 'The following line number and column header combinations were empty, but a value is required.',
          ],
          'germplasm_name_exists' => ['status' => 'fail'],
        ],
        0,
        FALSE,
      ],

      // #11: 1st line has reference to a non-existent germplasm name.
      [
        'non-existent germplasm',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'reference_to_non_existent_germplasm.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => ['status' => 'pass'],
          'valid_header' => ['status' => 'pass'],
          'empty_cell' => ['status' => 'pass'],
          'germplasm_name_exists' => [
            'title' => 'Germplasm exist(s) in the database',
            'status' => 'fail',
            'details' => 'The following germplasm names do not match any existing in this site. Please make sure you have entered the names exactly as they appear on the germplasm pages or contact your administrator to have them added if they do not yet exist.',
          ],
        ],
        0,
        FALSE,
      ],

      // #12: No validation error.
      [
        'all pass',
        [
          'project' => self::TEST_PROJECT,
          'genus' => self::TEST_GENUS,
          'file' => [
            'filename' => 'valid_header_valid_row.tsv',
            'source' => 'share',
          ],
        ],
        [
          'project_genus_match' => ['status' => 'pass'],
          'valid_data_file' => ['status' => 'pass'],
          'valid_delimited_file' => ['status' => 'pass'],
          'valid_header' => ['status' => 'pass'],
          'empty_cell' => ['status' => 'pass'],
          'germplasm_name_exists' => ['status' => 'pass'],
        ],
        0,
        TRUE,
      ],
    ];
  }

  /**
   * Test Stage 1 validation aspect of Phenotypes Share Importer form.
   *
   * @param string $scenario
   *   A short description of the test scenario.
   * @param array $input_values
   *   An array of importer share input values with the following keys.
   *     - 'project': the project name.
   *     - 'genus': the genus name that gets selected in the form dropdown.
   *     - 'file': the file with the following properties:
   *       - 'filename': the filename of the data collection file.
   *       - 'source': idicates the location of the test file fixutre relative
   *         to a module. A source of 'trait' points to phenotypes module
   *         fixutre whereas a source of 'share' refers to the fixtures in
   *         phenotypes share module. Any fixutes in base can be reference
   *         by and empty string.
   * @param array $expected_validator_results
   *   An array that is keyed by the unique name of each validator instance
   *   (these names are declared in the configureValidators() method in the
   *   Share Importer). Each validator instance in the array is further
   *   keyed by the following. Some are required but others are optional,
   *   dependent upon the expected validation results.
   *   - 'status': [REQUIRED] One of 'pass', 'todo', or 'fail'.
   *   - 'title': [REQUIRED if 'status' = 'fail'] A string that matches the
   *     title set in processValidationMessages() method in the Trait Importer
   *     class for this validator instance.
   *   - 'details': [REQUIRED if 'status' = 'fail'] A string that is ideally
   *     unique to the scenario that is expected to be in the render array.
   * @param int $expected_num_form_validation_errors
   *   The number of form validation messages we expect to see when the form is
   *   submitted. NOTE: These validation messages are produced by the form via
   *   Drupal and are not related to this module's use of validator plugins.
   * @param bool $job_created
   *   Indicates whether we expect a job to be created when the form is
   *   submitted. Specifically, if TRUE then submission should have been
   *   successful and a job should have been submitted; if FALSE then it
   *   should have been blocked in validation and no job should exist.
   *
   * @dataProvider provideFormInputValues
   */
  #[DataProvider('provideFormInputValues')]
  public function testShareImporterFormValidateStage1(
    string $scenario,
    array $input_values,
    array $expected_validator_results,
    int $expected_num_form_validation_errors,
    bool $job_created,
  ) {

    // Setup form_state.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$this->definitions['test-share-importer']['id']]);

    $form_state->setValue('current_stage', 1);
    $form_state->setValue('project', $input_values['project']);
    $form_state->setValue('genus', $input_values['genus']);

    $test_file = $this->createTestFile([
      'filename' => $input_values['file']['filename'],
      'content' => [
        'file' => $input_values['file']['filename'],
        'fixturepath' => $this->fixture_source[$input_values['file']['source']],
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

      // Test validation result title matches expected failed validation result
      // title text.
      if (isset($expected['title'])) {
        $this->assertEquals(
          $validation_element_data[$validation_plugin]['title'],
          $expected['title'],
          'Failed validation title does not match the expected failed validation title'
        );
      }

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

    $this->assertEquals(
      $job_created,
      !empty($tripal_jobs),
      'A failed import due to validation error that did not submit should not create a job request in scenario: $scenario.'
    );
  }

  /**
   * Test re-upload after previous failed upload attempt.
   */
  public function testFormReupload() {

    $form_id = 'Drupal\tripal\Form\TripalImporterForm';
    $share_importer = \Drupal::service('tripal.importer')
      ->createInstance($this->definitions['test-share-importer']['id']);

    // Setup form_state.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$this->definitions['test-share-importer']['id']]);
    $form_state->setValue('current_stage', 1);
    $form_state->setValue('project', self::TEST_PROJECT);
    $form_state->setValue('genus', self::TEST_GENUS);

    $invalid_test_file = 'incorrect_header_with_data.tsv';
    $test_file = $this->createTestFile([
      'filename' => $invalid_test_file,
      'content' => [
        'file' => $invalid_test_file,
        'fixturepath' => $this->fixture_source['share'],
      ],
    ]);

    $form_state->setValue('file_upload', $test_file->id());

    // Submit form for validation.
    $this->form_builder->submitForm($form_id, $form_state);
    $form = $this->form_builder->retrieveForm($form_id, $form_state);

    // Validation result window is in accordion stage 1.
    // Confirm that there is a validation window open.
    $this->assertArrayHasKey('validation_result', $form['accordion_stage1'],
      "We expected a validation failure reported via our plugin setup but it's not showing up in the form in test re-upload form.");

    $storage = $form_state->getStorage();
    $has_failed = $share_importer->hasFailedValidation($storage['validation_result']);

    $this->assertTrue(
      $has_failed,
      'The importer form is expected to fail with incorrect headers in the data file'
    );

    // With the form still has the validation result. Re-upload a file that is
    // expected to pass keeping other input values for genus and project.
    $valid_test_file = 'valid_header_valid_row.tsv';
    $test_file = $this->createTestFile([
      'filename' => $valid_test_file,
      'content' => [
        'file' => $valid_test_file,
        'fixturepath' => $this->fixture_source['share'],
      ],
    ]);

    $form_state->setValue('file_upload', $test_file->id());
    $this->form_builder->submitForm($form_id, $form_state);
    $form = $this->form_builder->retrieveForm($form_id, $form_state);

    $storage = $form_state->getStorage();
    $has_failed = $share_importer->hasFailedValidation($storage['validation_result']);

    $this->assertFalse(
      $has_failed,
      'The importer form is expected to pass with valid header and data row in the data file'
    );
  }

}
