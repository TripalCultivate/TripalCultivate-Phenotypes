<?php

namespace Drupal\trpcultivate_phenoshare\Plugin\TripalImporter;

use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\tripal\TripalVocabTerms\TripalTerm;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;

use Drupal\trpcultivate_phenotypes\Controller\TripalCultivatePhenotypesExperimentAutocompletController;

/**
 * GFF3 Importer implementation of the TripalImporterBase.
 *
 * @TripalImporter(
 *   id = "trpcultivate-phenotypes-share",
 *   label = @Translation("Phenotypes Share - Data Importer"),
 *   description = @Translation("Loads Phenotypic Data Importer."),
 *   file_types = {"txt","tsv"},
 *   upload_description = @Translation("Please provide a txt or tsv data file."),
 *   upload_title = @Translation("Phenotypes Data File*"),
 *   use_analysis = False,
 *   require_analysis = False,
 *   button_text = @Translation("Next Step"),
 *   file_upload = True,
 *   file_load = False,
 *   file_remote = False,
 *   file_required = True,
 *   cardinality = 1,
 *   menu_path = "",
 *   callback = "",
 *   callback_module = "",
 *   callback_path = "",
 * )
 */
class TripalCultivatePhenoshareImporter extends ChadoImporterBase {
  /**
   * The name of this loader.  This name will be presented to the site
   * user.
   */
  public static $name = 'Tripal Cultivate Phenotypes Share Data Importer';

  /**
   * The machine name for this loader. This name will be used to construct
   * the URL for the loader.
   */
  public static $machine_name = 'trpcultivate_phenotypes_share';

  /**
   * A brief description for this loader.  This description will be
   * presented to the site user.
   */
  public static $description = 'Loads Phenotypic Data Importer';

  /**
   * An array containing the extensions of allowed file types.
   */
  public static $file_types = ['txt', 'tsv'];

  /**
   * Provides information to the user about the file upload.  Typically this
   * may include a description of the file types allowed.
   */
  public static $upload_description = 'Please provide a txt or tsv data file.';

  /**
   * The title that should appear above the upload button.
   */
  public static $upload_title = 'Phenotypes Data File';

  /**
   * Text that should appear on the button at the bottom of the importer
   * form.
   */
  public static $button_text = 'Import File';

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    $chado = \Drupal::service('tripal_chado.database');
    // Always call the parent form to ensure Chado is handled properly.
    $form = parent::form($form, $form_state);

    // Select experiment, Genus field will reflect the genus project 
    // is set to.
    $form['experiment'] = [
      '#title' => t('Experiment'),
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'trpcultivate_phenotypes.autocomplete_experiment',
      '#autocomplete_route_parameters' => ['count' => 5],
      '#weight' => -1,
      '#required' => TRUE,
      '#attributes' => ['placeholder' => 'Experiment/Project Name']
    ];

    
    // get the list of organisms.
    $organisms = chado_get_organism_select_options(FALSE, TRUE);

    $form['organism_id'] = [
      '#title' => t('Genus'),
      '#type' => 'select',
      '#description' => t('Select a Genus. When an experiment or project has genus set, a value will be selected.'),
      '#required' => TRUE,
      '#options' => $organisms,
      '#empty_option' => t('- Select -'),
      '#weight' => 0,
    ];

    // This will ensure that file importer + submit button are rendered past
    // other form field elements. Button is set to weight #10.
    $form['file']['#weight'] = 9;
    $form['button']['#weight'] = 10;

    // Exclude the database option (advanced options) since phenotypes
    // schema is always installed in chado database (default schema).
    $form['advanced']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritDoc}
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
  public function formSubmit($form, &$form_state) {

  }
}
