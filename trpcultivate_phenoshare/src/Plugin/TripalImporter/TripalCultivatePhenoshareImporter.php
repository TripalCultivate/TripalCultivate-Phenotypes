<?php

/**
 * @file
 * Tripal Importer Plugin implementation for Tripal Cultivate Phenotypes - Share
 * data file uploader/importer.
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
 *   button_text = "Execute Tripal Job",
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
    'Header 4' => 'Header 4 Description',
    'Header 5' => 'Header 5 Description',
  ];

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    // Always call the parent form to ensure Chado is handled properly.
    //$form = parent::form($form, $form_state);
    
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
    // Cache current stage and id to allow script to reference this value.
    $stage = ($form_state->getValue('next_stage')) 
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


    // Manage importer submit button: Execute Tripal Job.
    // Enable button when it reaches the last stage.
    if ($stage > $total_stages) {
      $storage = $form_state->getStorage();
      $storage['disable_TripalImporter_submit'] = FALSE;
      $form_state->setStorage($storage);
    }
 
    return $form;
  }


  /// Stage accordion methods.

  /**
   * Stage 1: Upload data file callback.
   * 
   * Subsequent stages in accordion will correspond to a public method titled
   * stage + stage number (ie. stage1, or stage2). In each method
   * will define the stage markup using the structure below:
   *
   * <div class="tcp-stage">Title</div>
   * <div>Stage Body/Content</div>
   * 
   * Additional field elements will be wrapped using the field wrapper
   * variable defined in each stage accordion. 
   * 
   * @see accordion stlying (css) and behaviours (js).
   * 
   * @param $form
   *   Drupal form object.
   * @param $form_state
   *   Drupal form state object.
   * @param $stage_status
   *   String, class name to correctly style each stage.
   *   tcp-current-stage: active/current stage
   *   tcp-completed-stage: completed stage. 
   *   Default to empty string.
   */
  public function stage1(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 1,
      'title' => 'Upload Data File'
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = [
      '#prefix' => t('<div class="tcp-stage @stage_status">Stage @stage#: @title</div><div>', 
        ['@stage_status' => $stage_status, '@stage#' => $stage['stage#'], '@title' => $stage['title']]),
      '#suffix' => '</div>'
    ];

    // Other relevant fields here.
    
    // Apply field stage field wrapper to file upload element.
    // For the file upload field to conform to the accordion layout,
    // this override script must be performed.
    $file_upload = $form['file'];
    $form[ $fld_wrapper ]['file'] = $file_upload;
    // Omit old copy so there would not be duplicate file
    // element in the upload data file stage.
    $form['file'] = [];
    ///

    // Other relevant fields here.

    // Stage submit button.
    $form[ $fld_wrapper ]['next_stage'] = [
      '#type' => 'submit',
      '#value' => 'Next Stage',
      '#name' => 'next_stage'
    ];
  }

  /**
   * Stage 2: Validate data. 
   * 
   * @param $form
   *   Drupal form object.
   * @param $form_state
   *   Drupal form state object.
   * @param $stage_status
   *   String, class name to correctly style each stage.
   *   tcp-current-stage: active/current stage
   *   tcp-completed-stage: completed stage. 
   *   Default to empty string.
   */
  public function stage2(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 2,
      'title' => 'Validate Data'
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = [
      '#prefix' => t('<div class="tcp-stage @stage_status">Stage @stage#: @title</div><div>', 
        ['@stage_status' => $stage_status, '@stage#' => $stage['stage#'], '@title' => $stage['title']]),
      '#suffix' => '</div>'
    ];
    
    // Other relevant fields here.
    // Validation result.
    $form[ $fld_wrapper ]['validation_result'] = [
      '#type' => 'inline_template',
      '#theme' => 'result_window',
      '#data' => [],
    ];

    // Stage submit button.
    $form[ $fld_wrapper ]['next_stage'] = [
      '#type' => 'submit',
      '#value' => 'Next Stage',
      '#name' => 'next_stage'
    ];
  }

  /**
   * Stage 3: Describe and save data. 
   * 
   * @param $form
   *   Drupal form object.
   * @param $form_state
   *   Drupal form state object.
   * @param $stage_status
   *   String, class name to correctly style each stage.
   *   tcp-current-stage: active/current stage
   *   tcp-completed-stage: completed stage. 
   *   Default to empty string.
   */
  public function stage3(&$form, $form_state, $stage_status = '') {
    // Describe stage.
    $stage = [
      'stage#' => 3,
      'title' => 'Describe and Save Data'
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = [
      '#prefix' => t('<div class="tcp-stage @stage_status">Stage @stage#: @title</div><div>', 
        ['@stage_status' => $stage_status, '@stage#' => $stage['stage#'], '@title' => $stage['title']]),
      '#suffix' => '</div>'
    ];
    
    // Other relevant fields here.
    $form[ $fld_wrapper ]['field_elements'] = [
      '#markup' => '<p>Stage 3 field elements here</p>'
    ];


    // Stage submit button.
    $form[ $fld_wrapper ]['next_stage'] = [
      '#type' => 'submit',
      '#value' => 'Next Stage',
      '#name' => 'next_stage'
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
    $build = [
      '#theme' => 'importer_header',
      '#data' => [
        'headers' => $this->headers,
        'template_file' => '#'
      ]
    ];

    return \Drupal::service('renderer')->render($build);
  }
}
