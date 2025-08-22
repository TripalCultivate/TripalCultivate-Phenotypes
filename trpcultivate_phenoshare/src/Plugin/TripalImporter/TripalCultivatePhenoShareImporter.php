<?php

namespace Drupal\trpcultivate_phenoshare\Plugin\TripalImporter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\trpcultivate\Plugin\Validators\EmptyCell;
use Drupal\trpcultivate\Plugin\Validators\GermplasmNameExists;
use Drupal\trpcultivate\Plugin\Validators\ValidDataFile;
use Drupal\trpcultivate\Plugin\Validators\ValidDelimitedFile;
use Drupal\trpcultivate\Plugin\Validators\ValidHeaders;
use Drupal\trpcultivate_phenotypes\Plugin\Validators\ProjectGenusMatch;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use Drupal\trpcultivate\Service\TripalCultivateFileTemplateService;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tripal\TripalImporter\Attribute\TripalImporter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Tripal Cultivate Phenotypes - Share Importer.
 *
 * An importer focused on phenotypic data which has already been published or
 * which is ready to be freely shared.
 *
 * @TripalImporter(
 *   id = "trpcultivate-phenotypes-share-importer",
 *   label = @Translation("Tripal Cultivate: Open Science Phenotypic Data"),
 *   description = @Translation("Imports phenotypic data which has already been published or which is ready to be freely shared."),
 *   file_types = {"tsv"},
 *   upload_description = @Translation("Please provide a data file."),
 *   upload_title = @Translation("Phenotypic Data File"),
 *   use_analysis = FALSE,
 *   require_analysis = FALSE,
 *   use_button = TRUE,
 *   submit_disabled = TRUE,
 *   button_text = "Import",
 *   file_upload = TRUE,
 *   file_local  = FALSE,
 *   file_remote = FALSE,
 *   file_required = TRUE,
 *   cardinality = 1,
 *   menu_path = "",
 *   callback = "",
 *   callback_module = "",
 *   callback_path = "",
 * )
 */
#[TripalImporter(
   id: 'trpcultivate-phenotypes-share-importer',
   label: new TranslatableMarkup('Tripal Cultivate: Open Science Phenotypic Data'),
   description: new TranslatableMarkup('Imports phenotypic data which has already been published or which is ready to be freely shared.'),
   file_types: ['tsv'],
   upload_description: new TranslatableMarkup('Please provide a data file.'),
   upload_title: new TranslatableMarkup('Phenotypic Data File'),
   use_analysis: FALSE,
   require_analysis: FALSE,
   use_button: TRUE,
   submit_disabled: TRUE,
   button_text: new TranslatableMarkup('Import'),
   file_upload: TRUE,
   file_local: FALSE,
   file_remote: FALSE,
   file_required: TRUE,
   cardinality: 1,
   menu_path: '',
   callback: '',
   callback_path: '',
 )]
