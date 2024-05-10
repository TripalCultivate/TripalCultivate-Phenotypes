<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\Core\Url;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Tests the form + form-related functionality of the Trait Importer.
 * 
 * @group traitImporter
 */
class TraitImporterFormTest extends ChadoTestKernelBase {

	protected $defaultTheme = 'stark';

	protected static $modules = ['system', 'user', 'file', 'tripal', 'tripal_chado', 'trpcultivate_phenotypes'];

  use UserCreationTrait;
  use PhenotypeImporterTestTrait;

  protected $importer;

  /**
   * Chado connection
   */
  protected $connection;

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
		$this->connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

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
   * Tests building the importer form when all should be well.
   */
  public function testTraitImporterFormValid() {

	  $plugin_id = 'trpcultivate-phenotypes-traits-importer';
    $importer_label = 'Tripal Cultivate: Phenotypic Trait Importer';

    // Configure the module.
    $organism_id = $this->connection->insert('1:organism')
      ->fields([
        'genus' => 'Tripalus',
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");
    $this->cvdbon = $this->setOntologyConfig('Tripalus');
    $this->terms = $this->setTermConfig();

    // Build the form using the Drupal form builder.
    $form = \Drupal::formBuilder()->getForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $plugin_id
    );
    // Ensure we are able to build the form.
    $this->assertIsArray($form,
      'We expect the form builder to return a form but it did not.');
    $this->assertEquals('tripal_admin_form_tripalimporter', $form['#form_id'],
      'We did not get the form id we expected.');

    // We expect there to be a Drupal message indicating that the module is not configured.
    // There is also always a message about not importing phenotypic measurements here.
    $warnings = \Drupal::messenger()->messagesByType('warning');
    $this->assertCount(1, $warnings,
      "We expect a single warning since the module is configured.");

    // We also expect the full form to be rendered, so check that now.
    // Now that we have provided a plugin_id, we expect it to have...
    // title matching our importer label.
    $this->assertArrayHasKey('#title', $form,
      "The form should have a title set.");
    $this->assertEquals($importer_label, $form['#title'],
      "The title should match the label annotated for our plugin.");
    // the plugin_id stored in a value form element.
    $this->assertArrayHasKey('importer_plugin_id', $form,
      "The form should have an element to save the plugin_id.");
    $this->assertEquals($plugin_id, $form['importer_plugin_id']['#value'],
      "The importer_plugin_id[#value] should be set to our plugin_id.");

    // Check the file fieldset contents
    $this->assertArrayHasKey('file', $form,
      "We expect there to be a file fieldset but there is not.");
    $this->assertEquals('fieldset', $form['file']['#type'],
      "We expect the file element in the form to be a fieldset.");
    // We expect there to be an upload description including a template link
    // and numbered column description.
    $this->assertArrayHasKey('upload_description', $form['file'],
      "We expect the upload description to have been added to the form by the TripalImporter base class.");
    $this->assertStringContainsString('<a href', $form['file']['upload_description']['#markup'],
      "We expected the upload description to have a link in it.");
    $this->assertStringContainsString('<ol id="tcp-header-notes">', $form['file']['upload_description']['#markup'],
      "We expected the upload description to have an ordered list in it.");
    // We also expect the file upload HTML5 element provided by Tripal
    // and not the file local/remote.
    $this->assertArrayHasKey('file_upload', $form['file'],
      "We expect the file upload element to be added by the Tripal Importer base class.");
    $this->assertArrayNotHasKey('file_local', $form['file'],
      "The local file element should not be available.");
    $this->assertArrayNotHasKey('file_remote', $form['file'],
      "The remote file element should not be available.");

    // Check the Genus form element.
    $this->assertArrayHasKey('genus', $form,
      "We expect there to be a genus form element but there is not.");
    $this->assertEquals('select', $form['genus']['#type'],
      "We expect the genus element in the form to be a select list.");
  }

  /**
   * Tests submitting the importer form when all should be well.
   */
  public function testTraitImporterFormSubmitValid() {

	  $plugin_id = 'trpcultivate-phenotypes-traits-importer';

    // Configure the module.
    $organism_id = $this->connection->insert('1:organism')
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
      'filename' => 'simple_example.txt',
      'content' => ['file' => 'TraitImporterFiles/simple_example.txt'],
    ]);

