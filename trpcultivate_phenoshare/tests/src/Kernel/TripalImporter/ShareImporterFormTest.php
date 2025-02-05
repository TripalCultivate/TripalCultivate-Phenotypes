<?php

namespace Drupal\Tests\trpcultivate_phenoshare\Kernel\TripalImporter;

use Drupal\Core\Form\FormState;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\user\Entity\User;

/**
 * Tests the form + form-related functionality of the Share Importer.
 *
 * @group traitsImporter
 */
class ShareImporterFormTest extends ChadoTestKernelBase {

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
   * Saves details regarding the config.
   *
   * @var array
   */
  protected array $cvdbon;

  /**
   * The terms required by this module mapped to the cvterm_ids they are set to.
   *
   * @var array
   */
  protected array $terms;

  /**
   * The Drupal Renderer.
   *
   * @var Drupal\Core\Render\Renderer
   */
  protected $service_Renderer;

  /**
   * Phenotypes Share Importer plugin instance.
   *
   * @var Drupal\trpcultivate_phenoshare\src\Plugin\TripalImporter\TripalCultivatePhenoshareImporter
   */
  protected $phenoshare_importer;

  /**
   * Phenotypes Share Importer plugin manager.
   *
   * @var Drupal\trpcultivate_phenotype\src\TripalCultivateValidator\TripalCultivatePhenotypesValidatorManager
   */
  protected $phenoshare_plugin_manager;

  /**
   * A default listing of annotations associated with our importer.
   *
   * @var array
   */
  protected array $definitions = [
    'test-trait-importer' => [
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
    $this->installConfig(['system', 'trpcultivate_phenotypes', 'trpcultivate_phenoshare']);
    // Prepare Tripal Importer Environment.
    $this->prepareEnvironment(['TripalImporter']);
    // Create and log-in a user.
    $this->setUpCurrentUser();

    // We need to mock the logger to test the progress reporting.
    $container = \Drupal::getContainer();
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['error'])
      ->getMock();
    $mock_logger->method('error')
      ->willReturnCallback(function ($message, $context, $options) {
        // @todo Revisit print out of log messages, but perhaps setting an option
        // for log messages to not print to the UI?
        // print str_replace(array_keys($context), $context, $message);
        return NULL;
      });
    $container->set('tripal.logger', $mock_logger);
    $this->service_Renderer = $container->get('renderer');

    // Phenoshare Importer instance.
    $this->phenoshare_plugin_manager = \Drupal::service('tripal.importer');
    $plugin_id = $this->definitions['test-trait-importer']['id'];
    $this->phenoshare_importer = $this->phenoshare_plugin_manager->createInstance($plugin_id);
  }

  /**
   * Tests form.
   */
  public function testForm() {

    // Build Stage 1 form.
    $form = \Drupal::formBuilder()->getForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $this->definitions['test-trait-importer']['id']
    );

    $stage_1_form = $this->service_Renderer->renderInIsolation($form);

    // Test that on page load the default active stage is STAGE 1.
    preg_match('/<div class=".*\stcp-current-stage">(.*?)<\/div>/', $stage_1_form, $matches);
    $this->assertStringContainsString(
      'STAGE 1',
      $matches[1],
      'The default active stage on page load is not labelled Stage 1'
    );
  }

  /**
   * Test Stage 1.
   */
  public function testStage1() {
    // Fire up Tripal Share Importer Plugin.
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->getForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $this->definitions['test-trait-importer']['id']
    );

    // Build Stage 1.
    $this->phenoshare_importer->stage1($form, $form_state, '');
    $stage_1 = $this->service_Renderer->renderInIsolation($form);

    // Test that stage 1 specific field elements were rendered.
    print_r($stage_1);
  }

  /**
   * Test describeUploadFileFormat() method in the importer.
   */
  public function testDescribeUploadFileFormat() {
    // Create a user.
    $user_username = 'user-collector';
    $user = User::create([
      'name' => $user_username,
      'roles' => ['authenticated user'],
    ]);
    $user->save();

    \Drupal::currentUser()->setAccount($user);

    // Create a file format description section.
    $rendered_file_format_description = $this->phenoshare_importer->describeUploadFileFormat();

    // Assert headers matched the headers defined by Trait Importer.
    $expected_headers = [
      'Trait Name',
      'Method Name',
      'Unit',
      'Germplasm Accession',
      'Germplasm Name',
      'Year',
      'Location',
      'Replicate',
      'Value',
      'Data Collector',
    ];

    // Pull all the headers in the rendered description.
    preg_match_all('/<strong>(.*?)<\/strong>/', $rendered_file_format_description, $matches);
    $this->assertEquals(
      $expected_headers,
      $matches[1],
      'The headers defined by the importer does not match the headers rendered by describeUploadFileFormat()'
    );

    // Assert admin notes were incorporated into the description section.
    $expected_notes = 'The order of the above columns is important and your file must include a header!
    If you have a single trait measured in more than one way (i.e. with multiple collection
    methods), then you should have one line per collection method with the trait name/description repeated.';

    $this->assertStringContainsString(
      $expected_notes,
      $rendered_file_format_description,
      'The rendered markup of the method describeUploadFileFormat() does not contain expected file format importer notes'
    );

    // Assert a download link was provided.
    // Construct the templage file filename.
    // Only the first item in the 'file_types' importer annotation is used as
    // default file extension of the template file.
    $plugin_id = $this->definitions['test-trait-importer']['id'];
    $importer_annotations = $this->phenoshare_plugin_manager->getDefinitions();
    $expected_file_extension = $importer_annotations[$plugin_id]['file_types'][0];
    $expected_template_filename = $plugin_id . '-data-collection-template-file-' . $user_username . '.' . $expected_file_extension;

    $this->assertStringContainsString(
      $expected_template_filename,
      $rendered_file_format_description,
      'The rendered markup of the method describeUploadFileFormat() does not contain the expected file template filename.'
    );
  }

}
