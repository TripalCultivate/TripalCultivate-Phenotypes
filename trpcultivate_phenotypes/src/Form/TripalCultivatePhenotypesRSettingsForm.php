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
      '#default_value' => implode(',', $config->get($config_r . 'words')),
      '#description' => $this->t('Separate each word entry with a comma character. 
        Words must at least be 2 characters or more.'),
      '#required' => TRUE,
    ];

    $form['chars'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of special characters to remove'),
      '#default_value' => implode(',', $config->get($config_r . 'chars')),
      '#description' => $this->t('Separate each character entry with a comma character. 
        Characters must be 1 character only.'),
      '#required' => TRUE,
    ];

    $form['replace'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Match word and replace with'),
      '#default_value' => implode(',', $config->get($config_r . 'replace')),
      '#description' => $this->t('Separate match and replace pairs with a comma character. 
        Use match = replace pattern for each combination.'),
      '#required' => TRUE,
    ];
    
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Words: words must be at least 2 characters long.
    if ($words = $form_state->getValue('words')) {
      foreach(explode(',', $words) as $word) {
        if (empty($word) || !preg_match('/\w{2,}/', $word)) {
          $form_state->setErrorByName('word', $this->t('Could not save rule.
            Empty or invalid word: @word added to the list.', ['@word' => $word]));
        }
      }
    }
    
    // Special characters: 1 character only, excluding comma symbol since
    // it is used as delimiter.
    if ($chars = $form_state->getValue('chars')) {
      foreach(explode(',', $chars) as $char) {
        if (empty($char) || $char == ',' || !preg_match('/\W{1}/', $char)) {
          $form_state->setErrorByName('chars', $this->t('Could not save rule.
            Empty or invalid special characters: @char added to the list.', ['@char' => $char]));        
        }
      }
    }

    // Match and replace: match = replace pattern, excluding comma symbol since
    // it is used as delimiter.
    if ($replaces = $form_state->getValue('replace')) {
      foreach(explode(',', $replaces) as $replace) {
        if (empty($replace) || !preg_match('/^[^,]+\s{1}[=]\s{1}[^,]+$/', $replace)) {
          $form_state->setErrorByName('replace', $this->t('Could not save rule. 
            Empty or invalid match and replace: @replace added to the list.', ['@replace' => $replace]));        
        }  
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_r = 'trpcultivate.phenotypes.r_config.';
    
    // Configuration in field are string, convert to array to match 
    // configuration schema data type.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set($config_r . 'words', explode(',', $form_state->getValue('words')))
      ->set($config_r . 'chars', explode(',', $form_state->getValue('chars')))
      ->set($config_r . 'replace', explode(',', $form_state->getValue('replace')))
      ->save();

    return parent::submitForm($form, $form_state);
  }
}