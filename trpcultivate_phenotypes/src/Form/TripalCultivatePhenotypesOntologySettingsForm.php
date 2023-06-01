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
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesDatabaseService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesVocabularyService;


/**
 * Class definition TripalCultivatePhenotypesOntologySettingsForm.
 */
class TripalCultivatePhenotypesOntologySettingsForm extends ConfigFormBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';
  
  /**
   * Services.
   */
  protected $srv_database;
  protected $srv_ontology;
  protected $srv_terms;
  protected $srv_vocabulary;

  /**
   * Configuration variable cvdbon (ontology).
   */
  private $sysvar_ontology;
  
  /**
   * Class constuctor.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
    TripalCultivatePhenotypesDatabaseService $database,
    TripalCultivatePhenotypesOntologyService $ontology,
    TripalCultivatePhenotypesTermsService $terms, 
    TripalCultivatePhenotypesVocabularyService $vocabulary) {
    
    parent::__construct($config_factory);
  
    $this->srv_database = $database;
    $this->srv_ontology = $ontology;
    $this->srv_terms = $terms;
    $this->srv_vocabulary = $vocabulary;

    $this->sysvar_ontology = 'trpcultivate.phenotypes.ontology';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('trpcultivate_phenotypes.database'),
      $container->get('trpcultivate_phenotypes.ontology'),
      $container->get('trpcultivate_phenotypes.terms'),
      $container->get('trpcultivate_phenotypes.vocabulary'),
    );
  }

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
    // @TODO: add check if there is data to disable all term field elements
    // preventing user from changing values.

    // @TODO: Tripal add vocabulary not available, mark with # sign in
    // ontology instructions/guide.

    $configuration = $this->config(static::SETTINGS);

  
    // Attach library.
    $form['#attached']['library'][] = 'trpcultivate_phenotypes/autoselect-field';
    
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

    $form['ontology_fieldset']['wrapper']['table_fields'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('<strong><u>GENUS</u></strong>'), 
        $this->t('Trait Vocabularies'), 
        $this->t('Associated Database'), 
        $this->t('Crop Ontology')
      ],
    ];

    // Get genus ontology configuration variables.
    $genus_ontology = $this->srv_ontology->defineGenusOntology();
    
    // Each genus, create table fields for cv, method, unit, db and crop ontology.
    // Prepare vocabulary options.
    $vocabulary_options = $this->srv_vocabulary->getVocabularies();
    $database_options = $this->srv_database->getDatabase();

    $i = 0;
    foreach($genus_ontology as $genus => $vars) {
      // Label - Genus.
      $form['ontology_fieldset']['wrapper']['table_fields'][ $i ][ $genus . '_label' ] = [
        '#type' => 'item',
        '#title' => ucfirst($genus)
      ];

      // Get genus ontology configuration set of values.
      $config_value = $this->srv_ontology->getGenusOntologyConfigValue($genus);
      $config_token = array_values($vars);
      
      $j = 0;
      $config_i = 0;
  
      // Loop - Trait, DB and Crop Ontology.
      while($j < 3) {
        // Each genus, has 3 columns for CV, DB and Crop Ontology.
        // CV requires 3 select field whereas the other two require only one each.
        $fld_count = ($j == 0) ? 3 : 1;
        
        // Render x number of fields determined by field count.
        $k = 1;
        while($k <=  $fld_count) {
          $options = ($j == 1) ? $database_options : $vocabulary_options;

          $fld_options = [0 => 'Select: ' . ucfirst(str_replace('_', ' ', $config_token[ $config_i ]))] + $options;
          $v = $config_value[ $config_token[ $config_i ] ];
          $default_value = ($v > 0) ? $v : 0;

          // Select field.
          $form['ontology_fieldset']['wrapper']['table_fields'][ $i ][ $j ][ $k ][ $genus . '_' . $config_token[ $config_i ] ] = [
            '#type' => 'select',
            '#options' => $fld_options,
            '#attributes' => ['style' => 'width: 150px'],
            '#default_value' => $default_value,
            '#tree' => FALSE,
          ];

          $k++;
          $config_i++;
        }

        $j++;
      }

      $i++;
    }
    
    $allow_new = $configuration->get($this->sysvar_ontology . '.allownew');
    $form['ontology_fieldset']['allow_new'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow new traits to be added to the Controlled Vocabulary during upload.'),
      '#description' => $this->t('This applies to all organism listed above.'),
      '#default_value' => $allow_new
    ];


    // TERMS


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
    $terms = $this->srv_terms->mapDefaultTermToConfig();

    // Render each term as autocomplete field element.
    foreach($terms as $term => $config) {
      // Field description.
      $describe = $this->t($field_description[ $config ]);
      // Field placeholder and title text.
      $placeholder = $title = $this->t(ucfirst($term));
      // Field default value.
      $config_value = $this->srv_terms->getTermConfigValue($term);
      $term_rec = $this->srv_terms->getTerm($config_value);
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {    
    // Validate each term exits and field is not empty.
    
    // Get term - term configuration variable mapping details.
    $terms = $this->srv_terms->mapDefaultTermToConfig();
    
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

    // Validate each genus ontology configuration.
    // For a give genus, if one field was altered then user is trying
    // to set a value and this validate should ensure that other
    // configuration variables are set.
    $genus_ontology = $this->srv_ontology->defineGenusOntology();
    foreach($genus_ontology as $genus => $vars) {
      $var_set_ctr = 0;
      $fld_names = [];
      
      foreach($vars as $i => $config) {
        $fld_names[ $i ] = $genus . '_' . $config;
        if ((int) $form_state->getValue($fld_names[$i]) > 0) {
          $var_set_ctr++;
        }
      }

      if ($var_set_ctr > 0) {
        // A field/s have been set, make sure all were set.
        unset($config);
        foreach($fld_names as $field) {
          if ($form_state->getValue($field) <= 0) {
            // Field is empty.
            $form_state->setErrorByName($field, $this->t('Error: could not save form. Required field
              (GENUS: FIELD) @fld is empty.', [ '@fld' => str_replace('_', ' ', $field) ]));  
          }
        }
      }
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {    
    $configuration = $this->config(static::SETTINGS);

    // Get term - term configuration variable mapping details.
    $terms = $this->srv_terms->mapDefaultTermToConfig();    
    // Configuration.
    $sysvar_terms = 'trpcultivate.phenotypes.ontology.terms.';

    foreach($terms as $term => $config) {
      $fld = $form_state->getValue($config);
      $id = ChadoCVTermAutocompleteController::getCVtermId($fld);    
    
      $configuration
        ->set($sysvar_terms . $config, $id);
    }  
    
    // Get genus ontology.
    $genus_ontology = $this->srv_ontology->defineGenusOntology();
    // Configuration.
    $var_ontology = $this->sysvar_ontology . '.cvdbon.';

    foreach($genus_ontology as $genus => $vars) {
      // Trait, method, unit, database and crop ontology.
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        $fld_value = $form_state->getValue($fld_name);
        
        $configuration
          ->set($var_ontology . $genus . '.' . $config, $fld_value);
      }
    }

    // Allow new traits to be added during upload.
    $allow_new = $form_state->getValue('allow_new');
    $configuration
      ->set($this->sysvar_ontology . '.allownew', $allow_new);


    $configuration
      ->save();

    return parent::submitForm($form, $form_state);
  }
}