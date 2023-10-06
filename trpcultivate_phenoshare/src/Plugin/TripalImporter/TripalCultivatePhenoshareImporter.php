<?php

/**
 * @file
 * Tripal Importer Plugin implementation for Tripal Cultivate Phenotypes - Share.
 */

namespace Drupal\trpcultivate_phenoshare\Plugin\TripalImporter;

use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;

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

  // Headers required by this importer.
  private $headers = [
    'Header 1' => 'Header 1 Description',
    'Header 2' => 'Header 2 Description',
    'Header 3' => 'Header 3 Description',
  ];

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    // Always call the parent form to ensure Chado is handled properly.
    $form = parent::form($form, $form_state);
    
    // Attach scripts and libraries.
    $form['#attached']['library'] = [
      'trpcultivate_phenotypes/trpcultivate-phenotypes-style-stage-accordion',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-stage-accordion'
    ];

    // This is a reminder to user about expected phenotypic data.
    $phenotypes_minder = t('Phenotypic data should be filtered for outliers and mis-entries before
      being uploaded here. Do not upload data that should not be used in the final analysis for a
      scientific article. Furthermore, data should NOT BE AVERAGED across replicates or site-year.');
    \Drupal::messenger()->addWarning($phenotypes_minder);
    

    // Cacheing of stage number:
    // Cache current stage and id field to allow script to reference this value.
    $triggering_element = $form_state->getTriggeringElement();
    $valid_triggering_element = [
      'Validate Data File', // Stage 1
      'Check Values', // Stage 2
      'Skip' // Stage 2
    ];

    $stage = ($form_state->getValue('trigger_element') && in_array($triggering_element['#value'], $valid_triggering_element))
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
      $storage = $form_state->getStorage();
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
    $form[ $fld_wrapper ]['validation_result'] = [
      '#type' => 'inline_template',
      '#theme' => 'result_window',
      '#data' => [],
      '#weight' => -100
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
    // @TODO: resolve template_file download, either:
    // 1. via service, programmatically create a temp file for download based on $headers property defined.
    // 2. a created file, with pre-configured headers in a specific location (ie. file_template/).

    $build = [
      '#theme' => 'importer_header',
      '#data' => [
        'headers' => $this->headers,
        'template_file' => '#'
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
}