class TripalCultivatePhenoShareImporter extends ChadoImporterBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The field name to reference the current stage form field element.
   *
   * @var string
   */
  private const CURRENT_STAGE = 'current_stage';

  /**
   * The key to reference the validation result array in Drupal storage system.
   *
   * @var string
   */
  private const VALIDATION_RESULT = 'validation_result';

  /**
   * Headers required by this importer.
   *
   * @var array
   *
   * The following keys are required:
   * - 'name': The column header name as it should appear in the input file.
   * - 'description': A user-friendly description of the header that will be
   *   displayed to the user through the form.
   * - 'type': one of "required" or "optional" to indicate whether the column
   *   needs to have values present or not.
   *
   * NOTE: Order MUST reflect the desired order of headers in the input file.
   */
  private $headers = [
    [
      'name' => 'Germplasm Name',
      'description' => 'The official name of the accession phenotyped. This accession name should be an exact match of an accession previously imported using a Germplasm Importer.',
      'type' => 'required',
    ],
    [
      'name' => 'Sample Name',
      'description' => 'A unique identifier or label of the plant material being measured. For example, the germplasm entry number or the seed packet label.',
      'type' => 'required',
    ],
    [
      'name' => 'Group',
      'description' => 'A simple descriptor of the environmental variables for this particular grouping of experimental subjects or germplasm. For example, site location, heated vs. room temperature, or assay.',
      'type' => 'required',
    ],
    [
      'name' => 'Experimental Unit',
      'description' => 'The identifier for the physical entity (e.g. plot, plant, protein extraction) that measurements are being taken on. For example, the plot identifier in a field experiment or the test tube label in a biochemical assay.',
      'type' => 'required',
    ],
    [
      'name' => 'Replicate',
      'description' => 'The number indicating the replicate of the sample.',
      'type' => 'required',
    ],
    [
      'name' => 'Timepoint',
      'description' => 'The most specific timepoint common to all measurements recorded on a single row in the file. For example, if the measurements are days to various growth stages then this might be the planting date. Alternatively, if the measurements are all relating to specific biochemical assay run or drone flyover then the assay date or drone flyover date would be used in order to keep the rows of the file unique.',
      'type' => 'required',
    ],
    [
      'name' => 'Treatment',
      'description' => 'Refers to specific condition or manipulation that is applied. For example, fertilizer, weeding pressure, nitrogen supplementation, or temperature.',
      'type' => 'required',
    ],
  ];

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $service_ConfigFactory;

  /**
   * The TripalCultivate validator plugin manager.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $service_validatorPluginManager;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $service_entityTypeManager;

  /**
   * Genus Ontology Service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected $service_PhenoGenusOntology;

  /**
   * The TripalCultivate File Template Service.
   *
   * @var \Drupal\trpcultivate\Service\TripalCultivateFileTemplateService
   */
  protected TripalCultivateFileTemplateService $service_FileTemplate;

  /**
   * The Drupal Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $service_Renderer;

  /**
   * The Drupal Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $service_Messenger;

  /**
   * Expected column settings.
   *
   * @var array
   */
  private $expected_columns;

  /**
   * Constructs the Phenotypes Share importer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory service.
   * @param Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   The genus ontology service.
   * @param Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager $service_validatorPluginManager
   *   The TripalCultivate validator plugin manager.
   * @param Drupal\trpcultivate\Service\TripalCultivateFileTemplateService $service_FileTemplate
   *   The service used to generate the termplate file.
   * @param Drupal\Core\Entity\EntityTypeManager $service_entityTypeManager
   *   The entity type manager.
   * @param Drupal\Core\Render\Renderer $renderer
   *   The Drupal renderer service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ChadoConnection $chado_connection,
    ConfigFactoryInterface $config_factory,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivateValidatorManager $service_validatorPluginManager,
    TripalCultivateFileTemplateService $service_FileTemplate,
    EntityTypeManager $service_entityTypeManager,
    Renderer $renderer,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $chado_connection);

    $this->service_ConfigFactory = $config_factory;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
    $this->service_validatorPluginManager = $service_validatorPluginManager;
    $this->service_FileTemplate = $service_FileTemplate;
    $this->service_entityTypeManager = $service_entityTypeManager;
    $this->service_Renderer = $renderer;
    $this->service_Messenger = $messenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
      $container->get('config.factory'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('plugin.manager.trpcultivate_validator'),
      $container->get('trpcultivate.template_generator'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('messenger'),
    );
  }

  /**
   * Configure all the validators this importer uses.
   *
   * @param array $form_values
   *   An array of the importer form values provided to formValidate.
   * @param string $file_mime_type
   *   A string of the MIME type of the input file, usually grabbed from the
   *   file object using $file->getMimeType()
   *
   * @return array
   *   A listing of configured validator objects first keyed by their inputType.
   *   More specifically:
   *   - [inputType]: and array of validator instances. Not an
   *     associative array although the keys do indicate what
   *     order they should be run in.
   */
  public function configureValidators(array $form_values, string $file_mime_type) {

    $validators = [];

    // Make the header columns into a simplified array for easy reference:
    // - Keyed by the column header name.
    // - Values are the column header's position in the $headers property (ie.
    //   its index if we assume no keys were assigned).
    $header_index = [];
    $headers = $this->headers;
    foreach ($headers as $i => $column_details) {
      $header_index[$column_details['name']] = $i;
    }

    // -----------------------------------------------------
    // Metadata
    // - Project and Genus match.
    $instance = $this->service_validatorPluginManager->createInstance('project_genus_match');
    $validators['metadata']['project_genus_match'] = $instance;

    // -----------------------------------------------------
    // File level
    // - File exists and is the expected type
    $instance = $this->service_validatorPluginManager->createInstance('valid_data_file');
    // Set supported mime-types using the valid file extensions (file_types) as
    // defined in the annotation for this importer on line 25.
    $supported_file_extensions = $this->plugin_definition['file_types'];
    $instance->setSupportedMimeTypes($supported_file_extensions);
    $validators['file']['valid_data_file'] = $instance;

    // -----------------------------------------------------
    // Raw row level
    // - File rows are properly delimited
    $instance = $this->service_validatorPluginManager->createInstance('valid_delimited_file');
    // Count the number of columns and configure it for this validator. We want
    // this number to be strict = FALSE, thus extra columns are allowed.
    $num_columns = count($this->headers);
    $instance->setExpectedColumns($num_columns, FALSE);
    $this->expected_columns = $instance->getExpectedColumns();
    // Set the MIME type of this input file.
    $instance->setFileMimeType($file_mime_type);
    $validators['raw-row']['valid_delimited_file'] = $instance;

    // -----------------------------------------------------
    // Header Level
    // - All column headers match expected header format
    $instance = $this->service_validatorPluginManager->createInstance('valid_headers');
    // Use our $headers property to configure what we expect for a header in the
    // input file.
    $instance->setHeaders($this->headers);
    // Configure the expected number of columns and set it to be strict.
    $instance->setExpectedColumns($num_columns, FALSE);
    $validators['header-row']['valid_header'] = $instance;

    // -----------------------------------------------------
    // Data Row Level
    // - All data row cells in columns 0, 1, 2, 3, 4, 5, 6 are not empty
    $instance = $this->service_validatorPluginManager->createInstance('empty_cell');
    $indices = [
      $header_index['Germplasm Name'],
      $header_index['Sample Name'],
      $header_index['Group'],
      $header_index['Experimental Unit'],
      $header_index['Replicate'],
      $header_index['Timepoint'],
      $header_index['Treatment'],
    ];
    $instance->setIndices($indices);
    $validators['data-row']['empty_cell'] = $instance;

    $instance = $this->service_validatorPluginManager->createInstance('germplasm_name_exists');
    $instance->setIndices([0]);
    $instance->setGenus($form_values['genus']);
    $validators['data-row']['germplasm_name_exists'] = $instance;

    return $validators;
  }

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    // Always call the parent form to ensure Chado is handled properly.
    $form = parent::form($form, $form_state);

    // Attach scripts and libraries.
    $form['#attached']['library'] = [
      'trpcultivate_phenotypes/trpcultivate-phenotypes-style-stage-accordion',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-stage-accordion',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-autoselect-field',
    ];

    // Remind user about the configuration value set for allow new.
    $allownew = $this->service_ConfigFactory
      ->get('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.ontology.allownew');

    if ($allownew == FALSE) {
      $allownew_minder = $this->t('This module is set to NOT to allow new trait, new method and new unit to be added
        during the upload process. Please make sure that all trait, method and unit exist in Chado (cvterm)
        before uploading your data file.');

      $this->service_Messenger->addMessage($allownew_minder);
    }

    // This is a reminder to user about expected phenotypic data.
    $phenotypes_minder = $this->t('Phenotypic data should be filtered for outliers and mis-entries before
      being uploaded here. Do not upload data that should not be used in the final analysis for a
      scientific article. Furthermore, data should NOT BE AVERAGED across replicates or site-year.');
    $this->service_Messenger->addWarning($phenotypes_minder);

    // Cacheing of stage number:
    // Cache current stage and id field to allow script to reference this value.
    // Account for failed validation by referring to Drupal $storage system
    // for full validation result values saved.
    $storage = $form_state->getStorage();
    // Flag to indicate if validation has returned a failed status.
    $has_fail = FALSE;

    if (isset($storage[self::VALIDATION_RESULT])) {
      $has_fail = $this->hasFailedValidation($storage[self::VALIDATION_RESULT]);
    }

    $triggering_element = $form_state->getTriggeringElement();
    $valid_triggering_element = [
      // Stage 1.
      'Validate Data File',
      // Stage 2.
      'Check Values',
      // Stage 2.
      'Skip',
    ];

    $stage = (!$has_fail && $form_state->getValue('trigger_element') && in_array($triggering_element['#value'], $valid_triggering_element))
      ? (int) $form_state->getValue(self::CURRENT_STAGE) + 1
      : 1;

    $form[self::CURRENT_STAGE] = [
      '#type' => 'hidden',
      '#value' => $stage,
      '#attributes' => ['id' => 'tcp-current-stage'],
    ];

    // Rendering of Stage:
    // Compose stage array that will become the basis of the stages rendered in
    // stage accordion layout. Each stage is a method titled stage + stage no
    // (ie. stage1).
    $stage_methods = get_class_methods(get_class($this));
    $total_stages = 0;

    foreach ($stage_methods as $method) {
      if (preg_match('/stage([1-9])/', $method, $matches)) {
        if ($matches[1]) {
          $stage_no = $matches[1];

          // Call method to build stage.
          // Set the status of the stage (current, complete, or upcoming).
          $stage_status = '';
          if ($stage == $stage_no) {
            // Is the current stage.
            $stage_status = 'tcp-current-stage';
          }
          elseif ($stage_no < $stage) {
            // Is the previous completed stage.
            $stage_status = 'tcp-completed-stage';
          }

          $this->$method($form, $form_state, $stage_status);
          $total_stages++;
        }
      }
    }

    // Enable the Import button at final stage by setting a value in $form_state
    // storage keyed by 'disable_TripalImporter_submit' to FALSE (enabled).
    if ($stage > ($total_stages - 1)) {
      $storage['disable_TripalImporter_submit'] = FALSE;
      $form_state->setStorage($storage);
    }

    return $form;
  }

  /**
   * Stage 1: Upload data file. Method/Function template.
   *
   * Subsequent stages in accordion will correspond to a public method titled
   * stage + stage number (ie. stage1, or stage2).
   * In each method will define the stage markup and form render array using
   * the structure below:
   *
   * <div class="tcp-stage-title">Title</div>
   * <div class="tcp-stage-content">
   *   Stage Body/Content
   *   Form render array - stage form elements.
   * </div>
   *
   * Additional field elements will be wrapped using the field wrapper
   * variable defined in each stage accordion, in the following format:
   *
   * accordion_stage + STAGE NUMBER (ie. accordion_stage1)
   *
   * @param array $form
   *   Drupal form array.
   * @param object $form_state
   *   Drupal form state object.
   * @param string $stage_status
   *   CSS class selector name to style each stage with the correct css class
   *   corresponding to a stage - completed, current and upcoming stage.
   */
  public function stage1(&$form, $form_state, $stage_status = '') {
    // Describe stage by providing the stage number and title of the stage.
    // The status key in the stage description array corresponds to the
    // parameter of the method and is determined by the method call to render
    // stage in the form build above.
    $stage = [
      'stage#' => 1,
      'title'  => 'Upload Data File',
      'status' => $stage_status,
    ];

    // Field wrapper name. All additional elements that go into this stage
    // should use this name to encapsulate into a specific stage in
    // the accordion.
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[$fld_wrapper] = $this->createStageAccordion($stage);

    // Validation result.
    $storage = $form_state->getStorage();
    // Full validation result.
    if (isset($storage[self::VALIDATION_RESULT])) {
      $validation_result = $storage[self::VALIDATION_RESULT];

      $form[$fld_wrapper]['validation_result'] = [
        '#type' => 'inline_template',
        '#theme' => 'validation_result_window',
        '#data' => [
          'validation_result' => $validation_result,
        ],
        '#weight' => -100,
      ];
    }

    // Other relevant fields here.
    // Select experiment, Genus field will reflect the genus project is set to.
    $form[$fld_wrapper]['project'] = [
      '#title' => 'Research Experiment',
      '#type' => 'textfield',
      '#weight' => -100,
      '#required' => TRUE,
      '#description' => $this->t('Enter the name of the research experiment your data was generated as part of.'),
      '#description_display' => 'after',
      '#attributes' => ['placeholder' => 'Research Experiment Name', 'class' => ['tcp-autocomplete']],
      '#autocomplete_route_name' => 'tripal_chado.generic_autocomplete',
      '#autocomplete_route_parameters' => [
        'type_id' => 0,
        'match_limit' => 5,
        'base_table' => 'project',
        'column_name' => 'name',
        'type_column' => 'x',
        'property_table' => 'project',
      ],
    ];

    // Field Genus:
    // Prepare select options with only active genus.
    $all_genus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    $active_genus = array_combine($all_genus, $all_genus);

    $form[$fld_wrapper]['genus'] = [
      '#title' => 'Genus',
      '#type' => 'select',
      '#options' => $active_genus,
      '#weight' => -90,
      '#required' => TRUE,
      '#description' => $this->t('Select the genus for the germplasm represented within the data being uploaded.
        This genus must be configured for the selected Research Experiment. Please contact us if you do not see the intended genus.'),
      '#description_display' => 'after',

      // States.
      '#states' => [
        'disabled' => [
          ':input[name="project"]' => ['filled' => FALSE],
        ],
      ],

      // Used by script to pre-select when project was supplied.
      '#id' => 'trpcultivate-fld-genus',

      // AJAX.
      '#prefix' => '<div id="trpcultivate-field-genus-wrapper">',
      '#suffix' => '</div>',
    ];

    // Apply field stage field wrapper to file upload element.
    // For the file upload field to conform to the accordion layout,
    // this override script must be performed.
    $file_upload = $form['file'];
    $form[$fld_wrapper]['file'] = $file_upload;
    // Omit old copy so there would not be duplicate file
    // element in the upload data file stage.
    $form['file'] = [];

    // Other relevant fields here.
    // This importer does not support using file sources from existing field.
    // #access: (bool) Whether the element is accessible or not; when FALSE,
    // the element is not rendered and the user submitted value is not taken
    // into consideration.
    $form[$fld_wrapper]['file']['file_upload_existing']['#access'] = FALSE;

    // Stage submit button.
    $form[$fld_wrapper]['validate_stage'] = [
      '#type' => 'submit',
      '#value' => 'Validate Data File',
      '#name' => 'trigger_element',
    ];
  }

  /**
   * Stage 2: Validate data.
   *
   * @param array $form
   *   Drupal form array.
   * @param object $form_state
   *   Drupal form state object.
   * @param string $stage_status
   *   CSS class selector name to style each stage with the correct css class
   *   corresponding to a stage - completed, current and upcoming stage.
   */
  public function stage2(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 2,
      'title'  => 'Describe Traits',
      'status' => $stage_status,
    ];

    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[$fld_wrapper] = $this->createStageAccordion($stage);

    // Other relevant fields here.
    $form[$fld_wrapper]['field_elements'] = [
      '#markup' => '<p>Stage 2 field elements here</p>',
    ];

    // Stage submit button.
    $form[$fld_wrapper]['validate_stage'] = [
      '#type' => 'submit',
      '#value' => 'Check Values',
      '#name' => 'trigger_element',
    ];

    $form[$fld_wrapper]['skip_stage'] = [
      '#type' => 'submit',
      '#value' => 'Skip',
      '#name' => 'trigger_element',
    ];
  }

  /**
   * Stage 3: Describe and save data.
   *
   * @param array $form
   *   Drupal form array.
   * @param object $form_state
   *   Drupal form state object.
   * @param string $stage_status
   *   CSS class selector name to style each stage with the correct css class
   *   corresponding to a stage - completed, current and upcoming stage.
   */
  public function stage3(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 3,
      'title'  => 'Review Data',
      'status' => $stage_status,
    ];

    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[$fld_wrapper] = $this->createStageAccordion($stage);

    // Other relevant fields here.
    $form[$fld_wrapper]['field_elements'] = [
      '#markup' => '<p>Stage 3 summary table here</p>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formSubmit($form, &$form_state) {
    // Display successful message to user if file import was without any error.
    $this->service_Messenger
      ->addStatus($this->t('<b>Your file import was successful and a Job Process Request has been created to securely save your data.</b>'));
  }

  /**
   * {@inheritdoc}
   */
  public function formValidate($form, &$form_state) {
    $form_state_values = $form_state->getValues();

    // T4 - this values is from autocomplete field which seems to contain the
    // project id (id) part.
    $project = preg_replace('/\([0-9]\)$/', '', $form_state_values['project']);
    $project_name = trim($project);
    $form_state_values['project'] = $project_name;

    // Current stage.
    // Get the cached stage value from the formstate values and perform
    // validation when it is set to a value. Starting with index 1 - as stage 1,
    // index 2 as stage 2 and so on to the last stage.
    //
    // NOTE: not all stages require a validation and a subsequent condition will
    // target a specific stage to perform pertinent validation.
    if (array_key_exists(self::CURRENT_STAGE, $form_state_values)) {
      $stage = $form_state_values[self::CURRENT_STAGE];

      // This will support re-upload of a file but form has performed
      // validation of a previously uploaded file.
      if ($stage > 1 && $form_state->getValue('trigger_element') == 'Validate Data File') {
        // Stage is no longer stage 1 from previous upload and
        // triggering element
        // is the upload file (in stage 1).
        // Reset the stage to stage 1 to perform validation below.
        $stage = 1;
        // Cache stage.
        $form_state->setValue(self::CURRENT_STAGE, $stage);
      }

      if ($stage >= 1) {

        // Validate Stage 1.
        if ($stage == 1 && $form_state_values['file_upload']) {
          $form_values = $form_state_values;

          $file_id = $form_values['file_upload'];

          // Load our file object.
          $file = $this->service_entityTypeManager->getStorage('file')->load($file_id);

          // Get the mime type which is used to validate the file and
          // split the rows.
          $file_mime_type = $file->getMimeType();

          // Configure the validators.
          $validators = $this->configureValidators($form_values, $file_mime_type);

          // A FLAG to keep track if any validator fails.
          // We will only continue to the next input-type if all validators of
          // the current input-type pass.
          $failed_validator = FALSE;

          // Keep track of failed items. This is a nested array keyed
          // as follows:
          // - The unique name of a validator instance, which maps to the
          //   second level of the $validators array.
          //   - For row-level input-type validators, this is further keyed by
          //     the row number that the failure for this validator
          //     instance occurred.
          // The value (level 1 for non row-level validators, level 2 for
          // row-level validators) is the validation results array returned by
          // the validator.
          $failures = [];

          // *******************************************************************
          // Metadata Validation
          // *******************************************************************
          foreach ($validators['metadata'] as $validator_name => $validator) {
            // Set failures for this validator name to an empty array to signal
            // that this validator has been run.
            $failures[$validator_name] = [];
            // Validate metadata input value.
            $result = $validator->validateMetadata($form_values);

            // Check if validation failed and save the results if it did.
            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $failed_validator = TRUE;
              $failures[$validator_name] = $result;
            }
          }

          // Perform other validation level if the previous level did not find
          // any issues with input vlues.
          if ($failed_validator === FALSE) {
            // *****************************************************************
            // File Validation
            // *****************************************************************
            foreach ($validators['file'] as $validator_name => $validator) {
              // Set failures for this validator name to an empty array to
              // signal that this validator has been run.
              $failures[$validator_name] = [];
              $result = $validator->validateFile($file_id);

              // Check if validation failed and save the results if it did.
              if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
                $failed_validator = TRUE;
                $failures[$validator_name] = $result;
              }
            }
          }

          // Check if any previous validators failed before moving on to the
          // next input-type validation.
          if ($failed_validator === FALSE) {
            // Open and read file in this uri.
            $file_uri = $file->getFileUri();
            $handle = fopen($file_uri, 'r');

            // Line counter.
            $line_no = 0;

            // Begin column and row validation.
            while (!feof($handle)) {
              // This valirable will indicate if the validator has failed. It is
              // set to FALSE for every row to indicate that the line is valid
              // to start with, then execute the tests below to prove otherwise.
              $row_has_failed = FALSE;

              // Current row.
              $line = fgets($handle);
              $line_no++;
              // Skip this line if its empty, but line numbers should
              // remain accurate.
              if (empty(trim($line))) {
                continue;
              }

              // ***************************************************************
              // Raw Row Validation
              // ***************************************************************
              foreach ($validators['raw-row'] as $validator_name => $validator) {
                // Set failures for this validator name to an empty array to
                // signal that this validator has been run.
                if (!array_key_exists($validator_name, $failures)) {
                  $failures[$validator_name] = [];
                }

                $result = $validator->validateRawRow($line);

                // Check if validation failed and save the results if it did.
                if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
                  $row_has_failed = TRUE;
                  $failures[$validator_name][$line_no] = $result;
                }
              }

              // If any row-row validators failed, skip further validation and
              // move on to the next row in the data file.
              if ($row_has_failed === TRUE) {
                $failed_validator = TRUE;
                continue;
              }

              // ***************************************************************
              // Header Row Validation
              // ***************************************************************
              if ($line_no == 1) {
                // Split line into an array of values.
                $header_row = ImportValidationHelper::splitRowIntoColumns($line, $file_mime_type);

                foreach ($validators['header-row'] as $validator_name => $validator) {
                  // Set failures for this validator name to an empty array to
                  // signal that this validator has been run.
                  if (!array_key_exists($validator_name, $failures)) {
                    $failures[$validator_name] = [];
                  }

                  $result = $validator->validateRow($header_row);

                  // Check if validation failed and save the results if it did.
                  if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
                    $row_has_failed = TRUE;
                    $failures[$validator_name] = $result;
                  }
                }

                // If any header-row validators failed, skip validation of the
                // data rows.
                if ($row_has_failed === TRUE) {
                  $failed_validator = TRUE;
                  break;
                }
              }

              // ***************************************************************
              // Data Row Validation
              // ***************************************************************
              elseif ($line_no > 1) {
                // Split line into an array using the delimiter supported by
                // this importer when it was configured.
                $data_row = ImportValidationHelper::splitRowIntoColumns($line, $file_mime_type);

                // Call each validator on this row of the file.
                foreach ($validators['data-row'] as $validator_name => $validator) {
                  // Set failures for this validator name to an empty array to
                  // signal that this validator has been run, but ONLY if it
                  // doesn't exist. (ie. this validator may have already failed
                  // on a previous row, so we don't want to overwrite previous
                  // validation failures.)
                  if (!array_key_exists($validator_name, $failures)) {
                    $failures[$validator_name] = [];
                  }
                  $result = $validator->validateRow($data_row);
                  // Check if validation failed.
                  if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
                    $row_has_failed = TRUE;
                    $failed_validator = TRUE;
                    $failures[$validator_name][$line_no] = $result;
                  }
                }
              }
            }

            // Close the file.
            fclose($handle);
          }

          $validation_feedback = $this->processValidationMessages($failures);

          // Save all validation results in Drupal storage to create a
          // summary report.
          $storage = $form_state->getStorage();
          $storage[self::VALIDATION_RESULT] = $validation_feedback;
          $form_state->setStorage($storage);

          // Check if the $validation_feedback contains 'fail' or 'todo' status.
          // If either is found, prevent form submission.
          $submit_form = TRUE;

          foreach ($validation_feedback as $feedback_item) {
            if ($feedback_item['status'] == 'todo' || $feedback_item['status'] == 'fail') {
              $submit_form = FALSE;

              // No need to inspect other validators, a single instance of
              // fail/todo is sufficient to prevent form submission.
              break;
            }
          }

          if ($submit_form === FALSE) {
            // Provide a general error message indicating that input values
            // and/or the data file may contain one or more errors.
            $this->service_Messenger
              ->addError($this->t('Your file import was not successful. Please check the Validation Result Window for errors and try again.'));

            // Prevent this form from submitting and reload form with all the
            // validation failures in the storage system.
            $form_state->setRebuild(TRUE);
          }
        }
      }
    }
  }

  /**
   * Begin process validation messages.
   */

  /**
   * Configures and processes validation messages for the user.
   *
   * @param array $failures
   *   An array containing the return values from any failed validators. If
   *   validation was run for a validator instance, this is keyed by the unique
   *   name assigned to each validator-input type combination. This key will
   *   only contain values IF validation failed at any point that it was run. It
   *   is further keyed by row number IF the validator failed on that row as a
   *   row-level validator.
   *   Specifically:
   *   - [VALIDATOR INSTANCE NAME]
   *     - [ROW NUMBER (only if row-level validator)]
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': an array of items that failed. Structure of this
   *         array is dependent on the validator.
   *
   * @return array
   *   An array of feedback to provide to the user. It summarizes the validation
   *   results reported by the validators in formValidate (i.e. $failures). This
   *   array is keyed by a validation line, which is a string that is associated
   *   with a line in the validate UI dispalyed to the user. Specifically:
   *   - [VALIDATION LINE]:
   *     - 'title': A user-focused message describing the validation that took
   *       place.
   *     - 'status': One of: 'todo', 'pass', 'fail'.
   *     - 'details': A render array that will display details of any failures
   *       to guide the user to fix problems with their input file. The type of
   *       render array depends on the validator, but the most common types are
   *       item list and table.
   */
  public function processValidationMessages($failures) {

    $messages = [
      'project_genus_match' => [
        'title' => 'Research Experiment exists and has been configured with selected genus',
        'status' => 'todo',
        'details' => '',
      ],
      'valid_data_file' => [
        'title' => 'File is valid and not empty',
        'status' => 'todo',
        'details' => '',
      ],
      'valid_delimited_file' => [
        'title' => 'Lines are properly delimited',
        'status' => 'todo',
        'details' => '',
      ],
      'valid_header' => [
        'title' => 'File has all of the column headers expected',
        'status' => 'todo',
        'details' => '',
      ],
      'empty_cell' => [
        'title' => 'Required cells contain a value',
        'status' => 'todo',
        'details' => '',
      ],
      'germplasm_name_exists' => [
        'title' => 'Germplasm Name exists in the database',
        'status' => 'todo',
        'details' => '',
      ],
    ];

    $header_names = array_column($this->headers, 'name');

    // A flag to indicate whether any data row level validation can be set to
    // pass or remains as 'todo' if there are no failures at that stage. This is
    // because we don't want to mislead the user to think all data rows pass
    // validation if there are raw rows that failed, since they haven't been
    // looked at yet by data row validators.
    $raw_row_failed = FALSE;

    // ProjectGenusMatch.
    $validator_name = 'project_genus_match';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        // Change wording in messages from 'project' to 'Research Experiment'.
        $tokens = [
          'project' => 'Research Experiment',
        ];
        $messages[$validator_name]['details'] = ProjectGenusMatch::processItemWithSimpleList($failures[$validator_name], $tokens);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // ValidDataFile.
    $validator_name = 'valid_data_file';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = ValidDataFile::processItemWithSimpleList($failures[$validator_name]);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // ValidDelimitedFile.
    $validator_name = 'valid_delimited_file';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        // Set this flag so that data row-level validation doesn't pass.
        $raw_row_failed = TRUE;
        $messages[$validator_name]['status'] = 'fail';

        $metadata = [
          'strict_flag' => $this->expected_columns['strict'],
          'number_of_columns' => $this->expected_columns['number_of_columns'],
        ];
        $messages[$validator_name]['details'] = ValidDelimitedFile::processListWithDescribedTable($failures[$validator_name], $metadata);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // ValidHeaders.
    $validator_name = 'valid_header';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';

        $metadata = [
          'column_headers' => $header_names,
        ];
        $messages[$validator_name]['details'] = ValidHeaders::processListWithDescribedTable($failures[$validator_name], $metadata);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // EmptyCell.
    $validator_name = 'empty_cell';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';

        $metadata = [
          'column_headers' => $header_names,
        ];
        $messages[$validator_name]['details'] = EmptyCell::processListWithDescribedTable($failures[$validator_name], $metadata);
      }
      // Only pass if raw row validation didn't fail.
      elseif (!$raw_row_failed) {
        $messages[$validator_name]['status'] = 'pass';
      }
      // Otherwise, leave status as 'todo' since 1+ raw rows failed.
    }

    // GermplasmNameExists.
    $validator_name = 'germplasm_name_exists';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';

        $metadata = [
          'column_headers' => [$header_names[0]],
        ];
        $messages[$validator_name]['details'] = GermplasmNameExists::processListWithDescribedTable($failures[$validator_name], $metadata);
      }
      // Only pass if raw row validation didn't fail.
      elseif (!$raw_row_failed) {
        $messages[$validator_name]['status'] = 'pass';
      }
      // Otherwise, leave status as 'todo' since 1+ raw rows failed.
    }

    return $messages;
  }

  /**
   * End process validation messages.
   */

  /**
   * {@inheritDoc}
   */
  public function run() {

  }

  /**
   * {@inheritdoc}
   */
  public function postRun() {

  }

  /**
   * Describe the upload format including column descriptions + template file.
   *
   * Class TripalImporterBase is the parent class of this method and additional
   * documentation is available in reference link below.
   *
   * NOTE: This method supports full HTML markup output.
   *
   * All relevant information relating to expected column headers and usage
   * notes are laid out using the theme 'importer_header'. This is rendered
   * using the referenced TWIG file below.
   *
   * A template geneartor service is utilized to provide a downloadable file
   * template, pre-configured to contain all headers required. The link to
   * this template file is also formatted using the theme 'importer_header'.
   *
   * @return string
   *   The fully rendered HTML string produced by the 'importer_header' theme
   *   with the pertinent variables supplied by this method.
   *
   * @see Drupal\tripal\TripalImporter\TripalImporterBase::describeUploadFileFormat()
   * @see templates\trpcultivate-phenotypes-template-importer-header.html.twig
   */
  public function describeUploadFileFormat() {
    // A template file has been generated and is ready for download.
    $importer_id = $this->pluginDefinition['id'];

    // Only the header names are needed for making the template file, so pull
    // them out into a new array.
    $column_headers = array_column($this->headers, 'name');

    // File types 'file_types' annotation definition of this importer.
    // The first item in the definition list will be used as the primary
    // file extension of the template file.
    // File MIME type and delimiter are based on mapping information defined
    // in the validator base and file types validator trait.
    $file_extensions = $this->plugin_definition['file_types'];

    $file_link = $this->service_FileTemplate
      ->generateFile($importer_id, $column_headers, $file_extensions);

    // Additional notes to the headers.
    $notes = $this->t('To ensure proper file processing and organization, it is
    important that your data file includes a header.');

    // Render the header and notes/lists in a template and use the file link as
    // the value to href attribute of the link to download a template file.
    $supported_file_extensions = implode(', ', $file_extensions);

    $build = [
      '#theme' => 'describe_header_window',
      '#data' => [
        'headers' => $this->headers,
        'file_extensions' => $supported_file_extensions,
        'notes' => $notes,
        'template_file' => $file_link,
      ],
    ];

    return $this->service_Renderer->renderInIsolation($build);
  }

  /**
   * Construct markup for a stage.
   *
   * Each stage will utilize the HTML markup structure below:
   *
   * <div class="tcp-stage-title">Title</div>
   * <div class="tcp-stage-content">Stage Body/Content</div>
   *
   * @param array $stage
   *   An associative array with the following keys.
   *   - stage#: integer, stage number.
   *   - title : string, stage title.
   *   - status: string, class name to correctly style each stage.
   *     CSS class selector name is assigned in the method call to render stage
   *     in form build method above.
   *     - tcp-current-stage: active/current stage
   *     - tcp-completed-stage: completed stage.
   *     - Default to empty string in each stage method: upcoming stage.
   */
  public function createStageAccordion($stage) {
    // Stage number.
    $stage_no = $stage['stage#'];
    // Stage title.
    $title = $stage['title'];
    // Stage status - class name.
    $status = $stage['status'];

    $markup = [
      '#prefix' => $this->t('<div class="tcp-stage-title @stage_status">STAGE @stage#: @title</div>
        <div class="tcp-stage-content @stage_status"><!-- Stage Form Here -->',
        ['@stage_status' => $status, '@stage#' => $stage_no, '@title' => $title]),
      '#suffix' => '</div>',
    ];

    return $markup;
  }

  /**
   * Check if validation failed.
   *
   * @param array $validation_result
   *   An associative array where each element is a validator feedback array.
   *
   * @return bool
   *   A true value indicates a failed or todo status has been detected in the
   *   overall validation result feedback and a false meant all validation
   *   passed and cleared to proceed to next stage.
   */
  public function hasFailedValidation($validation_result = []) {
    $has_fail = FALSE;

    if ($validation_result) {
      foreach ($validation_result as $validator) {
        // Stop share importer on a validation status of todo or fail.
        if ($validator['status'] == 'todo' || $validator['status'] == 'fail') {
          $has_fail = TRUE;
          break;
        }
      }
    }

    return $has_fail;
  }

}
