<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\Core\Url;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Tests the form + form-related functionality of the Trait Importer.
 *
 * @group traitImporter
 */
class TraitImporterFormValidateTest extends ChadoTestKernelBase {

	protected $defaultTheme = 'stark';

	protected static $modules = ['system', 'user', 'file', 'tripal', 'tripal_chado', 'trpcultivate_phenotypes'];

  use UserCreationTrait;
  use PhenotypeImporterTestTrait;

  protected $importer;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Saves details regarding the config.
   */
  protected array $cvdbon;

  /**
   * The terms required by this module mapped to the cvterm_ids they are set to.
   */
  protected array $terms;

  protected $definitions = [
    'test-trait-importer' => [
      'id' => 'trpcultivate-phenotypes-traits-importer',
      'label' => 'Tripal Cultivate: Phenotypic Trait Importer',
      'description' => 'Loads Traits for phenotypic data into the system. This is useful for large phenotypic datasets to ease the upload process.',
      'file_types' => ["tsv", "txt"],
      'use_analysis' => FALSE,
      'require_analysis' => FALSE,
      'upload_title' => 'Phenotypic Trait Data File*',
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

		// Open connection to Chado
		$this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Ensure we can access file_managed related functionality from Drupal.
    // ... users need access to system.action config?
    $this->installConfig(['system', 'trpcultivate_phenotypes']);
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
    $mock_logger = $this->getMockBuilder(\Drupal\tripal\Services\TripalLogger::class)
      ->onlyMethods(['notice', 'error'])
      ->getMock();
    $mock_logger->method('error')
    ->willReturnCallback(function ($message, $context, $options) {
      // @todo: Revisit print out of log messages, but perhaps setting an option
      // for log messages to not print to the UI?
      //print str_replace(array_keys($context), $context, $message);
      return NULL;
    });
    $container->set('tripal.logger', $mock_logger);
  }

  /**
   * Data Provider: provides files with expected validation result.
   *
   * For each scenario we expect the following:
   * -- the genus name that gets selected in the dropdown of the form
   * -- the filename of the test file used for this scenario (test files are
   *    located in: tests/src/Fixtures/TraitImporterFiles/)
   * -- an array indicating the expected validation results
   *    - Each key is the unique name of a feedback line provided to the validator UI
   *      by the processValidationMessages(). Currently there is a feedback line for
   *      each unique validator instance that was instantiated by the
   *      configureValidators() method in the Traits Importer class
   *      - 'status': [REQUIRED] One of 'pass', 'todo', or 'fail'
   *      - 'title': [REQUIRED if 'status' = 'fail'] A string that matches the
   *        title set in processValidationMessages() method in the Trait Importer
   *        class for this validator instance.
   *      - 'details': [REQUIRED if 'status' = 'fail'] A string that is passed
   *        to the user through the UI if this validation instance failed.
   * -- an integer of the number of form validation messages we expect to see
   *    when the form is submitted. NOTE: These validation messages are produced
   *    by the form via Drupal and are not related to this module's use of
   *    validator plugins.
   */
  public function provideFilesForValidation() {

    $valid_genus = 'Tripalus';
    $invalid_genus = 'INVALID';
    // Set our number of expected validation messages to 0, since only the
    // 'genus_exists' validator should cause this number to change.
    $num_form_validation_messages = 0;

    $scenarios = [];

    // #0: File is valid but genus is not
    $scenarios[] = [
      $invalid_genus,
      'simple_example.txt',
      [
        'genus_exists' => [
          'title' => 'The genus is valid',
          'status' => 'fail',
          'details' => 'Genus does not exist'
        ],
        'valid_data_file' => ['status' => 'todo'],
        'valid_delimited_file' => ['status' => 'todo'],
        'valid_header' => ['status' => 'todo'],
        'empty_cell' => ['status' => 'todo'],
        'valid_data_type' => ['status' => 'todo'],
        'duplicate_traits' => ['status' => 'todo']
      ],
      1 // Since selecting an invalid genus should be impossible, 1 form validation error is expected
    ];

    // #1: File is empty
    $scenarios[] = [
      $valid_genus,
      'empty_file.txt',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => [
          'title' => 'File is valid and not empty',
          'status' => 'fail',
          'details' => 'The file has no data and is an empty file'
        ],
        'valid_delimited_file' => ['status' => 'todo'],
        'valid_header' => ['status' => 'todo'],
        'empty_cell' => ['status' => 'todo'],
        'valid_data_type' => ['status' => 'todo'],
        'duplicate_traits' => ['status' => 'todo']
      ],
      $num_form_validation_messages
    ];

    // #2: 2nd row of file is improperly delimited
    $scenarios[] = [
      $valid_genus,
      'correct_header_improperly_delimited_data_row.tsv',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => ['status' => 'pass'],
        'valid_delimited_file' => [
          'title' => 'Row is properly delimited',
          'status' => 'fail',
          'details' => 'Raw row is not delimited'
        ],
        // Since the header row has the correct number of columns, validation for
        // valid_header is expected to pass
        'valid_header' => ['status' => 'pass'],
        'empty_cell' => ['status' => 'todo'],
        'valid_data_type' => ['status' => 'todo'],
        'duplicate_traits' => ['status' => 'todo']
      ],
      $num_form_validation_messages
    ];

