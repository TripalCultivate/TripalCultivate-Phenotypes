<?php

/**
 * @file
 * Construct form to manage and configure R Transformation rules.
 */

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class definition TripalCultivatePhenotypesRSettingsForm.
 */
class TripalCultivatePhenotypesRSettingsForm extends ConfigFormBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'trpcultivate_phenotypes_r_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }  

  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return parent::submitForm($form, $form_state);
  }
}