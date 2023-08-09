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
 *   use_button = False,
 *   require_analysis = False,
 *   button_text = "",
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

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    // Always call the parent form to ensure Chado is handled properly.
    $form = parent::form($form, $form_state);
    
    // Attach scripts and libraries.
    $form['#attached']['library'] = [
      'trpcultivate_phenotypes/trpcultivate-phenotypes-style-accordion',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-accordion'
    ];

    // Reminder to user about expected phenotypes.
    $phenotypes_minder = t('Phenotypic data should be filtered for outliers and mis-entries before
      being uploaded here. Do not upload data that should not be used in the final analysis for a
      scientific article. Furthermore, data should NOT BE AVERAGED across replicates or site-year.');
    \Drupal::messenger()->addWarning($phenotypes_minder);

    // Get all methods that implements a stage accordion.
    // This will become the basis of the stages rendered in accordion layout.
    $importer_methods = get_class_methods(get_class($this));
    $stages = [];
    foreach ($importer_methods as $method) {
      if (preg_match('/stage([1-9])/', $method, $matches)) {
        if ($stage_no = $matches[1]) {
          $stages[ $stage_no ] = $method;
        } 
      }
    }

    // Determine the stage.
    $current_stage = 'current_page';

    // If form is submitted, update the current stage and load
    // corresponding form field and controls for the stage.
    if ($form_state->getUserInput()) {
      // Retrieve the the last stage saved in the form_state and
      // increment by 1 to load the next stage.
      $cache_stage = $form_state->get($current_stage);
      $stage = (int) $cache_stage + 1;

      // Return to stage 1 after final step.
      $stage = ($stage > count($stages)) ? 1 : $stage;
    }
    else {
      // On initial load of the importer set the stage to
      // stage 1 of the upload/import process.
      $stage = 1;
    }

    // Save the current stage in a $form_state variable.
    $form_state->set($current_stage, $stage);

    // Render the stage details in the header section of the form.
    // Set with the lowest weight to embed in the header of the form.
    $form['stage_indicator'] = [
      '#type' => 'inline_template',
      '#theme' => 'theme-upload-stages',
      '#weight' => -100,
      '#data' => [
        'stages'  => $stages,
        'cur_stage' => $stage,
      ],
    ];

    // Outline the column headers plus description and theme the 
    // list as an ordered list. Include in this area a link to download
    // a pre-configured data collection file template.
    $column_headers = [
      'Header 1: Header 1 Description',
      'Header 2: Header 2 Description',
      'Header 3: Header 3 Description',
      'Header 4: Header 4 Description',
      'Header 5: Header 5 Description',
    ];

    $form['importer_notes'] = [
      '#theme' => 'item_list',
      '#title' => t('This should be a tab-separated file with the following columns'),
      '#list_type' => 'ol',
      '#items' => $column_headers,
      '#attributes' => ['id' => 'tcp-importer-notes'],
    ];
    
    // Template file expected in module directory/templates/data-file-template.tsv.
    $form['importer_file_template'] = [
      '#markup' => '<a href="#">This is a link to a template file</a>'
    ];

    
    // Render stage accordion callback.
    foreach($stages as $stage_callback) {
      // Render Stage Accordion.
      $this->$stage_callback($form, $form_state);
    }

    return $form;
  }


  /// Stage accordion callback.

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
   */
  public function stage1(&$form, $form_state) {
    // Describe stage.
    $stage = [
      'stage#' => 1,
      'title' => 'Upload Data File'
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = [
      '#prefix' => t('<div class="tcp-stage">Stage @stage#: @title</div><div>', 
        ['@stage#' => $stage['stage#'], '@title' => $stage['title']]),
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
  }

  /**
   * Stage 2: Validate data. 
   */
  public function stage2(&$form, $form_state) {
    // Describe stage.
    $stage = [
      'stage#' => 2,
      'title' => 'Validate Data'
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = [
      '#prefix' => t('<div class="tcp-stage">Stage @stage#: @title</div><div>', 
        ['@stage#' => $stage['stage#'], '@title' => $stage['title']]),
      '#suffix' => '</div>'
    ];
    
    // Other relevant fields here.
    $form[ $fld_wrapper ]['field_elements'] = [
      '#markup' => 'Stage 2 field elements here'
    ];
  }

  /**
   * Stage 3: Describe and save data. 
   */
  public function stage3(&$form, $form_state) {
    // Describe stage.
    $stage = [
      'stage#' => 3,
      'title' => 'Describe and Save Data'
    ];
    
    $fld_wrapper = 'accordion_stage' . $stage['stage#'];
    $form[ $fld_wrapper ] = [
      '#prefix' => t('<div class="tcp-stage">Stage @stage#: @title</div><div>', 
        ['@stage#' => $stage['stage#'], '@title' => $stage['title']]),
      '#suffix' => '</div>'
    ];
    
    // Other relevant fields here.
    $form[ $fld_wrapper ]['field_elements'] = [
      '#markup' => 'Stage 3 field elements here'
    ];
  }

  // End stage accordion callback.

  ///


  /**
   * {@inheritdoc}
   */
  public function formSubmit($form, &$form_state) {
    $form_state->setRebuild(TRUE);
    dpm($form_state);
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
}
