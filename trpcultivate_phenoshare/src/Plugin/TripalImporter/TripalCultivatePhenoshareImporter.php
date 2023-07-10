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
 *   button_text = @Translation("Next Step"),
 *   file_upload = False,
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
    // Attach libraries.
    $form['#attached']['library'] = [
      'trpcultivate_phenotypes/trpcultivate-script-pull-window'
    ];

    // Reminder to user about expected phenotypes.
    $phenotypes_minder = 'Phenotypic data should be filtered for outliers and mis-entries before
      being uploaded here. Do not upload data that should not be used in the final analysis for a
      scientific article. Furthermore, data should NOT BE AVERAGED across replicates or site-year.';
    \Drupal::messenger()->addWarning($phenotypes_minder);

    // Describe the stages and help text/guide for this importer.
    // Stage indicators.
    $help_text = t('This is a test help text.');
    $stages = [
      1 => 'Upload Data File',
      2 => 'Validate Data',
      3 => 'Describe and Save Data'
    ];

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
      '#theme' => 'theme-upload_stages',
      '#weight' => -100,
      '#data' => [
        'stages'  => $stages,
        'cur_stage' => $stage,
        'help_text'  => $help_text
      ],
    ];

    // With the determined stage, load form.
    switch($stage) {
      case 1:
        // Upload file stage.
        $form = $this->formStage01($form, $form_state);
        break;

      case 2:
        // Describe traits stage.
        $form = $this->formStage02($form, $form_state);
        break;

      case 3:
        // Save data stage.
        $form = $this->formStage03($form, $form_state);
        break;
    }

    // Submit, next stage or save.
    $btn_text = ($stage < count($stages)) ? 'Next Stage' : 'Save';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t($btn_text),
      '#weight' => 100,
      '#id' => 'tcps-submit-button'
    ];

    return $form;
  }


  ///// Stages - form callback.

  /**
   * Form STAGE 01 - Upload file.
   */
  public function formStage01($form, &$form_state) {
    $form['stage1']['#markup'] = '<h3>Stage 1</h3>';

    return $form;
  }

  /**
   * Form STAGE 02 - Describe traits.
   */
  public function formStage02($form, &$form_state) {
    $form['stage2']['#markup'] = '<h3>Stage 2</h3>';

    return $form;
  }

  /**
   * Form STAGE 03 - Save.
   */
  public function formStage03($form, &$form_state) {
    $form['stage3']['#markup'] = '<h3>Stage 3</h3>';

    return $form;
  }

  /////


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
}
