<?php

/**
 * @file
 * Tripal Importer Plugin implementation for Tripal Cultivate Phenotypes - Share.
 */

namespace Drupal\trpcultivate_phenoshare\Plugin\TripalImporter;

use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\Core\Url;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Tripal Cultivate Phenotypes - Share Importer.
 *
 * Focused on phenotypic data which has already been published or which is ready
 * to be freely shared.
 *
 * @TripalImporter(
 *   id = "trpcultivate-phenotypes-share",
 *   label = @Translation("Tripal Cultivate: Open Science Phenotypic Data"),
 *   description = @Translation("Imports phenotypic data which has already been published or which is ready to be freely shared."),
 *   file_types = {"txt","tsv"},
 *   upload_description = @Translation("Please provide a txt or tsv data file."),
 *   upload_title = @Translation("Phenotypic Data File*"),
 *   use_analysis = False,
 *   use_button = True,
 *   submit_disabled = True,
 *   require_analysis = False,
 *   button_text = "Import",
 *   file_upload = True,
 *   file_load = False,
 *   file_remote = False,
 *   file_required = False,
 *   cardinality = 1,
 *   menu_path = "",
 *   callback = "",
 *   callback_module = "",
 *   callback_path = "",
 * )
 */
class TripalCultivatePhenoshareImporter extends ChadoImporterBase {
  // Reference the current stage with this variable to calibrate
  // each stage accordingly (form, validation, etc.).
  private $current_stage = 'current_stage';

  // Reference the validation result summary values in Drupal storage
  // system using this variable.
  private $validation_result = 'validation_result';

  // Headers required by this importer.
  private $headers = [
    'Header 1' => 'Header 1 Description',
    'Header 2' => 'Header 2 Description',
    'Header 3' => 'Header 3 Description',
  ];
  
  // Service: Make the following services available to all stages.
  // Genus Of Project service.
  protected $service_genusproject;
  // Genus Ontology configuration service.
  protected $service_genusontology;

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    // Set genus project service.
    $this->service_genusproject  = \Drupal::service('trpcultivate_phenotypes.genus_project');
    // Set genus ontology configuration service.
    $this->service_genusontology = \Drupal::service('trpcultivate_phenotypes.genus_ontology');

    // Always call the parent form to ensure Chado is handled properly.
    $form = parent::form($form, $form_state);
    
    // Attach scripts and libraries.
    $form['#attached']['library'] = [
      'trpcultivate_phenotypes/trpcultivate-phenotypes-style-stage-accordion',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-stage-accordion',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-autoselect-field',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-autoselect-project-genus'
    ];

