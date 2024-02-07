<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\Core\Url;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Tests the form + form-related functionality of the Trait Importer.
 */
class TraitImporterFormTest extends ChadoTestKernelBase {

	protected $defaultTheme = 'stark';

	protected static $modules = ['system', 'user', 'file', 'tripal', 'tripal_chado', 'trpcultivate_phenotypes'];

  use UserCreationTrait;
  use PhenotypeImporterTestTrait;

  protected $importer;

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
   * Tests focusing on the run() function using a simple example file that
   * populates all columns.
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
}
