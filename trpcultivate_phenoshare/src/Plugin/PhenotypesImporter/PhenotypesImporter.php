<?php

namespace Drupal\trpcultivate_phenoshare\Plugin\PhenotypesImporter;

use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\tripal\TripalVocabTerms\TripalTerm;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;

class PhenotypesImporter extends ChadoImporterBase {
  public static $name = 'Phenotypes Importer';

  public function form($form, $form_state) {
    $form = parent::form($form, $form_state);

    $form['abc'] = [
      '#markup' => '<h3>Hello Form</h3>'
    ];

    return $form;
  }

  public function formValidate($form, $form_state) {
    
  }

  public function run() {

  }
}