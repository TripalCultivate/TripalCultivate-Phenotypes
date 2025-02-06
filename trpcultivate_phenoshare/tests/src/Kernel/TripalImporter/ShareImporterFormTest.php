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
   * Data Provider: provides stage details to test import stages.
   *
   * @return array
   *   Each stage scenario is an array with the following values:
   *   - A string, human-readable short title text of the stage.
   *   - A string, the name of stage container or wrapper that contains all
   *     relevant stage specific form elements.
   *   - A string, the method name to generate the stage.
   *   - An array of expeceted features of the stage such as title or fields.
   *     The following keys are used to reference a value:
   *     - 'stage_title': The expected stage title text shown in the stage title
   *       banner of the form.
   *     - 'fields': A set of form elements expected to be rendered in a stage.
   *       Each field is keyed by the name of the field and the value is an
   *       array with the following keys:
   *       - 'wrapper_element': The name of the wrapper element if a field is
   *         contained in a wrapper element.
   *       - 'field_type': The field element type ie. a textfield or select.
   */
  public function provideStageDetails() {
    return [
      // #0: Stage One.
      [
        'stage 1 - upload',
        'accordion_stage1',
        'stage1',
        [
          'stage_title' => 'STAGE 1',
          'fields' => [
            'project' => [
              'wrapper_element' => '',
              'field_type' => 'textfield',
            ],
            'genus' => [
              'wrapper_element' => '',
              'field_type' => 'select',
            ],
            'file' => [
              'wrapper_element' => '',
              'field_type' => 'fieldset',
            ],
            'file_upload' => [
              'wrapper_element' => 'file',
              'field_type' => 'html5_file',
            ],
            'validate_stage' => [
              'wrapper_element' => '',
              'field_type' => 'submit',
            ],
          ],
        ],
      ],

      // #2: Stage Two.
      [
        'stage 2 - describe',
        'accordion_stage2',
        'stage2',
        [
          'stage_title' => 'STAGE 2',
          'fields' => [
            'validate_stage' => [
              'wrapper_element' => '',
              'field_type' => 'submit',
            ],
            'skip_stage' => [
              'wrapper_element' => '',
              'field_type' => 'submit',
            ],
          ],
        ],
      ],

      // #3: Stage Three.
      [
        'stage 3 - review and save',
        'accordion_stage3',
        'stage3',
        [
          'stage_title' => 'STAGE 3',
          'fields' => [],
        ],
      ],
    ];
  }

  /**
   * Tests that stages render with the correct stage elements.
   *
   * @param string $scenario
   *   A human-readable short title text of the stage.
   * @param string $stage_wrapper
   *   The name of stage container or wrapper that contains all relevant stage
   *   specific form elements.
   * @param string $stage_method
   *   The method name to generate the stage.
   * @param array $expected
   *   An array of expeceted features of the stage such as title or fields.
   *   The following keys are used to reference a value:
   *     - 'stage_title': The expected stage title text shown in the stage title
   *       banner of the form.
   *     - 'fields': A set of form elements expected to be rendered in a stage.
   *       Each field is keyed by the name of the field and the value is an
   *       array with the following keys:
   *       - 'wrapper_element': The name of the wrapper element if a field is
   *         contained in a wrapper element.
   *       - 'field_type': The field element type ie. a textfield or select.
   *
   * @dataProvider provideStageDetails
   */
  public function testStages($scenario, $stage_wrapper, $stage_method, $expected) {

    // Build $form parameter.
    $form = \Drupal::formBuilder()->getForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $this->definitions['test-trait-importer']['id']
    );

    // The initial page load has setup stage one and file fieldset element has
    // been relocated into the field wrapper element. This will restore the
    // original placement of the file field before any stage method
    // builds a form.
    $form['file'] = $form['accordion_stage1']['file'];

    // Build $form_state parameter.
    $form_state = new FormState();

    // Set the Stage.
    $this->phenoshare_importer->$stage_method($form, $form_state, '');

    // Check that the stage has the title.
    $stage_markup = $this->service_Renderer->renderInIsolation($form[$stage_wrapper]);
    preg_match('/<div class=".*\s">(.*?)<\/div>/', $stage_markup, $matches);
    $this->assertStringContainsString(
      $expected['stage_title'],
      $matches[1],
      'The stage title does not match expected stage title in scenario ' . $scenario
    );

    // Check that the stage contains the exepected field elememts.
    foreach ($expected['fields'] as $field_name => $field) {
      $field_placement = ($field['wrapper_element'])
        ? $form[$stage_wrapper][$field['wrapper_element']] : $form[$stage_wrapper];

      $this->assertArrayHasKey(
        $field_name,
        $field_placement,
        'The field element ' . $field_name . ' in ' . $scenario . ' could not be found in the form.'
      );

      $this->assertEquals(
        $field['field_type'],
        $field_placement[$field_name]['#type'],
        'The field element ' . $field_name . ' in ' . $scenario . ' has an incorrect field type.'
      );
    }
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
