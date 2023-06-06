<?php

/**
 * @file
 * Construct form to import phenotypes - Share.
 */

namespace Drupal\trpcultivate_phenoshare\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;


use Drupal\trpcultivate_phenoshare\PhenotypesImporter\PhenotypesImporterManager;

/**
 * Class definition TripalCultivatePhenoshareImportForm.
 */
class TripalCultivatePhenoshareImportForm implements FormInterface {
  public function getFormId() {
    return 'tripal_cultivate_phenoshare_importer';
  }


  public function buildForm(array $form, FormStateInterface $form_state) {
    $i = \Drupal::service('trpcultivate_phenoshare.phenotypes_importer');
    $d = $i->getDefinitions();
    dpm($d);
    dpm($i);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }
  
}