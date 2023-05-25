<?php

/**
 * @file
 * Construct form to manage and configure Ontology terms.
 */

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;

/**
 * Class definition TripalCultivatePhenotypesOntologySettingsForm.
 */
class TripalCultivatePhenotypesOntologySettingsForm extends ConfigFormBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'trpcultivate_phenotypes_ontology_settings_form';
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
    // TERMS.


    // @TODO: add check if there is data to disable all term field elements
    // preventing user from changing values.

    // @TODO: Tripal add vocabulary not available, mark with # sign in
    // ontology instructions/guide.

  

    // Attach library.
    $form['#attached']['library'][] = 'trpcultivate_phenotypes/autoselect-field';
    // Term service.
    $term_service = \Drupal::service('trpcultivate_phenotypes.terms');
    $config = $this->config(static::SETTINGS);
    
    // This is a warning about the watermark being able to bypass with
    // advanced knowledged of HTML/CSS.
    $warning = $this->t('Once Phenotypic Data has been uploaded for a genus, these vocabularies CANNOT be changed!
      Please take the time to read the descriptions below and ensure to select terms applicable in your research.');
    $this->messenger()->addWarning($warning, $repeat = FALSE);

    $form['description'] = [
      '#markup' => $this->t('Tripal Cultivate Phenotypes require that phenotypic traits be housed in 
        a Controlled Vocabulary (CV) and pre-define terms used throughout the various processes. 
        Use Ontology Terms configuration to setup terms that best support your data.')
    ];

    $form['term_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Controlled Vocabulary Terms - Property/Relationship Types and Measurement Metadata'),
      '#description' => $this->t('Chado uses controlled vocabularies extensively to allow for flexible storing of data.
        As such, this module supports that flexibility to ensure that you have the ability to choose the terms that
        best support your data. <br /><br /> 
        
        We have helfully selected what we think are the best ontology terms below. Thus the following configuration is
        completely optional, although it is highly recommended to review our choices.'),
      '#open' => FALSE,
    ];
    
    // Field description keyed by term configuration variable name.
    // Order as each appears in the form.
    $field_description = [
      'genus'  => 'This term indicates that a given property is the associated "genus". For example, this module save experiments producing phenotypic data as projects and the organism that experiment assays is indicated by adding a "genus" property to it.',
      'method'  => 'This term describes the relationship between your trait vocabulary and the method with which the data was taken.',
      'unit'     => 'This term describes the relationship between your method and the unit with which it was measured.',
      'related'   => 'This term describes the relationship between your trait vocabulary term and the chosen equivalent crop ontology term.',
      'collector' => 'Metadata tagged with this term indicates the name of the person who collected the phenotypic measurement.',
      'year'      => 'Metadata tagged with this term indicates the year in which a phenotypic datapoint was collected.',
      'location'  => 'Metadata tagged with this term indicates the physical location of the environment giving rise to the phenotype.',
      'replicate' => 'Metadata tagged with this term indicates the unique identifier for the replicate the current datapoint is part of.',
      'plot'     => 'Metadata tagged with this term indicates the plot number.',
      'entry'   => 'Metadata tagged with this term indicates the entry number.',
      'name'   => 'Metadata tagged with this term indicates the name of the germplasm or line.',
    ];
    
    // Get term - term configuration variable mapping details.
    $terms = $term_service->mapDefaultTermToConfig();

    // Render each term as autocomplete field element.
    foreach($terms as $term => $config) {
      // Field description.
      $describe = $this->t($field_description[ $config ]);
      // Field placeholder and title text.
      $placeholder = $title = $this->t(ucfirst($term));
      // Field default value.
      $config_value = $term_service->getTermConfigValue($term);
      $term_rec = $term_service->getTerm($config_value);
      $default_value = $term_rec['format'];
      
      // Field render array.
      $form['term_fieldset'][ $config ] = [
        '#type' => 'textfield',
        '#title' => $title,
        '#attributes' => ['class' => ['tcp-autocomplete'], 'placeholder' => $placeholder],
        '#description' => $describe,
        '#default_value' => $default_value,

        '#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
        '#autocomplete_route_parameters' => ['count' => 5],
      ]; 
    }


    // DB, CV and ONTOLOGY:
    

    $form['ontology_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Trait Ontologies - Trait Vocabulary, Associated Database and Crop Ontology'),
      '#open' => TRUE,
    ];
   
    // Instructions.
    $link = Link::fromTextAndUrl('cropontology.org', Url::fromUri('http://www.cropontology.org'));

    $form['ontology_fieldset']['guide'] = [
      '#type' => 'inline_template',
      '#theme' => 'ontology_instructions',
      '#link' => [
        'addvocab' => '#', // Not currently available.
        'cropontology' => $link->toRenderable()
      ],
    ];

    $form['ontology_fieldset']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'height: 300px; overflow-y: scroll;'
      ]
    ];
    
    $form['ontology_fieldset']['wrapper']['fields'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('<b>Genus</b>'), 
        $this->t('Trait Vocabularies'), 
        $this->t('Associated Database'), 
        $this->t('Crop Ontology')
      ],
    ];

    for($i = 0; $i < 5; $i++) {
      $form['ontology_fieldset']['wrapper']['fields'][$i]['name'] = [
        '#type' => 'select'
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {    
    // Validate each term exits and field is not empty.
    
    // Term service.
    $term_service = \Drupal::service('trpcultivate_phenotypes.terms');
    
    // Get term - term configuration variable mapping details.
    $terms = $term_service->mapDefaultTermToConfig();
    
    foreach($terms as $term => $config) {
      if ($fld = $form_state->getValue($config)) {
        // Has a value, test term exists.
        $id = ChadoCVTermAutocompleteController::getCVtermId($fld);    

        if (!$id) {
          $form_state->setErrorByName($config, $this->t('Error: could not save form. Required field
          @fld value does not exist.', ['@fld' => ucfirst($config)]));
        }
      }
      else {
        // Field is empty.
        $form_state->setErrorByName($config, $this->t('Error: could not save form. Required field
          @fld is empty.', ['@fld' => ucfirst($config)]));
      }
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Term service.
    $term_service = \Drupal::service('trpcultivate_phenotypes.terms');
    
    // Get term - term configuration variable mapping details.
    $terms = $term_service->mapDefaultTermToConfig();
    
    // Configuration.
    $sysvar_terms = 'trpcultivate.phenotypes.ontology.terms.';
    $configuration = $this->configFactory->getEditable(static::SETTINGS);

    foreach($terms as $term => $config) {
      $fld = $form_state->getValue($config);
      $id = ChadoCVTermAutocompleteController::getCVtermId($fld);    
    
      $configuration
        ->set($sysvar_terms . $config, $id);
    }  
    
    $configuration
      ->save();

    return parent::submitForm($form, $form_state);
  }
}