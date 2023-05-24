<?php

/**
 * @file
 * Construct form to manage and configure Ontology terms.
 */

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    $ontology_service = \Drupal::service('trpcultivate_phenotypes.ontology');
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
      '#open' => TRUE,
    ];
    
    $form['term_fieldset']['genus'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Genus')],
      '#description' => $this->t('This term indicates that a given property is the associated "genus".
        For example, this module save experiments producing phenotypic data as projects and the
        organism that experiment assays is indicated by adding a "genus" property to it.')
    ]; 

    $form['term_fieldset']['method'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Method')],
      '#description' => $this->t('This term describes the relationship between your trait vocabulary and
        the method with which the data was taken')
    ];

    $form['term_fieldset']['unit'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Unit')], 
      '#description' => $this->t('This term describes the relationship between your method and the
        unit with which it was measured.')
    ];

    $form['term_fieldset']['related'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Related')],
      '#description' => $this->t('This term describes the relationship between your trait vocabulary
        term and the chosen equivalent crop ontology term.')
    ];

    $form['term_fieldset']['collector'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Data Collector')],
      '#description' => $this->t('Metadata tagged with this term indicates the name of the person who 
        collected the phenotypic measurement.')
    ];

    $form['term_fieldset']['year'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Year')],
      '#description' => $this->t('Metadata tagged with this term indicates the year in which a phenotypic
        datapoint was collected.') 
    ];

    $form['term_fieldset']['location'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Location')],
      '#description' => $this->t('Metadata tagged with this term indicates the physical location of
        the environment giving rise to the phenotype.')
    ];

    $form['term_fieldset']['replicate'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Replicate')],
      '#description' => $this->t('Metadata tagged with this term indicates the unique identifier for
        the replicate the current datapoint is part of.')
    ];

    $form['term_fieldset']['plot'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Plot')],
      '#description' => $this->t('Metadata tagged with this term indicates the plot number.')
    ];

    $form['term_fieldset']['entry'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Entry')],
      '#description' => $this->t('Metadata tagged with this term indicates the entry number.')
    ];

    $form['term_fieldset']['name'] = [
      '#type' => 'textfield',
      #'#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      #'#autocomplete_route_parameters' => ['count' => 5],
      '#attributes' => ['placeholder' => $this->t('Name')],
      '#description' => $this->t('Metadata tagged with this term indicates the name of 
        of the germplasm or line.')
    ];
  




    
    // Context information window.
    $form['wrapper'] = [
      '#type' => 'container',
    ];

    $form['wrapper']['help'] = [
      '#type' => 'item',
      '#markup' => '<h4>Modules require that phenotypic traits be part of a controlled vocabulary 
        <div style="position: absolute; top: 0; right: 0">Need Help?</div></h4>'
    ];
    



    '
    Trait Vocabulary:
    A container of terms where each term is a phenotypic trait that canb be measured in your species
    of interest. This controlled vocabulary shoud be specific to a given genus and each term will become
    a trait page on your Tripal site. If you do not already have a trait vocabulary, you can create it
    here and add terms upfront and/or automatically on upload of phenotypic data.';

    '
    Associated Database:
    Chado requires a "database" container to be associated with all controlled vocabularies. Please
    select the "database" container you would like to be associated with your trait. If needed, create one here.
    ';

    '
    Crop Ontology:
    Our experience with breeders has led us to recommend using the trait name your breeder(s) already use
    in the Trait Vocabulary and then linking them to a more generic crop ontology such as those provided
    by cropontology.org to facilitate sharing. If you decide to go this route, you an set species specific
    crop ontology here and on upload, suitable terms will be suggested based on pattern matching.
    ';

    




    $form['cvdbon_title'] = [

    ];





    $form['cvdbon_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Ontology Terms - Trait, Method and Unit'),
      '#open' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $m = $form_state->getValue('genus');
    $x = ChadoCVTermAutocompleteController::getCVtermId($m);
    dpm($x);

    return parent::submitForm($form, $form_state);
  }
}