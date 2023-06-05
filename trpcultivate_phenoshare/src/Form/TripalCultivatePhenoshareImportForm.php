<?php

/**
 * @file
 * Construct form to import phenotypes - Share.
 */

namespace Drupal\trpcultivate_phenoshare\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class definition TripalCultivatePhenoshareImportForm.
 */
class TripalCultivatePhenoshareImportForm extends FormBase {
  $importer = \Drupal::service('tripal.importer');

  
}