    // This is a reminder to user about expected phenotypic data.
    $phenotypes_minder = t('Phenotypic data should be filtered for outliers and mis-entries before
      being uploaded here. Do not upload data that should not be used in the final analysis for a
      scientific article. Furthermore, data should NOT BE AVERAGED across replicates or site-year.');
    \Drupal::messenger()->addWarning($phenotypes_minder);
    

    // Cacheing of stage number:
    // Cache current stage and id field to allow script to reference this value.

    // Account for failed validation.
    // Refer to Drupal $storage system for validation result values saved.
    $storage = $form_state->getStorage();
    // Full validation result.

    $validation_result = [];
    $has_fail = FALSE;

    if (isset($storage[ $this->validation_result ])) {
      $has_fail = $this->hasFailedValidation($storage[ $this->validation_result ]); 
    }

    $triggering_element = $form_state->getTriggeringElement();
    $valid_triggering_element = [
      'Validate Data File', // Stage 1
      'Check Values', // Stage 2
      'Skip' // Stage 2
    ];

    $stage = (!$has_fail && $form_state->getValue('trigger_element') && in_array($triggering_element['#value'], $valid_triggering_element))
      ? (int) $form_state->getValue( $this->current_stage ) + 1
      : 1;
    
    $form[ $this->current_stage ] = [
      '#type' => 'hidden',
      '#value' => $stage,
      '#attributes' => ['id' => 'tcp-current-stage']
    ];


    // Rendering of Stage:
    // Compose stage array that will become the basis of the stages rendered in 
    // stage accordion layout. Each stage is a method titled stage + stage no (ie. stage1).
    $stage_methods = get_class_methods(get_class($this));
    $total_stages = 0;
  
    foreach ($stage_methods as $method) {
      if (preg_match('/stage([1-9])/', $method, $matches)) {
        if ($stage_no = $matches[1]) {
          // Call method to build stage.
          // Set the status of the stage (current, complete, upcoming).
          
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

    
    // Submit button.
    // Manage importer submit button: Import
    // By default, is disabled in the plugin annotation definition: submit_disabled
    // and enabled one less stage of the total stages.
    if ($stage > ($total_stages - 1)) {
      $storage['disable_TripalImporter_submit'] = FALSE;
      $form_state->setStorage($storage);
    }
 
    return $form;
  }


  /// Stage accordion methods.

  /**
   * Stage 1: Upload data file. Method/Function template.
   * 
   * Subsequent stages in accordion will correspond to a public method titled
   * stage + stage number (ie. stage1, or stage2). In each method
   * will define the stage markup and form render array using the structure below:
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
   * @param $form
   *   Drupal form object.
   * @param $form_state
   *   Drupal form state object.
   * @param $stage_status
   *   String, class name to style each stage with the correct css class
   *   corresponding to a stage - completed, current and upcoming stage.
   */
  public function stage1(&$form, $form_state, $stage_status = '') {
    // Describe stage by providing the stage number and title of the stage.
    // The status key in the stage description array corresponds to the parameter of the method
    // and is determined by the method call to render stage in the form build above.
    $stage = [
      'stage#' => 1,
      'title'  => 'Upload Data File',
      'status' => $stage_status
    ];
    
    // Field wrapper name. All additional elements that go into this stage should
    // use this name to encapsulate into a specific stage in the accordion.
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = $this->createStageAccordion($stage);
    
    // Validation result.
    $storage = $form_state->getStorage();
    // Full validation result.
    if (isset($storage[ $this->validation_result ])) {
      $validation_result = $storage[ $this->validation_result ];

      $form[ $fld_wrapper ]['validation_result'] = [
        '#type' => 'inline_template',
        '#theme' => 'result_window',
        '#data' => [
          'validation_result' => $validation_result
        ],
        '#weight' => -100
      ];
    }

    // Other relevant fields here.
    // Select experiment, Genus field will reflect the genus project is set to.
    $form[ $fld_wrapper ]['project'] = [
      '#title' => t('Project/Experiment'),
      '#type' => 'textfield',
      '#weight' => -100,  
      '#required' => TRUE,   
      '#description' => t('Type in the experiment or project title your data is specific to.'), 
      '#attributes' => ['placeholder' => 'Project/Experiment Name', 'class' => ['tcp-autocomplete']],
      '#autocomplete_route_name' => 'tripal_chado.project_autocomplete',
      '#autocomplete_route_parameters' => ['type_id' => 0, 'count' => 5],
      
      // Used by script to pre-select genus paired to project entered.
      '#id' => 'trpcultivate-fld-project'
    ];
    
    // Field Genus:
    // Prepare select options with only active genus.
    $all_genus = $this->service_genusontology->getConfiguredGenusList();
    $active_genus = array_combine($all_genus, $all_genus);

    $form[ $fld_wrapper ]['genus'] = [
      '#title' => t('Genus'),
      '#type' => 'select',
      '#options' => $active_genus,   
      '#weight' => -90,
      '#required' => TRUE,
      '#description' => t('Select Genus. When experiment or project has genus set, a value will be selected.'),
   
      // States.
      '#states' => [
        'disabled' => [
          ':input[name="project"]' => ['filled' => FALSE],
        ]
      ],
      
      // Used by script to pre-select when project was supplied. 
      '#id' => 'trpcultivate-fld-genus'
    ]; 

    // Apply field stage field wrapper to file upload element.
    // For the file upload field to conform to the accordion layout,
    // this override script must be performed.
    $file_upload = $form['file'];
    $form[ $fld_wrapper ]['file'] = $file_upload;
    // Omit old copy so there would not be duplicate file
    // element in the upload data file stage.
    $form['file'] = [];

    // Other relevant fields here.
    
    // This importer does not support using file sources from existing field and local/external source.
    $form[ $fld_wrapper ]['file']['file_upload_existing'] = ['#access' => 'FALSE'];
    $form[ $fld_wrapper ]['file']['file_local'] = ['#type' => 'hidden'];

    // Stage submit button.
    $form[ $fld_wrapper ]['validate_stage'] = [
      '#type' => 'submit',
      '#value' => 'Validate Data File',
      '#name' => 'trigger_element'
    ];
  }

  /**
   * Stage 2: Validate data. 
   *
   * @see stage 1 method template.
   *  
   * @param $form
   *   Drupal form object.
   * @param $form_state
   *   Drupal form state object.
   * @param $stage_status
   *   String, class name to style each stage with the correct css class
   *   corresponding to a stage - completed, current and upcoming stage. 
   */
  public function stage2(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 2,
      'title'  => 'Describe Traits',
      'status' => $stage_status
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = $this->createStageAccordion($stage);
    
    // Other relevant fields here.
    $form[ $fld_wrapper ]['field_elements'] = [
      '#markup' => '<p>Stage 2 field elements here</p>'
    ];
 
    // Stage submit button.
    $form[ $fld_wrapper ]['validate_stage'] = [
      '#type' => 'submit',
      '#value' => 'Check Values',
      '#name' => 'trigger_element'
    ];

    $form[ $fld_wrapper ]['skip_stage'] = [
      '#type' => 'submit',
      '#value' => 'Skip',
      '#name' => 'trigger_element'
    ];
  }

  /**
   * Stage 3: Describe and save data. 
   * 
   * @see stage 1 method template.
   *  
   * @param $form
   *   Drupal form object.
   * @param $form_state
   *   Drupal form state object.
   * @param $stage_status
   *   String, class name to style each stage with the correct css class
   *   corresponding to a stage - completed, current and upcoming stage.
   */
  public function stage3(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 3,
      'title'  => 'Review Data',
      'status' => $stage_status
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = $this->createStageAccordion($stage);
    
    // Other relevant fields here.
    $form[ $fld_wrapper ]['field_elements'] = [
      '#markup' => '<p>Stage 3 summary table here</p>'
    ];
  }

  // End stage accordion methods.

  ///


  /**
   * {@inheritdoc}
   */
  public function formSubmit($form, &$form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function formValidate($form, &$form_state) {
    $form_state_values = $form_state->getValues();
    
    // Current stage.
    // Get the cached stage value from the formstate values and perform validation
    // when it is set to a value. Starting with index 1 - as stage 1, index 2 as stage 2
    // and so on to the last stage.

    // NOTE: not all stages require a validation and a subsequent condition will
    // target a specific stage to perform pertinent validation.

    // NOTE: $this->current_stage is the name of the field in the formstate that holds
    // the current stage value (cacheing of stage no.). See $current_stage property.
    if (array_key_exists($this->current_stage, $form_state_values)) {      
      $stage = $form_state_values[ $this->current_stage ];

      if ($stage >= 1) {
        // Validate Stage 1.
        
        // Counter, count number of validators that failed.
        $failed_validator = 0;

        // Call validator manager service.
        $manager = \Drupal::service('plugin.manager.trpcultivate_validator');
       
        // All values will be accessible to every instance of the validator Plugin.
        $project = $form_state_values['project'];
        $genus = $form_state_values['genus'];
        $file = $form_state_values['file_upload'];
        // @TODO: load headers in validators that require reference to the headers.
        $headers = [];

        if ($stage == 1) {
          $scopes = ['PROJECT', 'GENUS'];
          
          foreach($scopes as $scope) {
            // Create instance of the scope-specific plugin and perform validation.
            $validator = $manager->getValidatorIdWithScope($scope);
            $instance = $manager->createInstance($validator);
            
            // Set other validation level to upcoming/todo if a validation failed.
            $skip = ($failed_validator > 0) ? 1 : 0;
            
            // Load values.
            $instance->loadAssets($project, $genus, $file, $headers, $skip);
            
            // Perform Project Level validation.
            $validation[ $scope ] = $instance->validate();

            // Save validation result.
            $storage = $form_state->getStorage();
            $storage[ $this->validation_result ] = $validation;
            $form_state->setStorage($storage);

            // Inspect for any failed validation to halt the importer.
            if ($validation[ $scope ]['status'] == 'fail') {
              $failed_validator++;
            }
          }

          if ($failed_validator > 0) {
            // There are issues in the submission and are detailed in the validation result window.
            // Prevent this form from submitting and reload form with all the validation errors 
            // in the storage system.
            $form_state->setRebuild(TRUE);
          }
        }
      }
    }
  }

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
   * {@inheritdoc}
   */
  public function describeUploadFileFormat() {
    // A template file has been generated and is ready for download.
    $importer_id = $this->pluginDefinition['id'];
    $column_headers = array_keys($this->headers);

    $file_link = \Drupal::service('trpcultivate_phenotypes.template_generator')
      ->generateFile($importer_id, $column_headers);
    
    // Render the header notes/lists template and use the file link as 
    // the value to href attribute of the link to download a template file.
    $build = [
      '#theme' => 'importer_header',
      '#data' => [
        'headers' => $this->headers,
        'template_file' => $file_link
      ]
    ];

    return \Drupal::service('renderer')->render($build);
  }

  /**
   * Construct markup for a stage with title and a corresponding content area
   * that will be rendered as a stage in the accordion. Each stage will utilize
   * the structure below:
   * 
   * <div class="tcp-stage-title">Title</div>
   * <div class="tcp-stage-content">Stage Body/Content</div>
   *
   * @param $stage
   *   An associative array with the following keys.
   *   - stage#: integer, stage number.
   *   - title : string, stage title.
   *   - status: string, class name to correctly style each stage. 
   *     Class is assigned in the method call to render stage in form build method above.
   *     - tcp-current-stage: active/current stage
   *     - tcp-completed-stage: completed stage. 
   *     - Default to empty string in each stage method: upcoming stage.
   */
  public function createStageAccordion($stage) {
    // Stage number.
    $stage_no = $stage['stage#'];
    // Stage title.
    $title   = $stage['title'];
    // Stage status - class name.
    $status = $stage['status'];

    $markup = [
      '#prefix' => t('<div class="tcp-stage-title @stage_status">STAGE @stage#: @title</div>
        <div class="tcp-stage-content @stage_status"><!-- Stage Form Here -->', 
        ['@stage_status' => $status, '@stage#' => $stage_no, '@title' => $title]),
      '#suffix' => '</div>'
    ];
    
    return $markup;
  }

  /**
   * Check if validation failed.
   * 
   * @param $validation_result
   *   An associative array where each element is the validation summary of each 
   *   level (PROJECT, GENUS, FILE etc.).
   *   [level => [
   *       'status' => 'fail', // pass, todo
   *       'detail' => 'String, describing more details about the failed validation'
   *     ],
   *    ...
   *   ]
   */  
  public function hasFailedValidation($validation_result = []) {
    $has_fail = FALSE;

    if ($validation_result) {
      foreach($validation_result as $validator) {
        // Inspect the validation result summary and if any one level
        // failed validation should suffice to stop import.
        if ($validator['status'] == 'fail') {
          $has_fail = TRUE;
          break;
        }
      }
    }

    return $has_fail;
  }
}