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
  
  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $config_r = 'trpcultivate.phenotypes.r_config.';

    $form['description'] = [
      '#markup' => $this->t('Tripal Cultivate Phenotypes supports R language for statistical computing by providing
      syntactically valid version of trait or values. Use R Transformation Rules configuration page to define 
      standard transformation rules to apply to trait or string when converting to R version.')
    ];

    $form['words'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of words to remove'),
      '#default_value' => $config->get($config_r . 'words'),
      '#description' => $this->t('Separate each word entry with a comma character'),
      '#required' => TRUE,
    ];

    $form['chars'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of special characters to remove'),
      '#default_value' => $config->get($config_r . 'chars'),
      '#description' => $this->t('Separate each character entry with a comma character'),
      '#required' => TRUE,
    ];

    $form['replace'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Match word and replace with'),
      '#default_value' => $config->get($config_r . 'replace'),
      '#description' => $this->t('Separate match and replace pairs with a comma character'),
      '#required' => TRUE,
    ];

    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_r = 'trpcultivate.phenotypes.r_config.';

    $this->configFactory->getEditable(static::SETTINGS)
      ->set($config_r . 'words', $form_state->getValue('words'))
      ->set($config_r . 'chars', $form_state->getValue('chars'))
      ->set($config_r . 'replace', $form_state->getValue('replace'))
      ->save();

    return parent::submitForm($form, $form_state);
  }
}