<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\Core\Url;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal_chado\Database\ChadoConnection;

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
   * Tripal DBX Chado Connection object
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
  }

  /**
   * Data Provider: provides files with expected validation result.
   */
  public function provideFilesForValidation() {
    $senarios = [];

    // Contains correct header but no data
    $senarios[] = [
      'correct_header_no_data.tsv',
      [
        'GENUS' => ['status' => 'pass'],
        'FILE' => ['status' => 'pass'],
        'HEADERS' => ['status' => 'pass'],
        'FILE ROW' => [
          'status' => 'fail',
          'message' => 'no data rows',
        ]
      ]
    ];

    // Contains incorrect header and one line of correct data
    $senarios[] = [
      'incorrect_header_with_data.tsv',
      [
        'GENUS' => ['status' => 'pass'],
        'FILE' => ['status' => 'pass'],
        'HEADERS' => [
          'status' => 'fail',
          'message' => 'Trait Description is/are missing in the file',
        ],
        'FILE ROW' => ['status' => 'todo'],
      ]
    ];

    // Contains correct header and duplicate trait-method-unit combo
    $senarios[] = [
      'correct_header_duplicate_traitMethodUnit.tsv',
      [
        'GENUS' => ['status' => 'pass'],
        'FILE' => ['status' => 'pass'],
        'HEADERS' => ['status' => 'pass'],
        'FILE ROW' => [
          'status' => 'fail',
          // Can't yet test the message here is it's a complicated structure.
        ]
      ]
    ];

    // Contains correct header but data types are mismatched

    return $senarios;
  }

  /**
   * Tests the validation aspect of the trait importer form.
   *
   * @dataProvider provideFilesForValidation
   */
  public function testTraitFormValidation($filename, $expectations) {

    $this->markTestSkipped('Skipping trait form validation until new plugins have been implemented.');

    $formBuilder = \Drupal::formBuilder();
    $form_id = 'Drupal\tripal\Form\TripalImporterForm';
	  $plugin_id = 'trpcultivate-phenotypes-traits-importer';

    // Configure the module.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => 'Tripalus',
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");
    $this->cvdbon = $this->setOntologyConfig('Tripalus');
    $this->terms = $this->setTermConfig();

    // Create a file to upload.
    $file = $this->createTestFile([
      'filename' => $filename,
      'content' => ['file' => 'TraitImporterFiles/' . $filename],
    ]);

    // Setup the form_state.
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$plugin_id]);
    $form_state->setValue('genus', 'Tripalus');
    $form_state->setValue('file_upload', $file->id());

    // Now try validation!
    $formBuilder->submitForm($form_id, $form_state);
    // And retrieve the form that would be shown after the above submit.
    $form = $formBuilder->retrieveForm($form_id, $form_state);
    // And the for form state storage where our importers store their validation.
    $storage = $form_state->getStorage();

    // Check that we did validation.
    $this->assertTrue($form_state->isValidationComplete(),
      "We expect the form state to have been updated to indicate that validation is complete.");
    //   Looking for form validation errors
    $form_validation_messages = $form_state->getErrors();
    $helpful_output = [];
    foreach ($form_validation_messages as $element => $markup) {
      $helpful_output[] = $element . " => " . (string) $markup;
    }
    $this->assertCount(0, $form_validation_messages,
      "We should not have any form state errors but instead we have: " . implode(" AND ", $helpful_output));

    // Confirm that there is a validation window open
    $this->assertArrayHasKey('validation_result', $form,
      "We expected a validation failure reported via our plugin setup but it's not showing up in the form.");
    $validation_element_data = $form['validation_result']['#data']['validation_result'];
    // Now check our expectations are met.
    foreach ($expectations as $validation_plugin => $expected) {
      $this->assertEquals($expected['status'], $validation_element_data[$validation_plugin]['status'],
        "We expected the form validation element to indicate the $validation_plugin plugin had the specified status.");
      if (array_key_exists('message', $expected)) {
        $this->assertStringContainsString($expected['message'], $validation_element_data[$validation_plugin]['details'],
          "We expected the details for $validation_plugin to include a specific string but it did not.");
      }
    }
  }
}