    // #3: Contains correct header but no data
    // Never reaches the validators for data-row since file content is empty
    $scenarios[] = [
      $valid_genus,
      'correct_header_no_data.tsv',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => ['status' => 'pass'],
        'valid_delimited_file' => ['status' => 'pass'],
        'valid_header' => ['status' => 'pass'],
        'empty_cell' => ['status' => 'todo'],
        'valid_data_type' => ['status' => 'todo'],
        'duplicate_traits' => ['status' => 'todo']
      ],
      $num_form_validation_messages
    ];

    // #4: Contains incorrect header and one line of correct data
    $scenarios[] = [
      $valid_genus,
      'incorrect_header_with_data.tsv',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => ['status' => 'pass'],
        'valid_delimited_file' => ['status' => 'pass'],
        'valid_header' => [
          'title' => 'File has all of the column headers expected',
          'status' => 'fail',
          'details' => 'Headers do not match expected headers',
        ],
        'empty_cell' => ['status' => 'todo'],
        'valid_data_type' => ['status' => 'todo'],
        'duplicate_traits' => ['status' => 'todo']
      ],
      $num_form_validation_messages
    ];

    // #5: Contains correct header and one line of correct data,
    // 2nd line has an empty 'Short Method Name'
    $scenarios[] = [
      $valid_genus,
      'correct_header_emptycell_method.tsv',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => ['status' => 'pass'],
        'valid_delimited_file' => ['status' => 'pass'],
        'valid_header' => ['status' => 'pass'],
        'empty_cell' => [
          'title' => 'Required cells contain a value',
          'status' => 'fail',
          'details' => 'Empty value found in required column(s) at row #: 3'
        ],
        'valid_data_type' => ['status' => 'pass'],
        'duplicate_traits' => ['status' => 'pass']
      ],
      $num_form_validation_messages
    ];

    // #6: Contains correct header and two lines of data
    // First line has an invalid value for 'Type' column
    $scenarios[] = [
      $valid_genus,
      'correct_header_invalid_datatype.tsv',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => ['status' => 'pass'],
        'valid_delimited_file' => ['status' => 'pass'],
        'valid_header' => ['status' => 'pass'],
        'empty_cell' => ['status' => 'pass'],
        'valid_data_type' => [
          'title' => 'Values in required cells are valid',
          'status' => 'fail',
          'details' => 'Invalid value(s) in required column(s) at row #: 2'
        ],
        'duplicate_traits' => ['status' => 'pass']
      ],
      $num_form_validation_messages
    ];

    // #7: Contains correct header and a duplicate trait-method-unit combo
     $scenarios[] = [
      $valid_genus,
      'correct_header_duplicate_traitMethodUnit.tsv',
      [
        'genus_exists' => ['status' => 'pass'],
        'valid_data_file' => ['status' => 'pass'],
        'valid_delimited_file' => ['status' => 'pass'],
        'valid_header' => ['status' => 'pass'],
        'empty_cell' => ['status' => 'pass'],
        'valid_data_type' => ['status' => 'pass'],
        'duplicate_traits' => [
          'title' => 'All trait-method-unit combinations are unique',
          'status' => 'fail',
          'details' => 'A duplicate trait was found within the input file at row #: 3'
        ]
      ],
      $num_form_validation_messages
    ];

    return $scenarios;
  }

  /**
   * Tests the validation aspect of the trait importer form.
   *
   * @param string $submitted_genus
   *   The name of the genus that is submitted with the form.
   * @param string $filename
   *   The name of the file being tested. This file is located in:
   *   tests/src/Fixtures/TraitImporterFiles/
   * @param array $expected_validator_results
   *   An array that is keyed by the unique name of each validator instance
   *   (these keys are declared in the configureValidators() method in the Traits
   *   Importer class). Each validator instance in the array is further keyed by
   *   the following. Some are required but others are optional, dependent upon
   *   the expected validation results.
   *   - 'status': [REQUIRED] One of 'pass', 'todo', or 'fail'
   *   - 'title': [REQUIRED if 'status' = 'fail'] A string that matches the
   *     title set in processValidationMessages() method in the Trait Importer
   *     class for this validator instance.
   *   - 'details': [REQUIRED if 'status' = 'fail'] A string that is passed to
   *     the user through the UI if this validation instance failed.
   * @param integer $expected_num_form_validation_errors
   *   The number of form validation messages we expect to see
   *   when the form is submitted. NOTE: These validation messages are produced
   *   by the form via Drupal and are not related to this module's use of
   *   validator plugins.
   *
   * @return void
   *
   * @dataProvider provideFilesForValidation
   */
  public function testTraitFormValidation(string $submitted_genus, string $filename, array $expected_validator_results, int $expected_num_form_validation_errors) {

    $formBuilder = \Drupal::formBuilder();
    $form_id = 'Drupal\tripal\Form\TripalImporterForm';
	  $plugin_id = 'trpcultivate-phenotypes-traits-importer';

    // Configure the module.
    $genus = 'Tripalus';
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");
    $this->cvdbon = $this->setOntologyConfig($genus);
    $this->terms = $this->setTermConfig();

    // Create a file to upload.
    $file = $this->createTestFile([
      'filename' => $filename,
      'content' => ['file' => 'TraitImporterFiles/' . $filename],
    ]);

    // Setup the form_state.
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$plugin_id]);

    // Submit our genus
    $form_state->setValue('genus', $submitted_genus);

    // Submit our file
    $form_state->setValue('file_upload', $file->id());

    // Now try validation!
    $formBuilder->submitForm($form_id, $form_state);
    // And retrieve the form that would be shown after the above submit.
    $form = $formBuilder->retrieveForm($form_id, $form_state);
    // And the form state storage where our importers store their validation.
    $storage = $form_state->getStorage();

    // Check that we did validation.
    $this->assertTrue($form_state->isValidationComplete(),
      "We expect the form state to have been updated to indicate that validation is complete.");

    // Looking for form validation errors
    $form_validation_messages = $form_state->getErrors();
    $helpful_output = [];
    foreach ($form_validation_messages as $element => $markup) {
      $helpful_output[] = $element . " => " . (string) $markup;
    }

    // Compare the number of form validation errors we received to the number we expect
    $this->assertCount(
      $expected_num_form_validation_errors,
      $form_validation_messages,
      "The number of form state errors we expected (" . $expected_num_form_validation_errors . ") does not match what we received: " . implode(" AND ", $helpful_output)
    );
    // Confirm that there is a validation window open
    $this->assertArrayHasKey('validation_result', $form,
      "We expected a validation failure reported via our plugin setup but it's not showing up in the form.");
    $validation_element_data = $form['validation_result']['#data']['validation_result'];

    // Now check our expectations are met.
    foreach ($expected_validator_results as $validation_plugin => $expected) {
      // Check status
      $this->assertEquals(
        $expected['status'],
        $validation_element_data[$validation_plugin]['status'],
        "We expected the form validation element to indicate the $validation_plugin plugin had the specified status."
      );
      // We don't want the value of 'details' in $expectations (from the data
      // provider) to be empty since assertStringContainsString() will evaluate
      // to true in that scenario. It can be tempting to set it to empty and
      // then come back to it when you figure out what the expected string
      // should be- just don't do it!
      if (array_key_exists('details', $expected)) {
        $this->assertNotEmpty(
          $expected['details'],
          "An empty string was provided with a 'details' key within the data provider - trust me, don't do that!"
        );
        // Now check details
        $this->assertStringContainsString(
          $expected['details'],
          $validation_element_data[$validation_plugin]['details'],
          "We expected the details for $validation_plugin to include a specific string but it did not."
        );
      }
    }
  }
}