    // Setup the form_state.
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$plugin_id]);
    $form_state->setValue('genus', 'Tripalus');
    $form_state->setValue('file_upload', $file->id());

    // Now try validation!
    \Drupal::formBuilder()->submitForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $form_state
    );

    // Check that we got an error about the genus not being valid.
    $this->assertTrue($form_state->isValidationComplete(),
      "We expect the form state to have been updated to indicate that validation is complete.");
    //   Looking for form validation errors
    $form_validation_messages = $form_state->getErrors();
    $helpful_output = [];
    foreach ($form_validation_messages as $element => $markup) {
      $helpful_output[] = $element . " => " . (string) $markup;
    }
    $this->assertCount(0, $form_validation_messages,
      "We should not have any errors but instead we have: " . implode(" AND ", $helpful_output));

  }

  /**
   * Tests building the importer form when the module is not configured.
   */
  public function testTraitImporterFormNoOrganism() {

	  $plugin_id = 'trpcultivate-phenotypes-traits-importer';
    $importer_label = 'Tripal Cultivate: Phenotypic Trait Importer';

    // Build the form using the Drupal form builder.
    $form = \Drupal::formBuilder()->getForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $plugin_id
    );
    // Ensure we are able to build the form.
    $this->assertIsArray($form,
      'We expect the form builder to return a form but it did not.');
    $this->assertEquals('tripal_admin_form_tripalimporter', $form['#form_id'],
      'We did not get the form id we expected.');

    // We expect there to be a Drupal message indicating that the module is not configured.
    // There is also always a message about not importing phenotypic measurements here.
    $warnings = \Drupal::messenger()->messagesByType('warning');
    $this->assertCount(2, $warnings,
      "We expect two warnings since the module is not configured.");
    $this->assertStringContainsString('NOT configured', $warnings[1],
      "We expected the second message logged to indicate that the module is not yet configured.");

    // We also expect the full form to be rendered, so check that now.
    // Now that we have provided a plugin_id, we expect it to have...
    // title matching our importer label.
    $this->assertArrayHasKey('#title', $form,
      "The form should have a title set.");
    $this->assertEquals($importer_label, $form['#title'],
      "The title should match the label annotated for our plugin.");
    // the plugin_id stored in a value form element.
    $this->assertArrayHasKey('importer_plugin_id', $form,
      "The form should have an element to save the plugin_id.");
    $this->assertEquals($plugin_id, $form['importer_plugin_id']['#value'],
      "The importer_plugin_id[#value] should be set to our plugin_id.");

    // Check the file fieldset contents
    $this->assertArrayHasKey('file', $form,
      "We expect there to be a file fieldset but there is not.");
    $this->assertEquals('fieldset', $form['file']['#type'],
      "We expect the file element in the form to be a fieldset.");
    // We expect there to be an upload description including a template link
    // and numbered column description.
    $this->assertArrayHasKey('upload_description', $form['file'],
      "We expect the upload description to have been added to the form by the TripalImporter base class.");
    $this->assertStringContainsString('<a href', $form['file']['upload_description']['#markup'],
      "We expected the upload description to have a link in it.");
    $this->assertStringContainsString('<ol id="tcp-header-notes">', $form['file']['upload_description']['#markup'],
      "We expected the upload description to have an ordered list in it.");
    // We also expect the file upload HTML5 element provided by Tripal
    // and not the file local/remote.
    $this->assertArrayHasKey('file_upload', $form['file'],
      "We expect the file upload element to be added by the Tripal Importer base class.");
    $this->assertArrayNotHasKey('file_local', $form['file'],
      "The local file element should not be available.");
    $this->assertArrayNotHasKey('file_remote', $form['file'],
      "The remote file element should not be available.");

    // Check the Genus form element.
    $this->assertArrayHasKey('genus', $form,
      "We expect there to be a genus form element but there is not.");
    $this->assertEquals('select', $form['genus']['#type'],
      "We expect the genus element in the form to be a select list.");
  }

  /**
   * Tests submitting the importer form when the module is not configured.
   */
  public function testTraitImporterFormSubmitNoOrganism() {

	  $plugin_id = 'trpcultivate-phenotypes-traits-importer';

    // Create a file to upload.
    $file = $this->createTestFile([
      'filename' => 'simple_example.txt',
      'content' => ['file' => 'TraitImporterFiles/simple_example.txt'],
    ]);

    // INVALID ORGANISM.
    // Setup the form_state.
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$plugin_id]);
    $form_state->setValue('genus', 'NONexistingOrganism');
    $form_state->setValue('file_upload', $file->id());

    // Now try validation!
    \Drupal::formBuilder()->submitForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $form_state
    );

    // Check that we got an error about the genus not being valid.
    $this->assertTrue($form_state->isValidationComplete(),
      "We expect the form state to have been updated to indicate that validation is complete.");
    //   Looking for form validation errors
    $form_validation_messages = $form_state->getErrors();
    $helpful_output = [];
    foreach ($form_validation_messages as $element => $markup) {
      $helpful_output[] = $element . " => " . (string) $markup;
    }
    $this->assertCount(1, $form_validation_messages,
      "We should have exactly one validation error but instead we have: " . implode(" AND ", $helpful_output));
    $this->assertArrayHasKey('genus', $form_validation_messages,
      "We expected the genus form element specifically to have a validation error but instead we have: " . implode(" AND ", $helpful_output));

    // NO ORGANISM.
    // Setup the form_state.
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$plugin_id]);
    $form_state->setValue('genus', 0);
    $form_state->setValue('file_upload', $file->id());

    // Now try validation!
    \Drupal::formBuilder()->submitForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $form_state
    );

    // Check that we got an error about the genus not being valid.
    $this->assertTrue($form_state->isValidationComplete(),
      "We expect the form state to have been updated to indicate that validation is complete.");
    //   Looking for form validation errors
    $form_validation_messages = $form_state->getErrors();
    $helpful_output = [];
    foreach ($form_validation_messages as $element => $markup) {
      $helpful_output[] = $element . " => " . (string) $markup;
    }
    $this->assertCount(1, $form_validation_messages,
      "We should have exactly one validation error but instead we have: " . implode(" AND ", $helpful_output));
    $this->assertArrayHasKey('genus', $form_validation_messages,
      "We expected the genus form element specifically to have a validation error but instead we have: " . implode(" AND ", $helpful_output));
  }
}
