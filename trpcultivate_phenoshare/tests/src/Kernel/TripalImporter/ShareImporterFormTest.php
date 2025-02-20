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
   * @var Drupal\trpcultivate_phenoshare\src\Plugin\TripalImporter\TripalCultivatePhenoShareImporter
   */
  protected $phenoshare_importer;

  /**
   * Phenotypes Share Importer plugin manager.
   *
   * @var Drupal\trpcultivate_phenotype\src\TripalCultivateValidator\TripalCultivatePhenotypesValidatorManager
   */
  protected $phenoshare_plugin_manager;

  /**
   * Configuration Factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $service_ConfigFactory;

  /**
   * Messenger service.
   *
   * @var Drupal\Core\Messenger\MessengerInterface
   */
  protected $service_Messenger;

  /**
   * Form builder.
   *
   * @var Drupal\Core\Form\FormBuilder
   */
  protected $service_FormBuilder;

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
    $this->service_ConigFactory = $container->get('config.factory');
    $this->service_Messenger = $container->get('messenger');
    $this->service_FormBuilder = $container->get('form_builder');

    // Phenoshare Importer instance.
    $this->phenoshare_plugin_manager = \Drupal::service('tripal.importer');
    $plugin_id = $this->definitions['test-share-importer']['id'];
    $this->phenoshare_importer = $this->phenoshare_plugin_manager->createInstance($plugin_id);

    $this->setTermConfig();

    $genus = 'Tripalus';
    $this->setOntologyConfig($genus);

    // Setup a genus and project to test.
    $project = 'Awesome Project';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => 'A project description',
      ])
      ->execute();

    \Drupal::service('trpcultivate_phenotypes.genus_project')
      ->setGenusToProject($project_id, $genus, TRUE);
  }

  /**
   * Data Provider: provides form values to test form build.
   *
   * @return array
   *   Each stage scenario is an array with the following values:
   *   - A string, human-readable short title text of the stage.
   *   - Integer, Zero-based index number of the stage.
   *   - The triggering element to set, to get to the next stage.
   *   - An array of expeceted features of the stage.
   *     The following keys are used to reference a value:
   *     - 'disable_import_button': the expected #disabled state of the Import
   *       button in the form.
   *     - 'completed_stages': the stages that have been marked completed
   *       depending on which page the form is currently at.
   *     - 'upcoming_stages': the stages that have been marked upcoming
   *       depending on which page the form is currently at.
   */
  public function provideFormValues() {
    return [
      [
        'stage 1',
        0,
        '',
        [
          'disable_import_button' => TRUE,
          'completed_stages' => [],
          'upcoming_stages' => [
            'STAGE 2',
            'STAGE 3',
          ],
        ],
      ],

      [
        'stage 2',
        1,
        'Validate Data File',
        [
          'disable_import_button' => TRUE,
          'completed_stages' => [
            'STAGE 1',
          ],
          'upcoming_stages' => [
            'STAGE 3',
          ],
        ],
      ],

      [
        'stage 3',
        2,
        'Check Values',
        [
          'disable_import_button' => TRUE,
          'completed_stages' => [
            'STAGE 1',
            'STAGE 2',
          ],
          'upcoming_stages' => [],
        ],
      ],
    ];
  }

  /**
   * Test form() method in the importer.
   *
   * This test will test how form() method assembles all stages.
   *
   * @param string $scenario
   *   A human-readable short title text of the stage.
   * @param int $stage_index
   *   A Zero-based index number of the stage.
   * @param string $trigger_element
   *   The triggering element to set, to get to the next stage.
   * @param array $expected
   *   An array of expeceted features of the stage.
   *     The following keys are used to reference a value:
   *     - 'disable_import_button': the expected #disabled state of the Import
   *       button in the form.
   *     - 'completed_stages': the stages that have been marked completed
   *       depending on which page the form is currently at.
   *     - 'upcoming_stages': the stages that have been marked upcoming
   *       depending on which page the form is currently at.
   *
   * @dataProvider provideFormValues
   */
  public function testForm($scenario, $stage_index, $trigger_element, $expected) {
    // Build $form_state parameter. These values are used to determine
    // which how the stages are prepared in the main importer form.
    $form_state = new FormState();

    $storage = $form_state->getStorage();
    // This will indicate that there is no failed validations.
    $storage['validation_result'] = [];
    $form_state->setStorage($storage);
    // This replicates the triggering element was set (form is submitted).
    $form_state->setValue('trigger_element', $trigger_element);
    $form_state->setTriggeringElement(
      [
        '#type' => 'submit',
        '#value' => $trigger_element,
      ]
    );
    // This initializes the form to the current stage.
    $form_state->setValue('current_stage', $stage_index);

    // Build $form parameter.
    $form = [];
    $form['file'] = [];

    $form['button'] = $this->service_FormBuilder
      ->getForm(
      'Drupal\tripal\Form\TripalImporterForm',
      $this->definitions['test-share-importer']['id']
      )['button'];

    // Build the form.
    $form = $this->phenoshare_importer->form($form, $form_state);

    $this->assertEquals(
      $expected['disable_import_button'],
      $form['button']['#disabled'],
      'Importer form() import button #disabled field attribute value does not match expected value in scenario ' . $scenario
    );

    // Before rendering, it is required to add the key #description_display
    // to field that uses #description, in this case schema select field is one.
    $form['advanced']['schema_name']['#description_display'] = 'after';
    $form_markup = $this->service_Renderer->renderInIsolation($form);
    // Get all <div> that wrap the stage title. The wrapper contains the
    // current status of every stages by inspecting the CSS class selector set.
    preg_match_all('/<div class="tcp-stage-title .*">.*?<\/div>/', (string) $form_markup, $matches);

    // Assert that the current stage is this stage.
    $this->assertStringContainsString(
      'tcp-current-stage',
      $matches[0][$stage_index],
      'The form has set the incorrect current stage in scenario ' . $scenario
    );

    // Assert that the remanining stages are upcoming and previous stages are
    // completed stages.
    $form_stages = [
      'completed' => [],
      'upcoming'  => [],
    ];

    foreach ($matches[0] as $i => $stage_wrapper) {
      if ($i == $stage_index) {
        continue;
      }

      preg_match('/STAGE [1-9]/', $stage_wrapper, $matches);

      if ($i < $stage_index && str_contains($stage_wrapper, 'tcp-completed-stage')) {
        array_push($form_stages['completed'], $matches[0]);
      }
      else {
        array_push($form_stages['upcoming'], $matches[0]);
      }
    }

    $this->assertEquals(
      $expected['completed_stages'],
      $form_stages['completed'],
      'Importer form() method completed stages do not match expected completed stages in scenario ' . $scenario
    );

    $this->assertEquals(
      $expected['upcoming_stages'],
      $form_stages['upcoming'],
      'Importer form() method upcoming stages do not match expected upcoming stages in scenario ' . $scenario
    );
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
    $form = $this->service_FormBuilder
      ->getForm(
        'Drupal\tripal\Form\TripalImporterForm',
        $this->definitions['test-share-importer']['id']
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
    preg_match('/<div class=".*\s">(.*?)<\/div>/', (string) $stage_markup, $matches);
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
      'Germplasm Name',
      'Sample Name',
      'Group',
      'Experimental Unit',
      'Replicate',
      'Timepoint',
      'Treatment',
    ];

    // Pull all the headers in the rendered description.
    preg_match_all('/<strong>(.*?)<\/strong>/', $rendered_file_format_description, $matches);
    $this->assertEquals(
      $expected_headers,
      $matches[1],
      'The headers defined by the importer does not match the headers rendered by describeUploadFileFormat()'
    );

    // Assert admin notes were incorporated into the description section.
    $expected_notes = 'To ensure proper file processing and organization, it is
    important that your data file includes a header.';

    $this->assertStringContainsString(
      $expected_notes,
      $rendered_file_format_description,
      'The rendered markup of the method describeUploadFileFormat() does not contain expected file format importer notes'
    );

    // Assert a download link was provided.
    // Construct the templage file filename.
    // Only the first item in the 'file_types' importer annotation is used as
    // default file extension of the template file.
    $plugin_id = $this->definitions['test-share-importer']['id'];
    $importer_annotations = $this->phenoshare_plugin_manager->getDefinitions();
    $expected_file_extension = $importer_annotations[$plugin_id]['file_types'][0];
    $expected_template_filename = $plugin_id . '-data-collection-template-file-' . $user_username . '.' . $expected_file_extension;

    $this->assertStringContainsString(
      $expected_template_filename,
      $rendered_file_format_description,
      'The rendered markup of the method describeUploadFileFormat() does not contain the expected file template filename.'
    );
  }

  /**
   * Data Provider: provides validation result array.
   *
   * @return array
   *   Each validation array scenario is an array with the following values:
   *   - A string, human-readable short title text of the stage.
   *   - A validation result array.
   *   - An array of expeceted values with the following keys.
   *     - 'has_failed': the expected value returned by 'hasFailedValidation'
   *       method given the validation result array.
   *
   * @todo update validation result with the new validation result array.
   */
  public function provideValidationResultArray() {
    return [
      // #0: Validation result array has failed item.
      [
        'has failed item',
        [
          'Genus Exists' => [
            'status' => 'fail',
            'detail' => 'Genus does not exist',
          ],
          'Project Exists' => [
            'status' => 'pass',
            'detail' => '',
          ],
        ],
        [
          'has_failed' => TRUE,
        ],
      ],

      // #1: Validation result array has no failed item.
      [
        'has no failed item',
        [
          'Genus Exists' => [
            'status' => 'pass',
            'detail' => '',
          ],
          'Project Exists' => [
            'status' => 'pass',
            'detail' => '',
          ],
        ],
        [
          'has_failed' => FALSE,
        ],
      ],
    ];
  }

  /**
   * Test hasFailedValidation() method in the importer.
   *
   * @param string $scenario
   *   A human-readable short title text of the stage.
   * @param array $validation_result_array
   *   A validation result array.
   * @param array $expected
   *   An array of expeceted values with the following keys.
   *   - 'has_failed': the expected value returned by 'hasFailedValidation'
   *     method given the validation result array.
   *
   * @dataProvider provideValidationResultArray
   *
   * @todo update validation result with the new validation result array.
   */
  public function testHasFailedValidation($scenario, $validation_result_array, $expected) {
    $has_failed = $this->phenoshare_importer->hasFailedValidation($validation_result_array);

    $this->assertEquals(
      $expected['has_failed'],
      $has_failed,
      'The validation result array status does not match status returned by hasFailedValidation() method in scenario ' . $scenario
    );
  }

  /**
   * Data Provider: provides config value to allow new configuration.
   *
   * @return array
   *   Each config value scenario is an array with the following values:
   *   - A string, human-readable short title text of the stage.
   *   - Boolean, True to allow and False to restrict new trait during import.
   *   - An array of expeceted values with the following keys.
   *     - 'has_message': A false value will indicate that the form will post a
   *       Drupal Status Message.
   *
   * @todo update validation result with the new validation result array.
   */
  public function provideAllowNewConfig() {
    return [
      // #0: True, allow new traits to be added during import.
      [
        'allow new config set to true',
        TRUE,
        [
          'has_message' => FALSE,
        ],
      ],

      // #1: False, prevent traits from being added during import.
      [
        'allow new config set to false',
        FALSE,
        [
          'has_message' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Test importer form notification relating to allow new config.
   *
   * @param string $scenario
   *   A human-readable short title text of the stage.
   * @param bool $set_value
   *   The value True will allow while False will restrict trait during import.
   * @param array $expected
   *   An array of expeceted values with the following keys.
   *     - 'has_message': A false value will indicate that the form will post a
   *       Drupal Status Message.
   *
   * @dataProvider provideAllowNewConfig
   */
  public function testFormAllowNewNotification($scenario, $set_value, $expected) {

    $form = $this->service_FormBuilder
      ->getForm(
        'Drupal\tripal\Form\TripalImporterForm',
        $this->definitions['test-share-importer']['id']
      );

    $form_state = new FormState();

    $this->service_ConigFactory
      ->getEditable('trpcultivate_phenotypes.settings')
      ->set('trpcultivate.phenotypes.ontology.allownew', $set_value)
      ->save();

    $this->phenoshare_importer->form($form, $form_state);

    // Collect all messages of type status posted through the messenger service.
    $messages = $this->service_Messenger->all();

    $this->assertEquals(
      $expected['has_message'],
      isset($messages['status']),
      'The form allow new notification message should coincide with the configured value ' . $scenario
    );
  }

  /**
   * Test ajaxLoadGenusOfProject() method in the importer.
   */
  public function testAjaxLoadGenusOfProject() {

    $form = $this->service_FormBuilder
      ->getForm(
        'Drupal\tripal\Form\TripalImporterForm',
        $this->definitions['test-share-importer']['id']
      );

    $form_state = new FormState();
    $form_state->setValue('project', 'Awesome Project');

    $this->phenoshare_importer->ajaxLoadGenusOfProject($form, $form_state);
  }

}
