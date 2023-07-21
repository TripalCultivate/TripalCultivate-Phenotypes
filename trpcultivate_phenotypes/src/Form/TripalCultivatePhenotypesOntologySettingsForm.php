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

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;


/**
 * Class definition TripalCultivatePhenotypesOntologySettingsForm.
 */
class TripalCultivatePhenotypesOntologySettingsForm extends ConfigFormBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';
  
  /**
   * Service genus ontology.
   * 
   * @var object
   */
  protected $service_genusontology;

  /**
   * Service terms.
   * 
   * @var object
   */
  protected $service_terms;
  
  /** 
   * Chado connection.
   * 
   * @var object
   */
  protected $chado;

  /**
   * Configuration variable cvdbon (ontology).
   *
   * @var string
   */
  private $sysvar_ontology;

  /**
   * Terms and ontology configuration variable names.
   * 
   * @var array
   */
  private $config_vars;
  
  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
    TripalCultivatePhenotypesGenusOntologyService $genus_ontology,
    TripalCultivatePhenotypesTermsService $terms,
    ChadoConnection $chado
  ) {
    
    parent::__construct($config_factory);
  
    // Services: genus ontology, terms and chado database.
    $this->service_genusontology = $genus_ontology;
    $this->service_terms = $terms;
    $this->chado = $chado;

    // Prepare terms and ontology.
    $this->config_vars['ontology'] = $this->service_genusontology->defineGenusOntology();
    $this->config_vars['terms']    = $this->service_terms->defineTerms();

    // Terms and Genus Ontology configuration.
    $this->sysvar_ontology = 'trpcultivate.phenotypes.ontology';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Return services terms and genus ontology.
    return new static(
      $container->get('config.factory'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.terms'),
      $container->get('tripal_chado.database')
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

    // No genus nor terms to work on. Remind user to execute Tripal Job
    // registered during install to initialize module with terms.
    // Could not proceed if no genus in the host site.
    if (count($this->config_vars['ontology']) <= 0 || count($this->config_vars['terms']) <= 0) {
      $url = Url::fromRoute('tripal.jobs');
      $link = Link::fromTextAndUrl($this->t('Click here to manage Tripal Jobs'), $url)
        ->toString();

      $warning = $this->t('Tripal Cultivate Phenotypes could not find required terms and/or organism records (Genus), 
        both used for creating terms and genus-ontology module configuration. Please execute Tripal Job titled:
        Tripal Cultivate Phenotypes: Install Ontology and Terms. @jobs', ['@jobs' => $link]);
      $this->messenger()->addWarning($warning);

      return $form;
    }
    
    // Attach library.
    $form['#attached']['library'][] = 'trpcultivate_phenotypes/script-autoselect-field';
    
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
      '#theme' => 'header_instructions',
      '#data' => [
        'section' => 'ontology',
        'link_01' => '#', // Not currently available.
        'link_02' => $link->toRenderable()
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

    // Each genus, create table fields for cv, method, unit, db and crop ontology.
    // Prepare vocabulary and database select field options.
    $vocabulary = $this->chado->query("
      SELECT cv_id, name FROM {1:cv} ORDER BY name ASC    
    ")
    ->fetchAllKeyed(0, 1);
    $vocabulary_options = (count($vocabulary) > 0) ? $vocabulary : [];

    $database = $this->chado->query("
      SELECT db_id, name FROM {1:db} ORDER BY name ASC
    ")
    ->fetchAllKeyed(0, 1);
    $database_options = (count($database) > 0) ? $database : [];

    $i = 0;
    foreach($this->config_vars['ontology'] as $genus => $vars) {
      // Label - Genus.
      $form['ontology_fieldset']['wrapper']['table_fields'][ $i ][ $genus . '_label' ] = [
        '#type' => 'item',
        '#title' => ucwords($genus)
      ];

      // Get genus ontology configuration set of values.
      $config_value = $this->service_genusontology
        ->getGenusOntologyConfigValues($genus);
      
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


    // ALLOW NEW TRAITS.
    $allow_new = $this->config(static::SETTINGS)
      ->get($this->sysvar_ontology . '.allownew');
    
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
      '#open' => FALSE,
    ];

    // Instructions.
    $form['term_fieldset']['guide'] = [
      '#type' => 'inline_template',
      '#theme' => 'header_instructions',
      '#data' => [
        'section' => 'terms',
        'link_01' => '',
        'link_02' => ''
      ],
    ];
    
    // Render each term as autocomplete field element.
    foreach($this->config_vars['terms'] as $config => $prop) {
      // Field description.
      $describe = $this->t($prop['help_text']);
      // Field placeholder and title text.
      $placeholder = $title = $this->t(ucwords($prop['name']));
      // Field default value.
      $config_value = $this->service_terms->getTermId($config);
      $default_value = ChadoCVTermAutocompleteController::formatCVterm($config_value);
      
      // Field render array.
      $form['term_fieldset'][ $config ] = [
        '#type' => 'textfield',
        '#title' => $title,
        '#attributes' => ['class' => ['tcp-autocomplete'], 'placeholder' => $placeholder],
        '#description' => $describe,
        '#default_value' => $default_value,
        // Tripal autocomplete cvtern service: parameter - count.
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
    // Validate each genus ontology configuration.
    // For a give genus, if one field was altered then user is trying
    // to set a value and this validate should ensure that other
    // configuration variables are set.
    foreach($this->config_vars['ontology'] as $genus => $vars) {
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

    // Validate each term exits and field is not empty.
    foreach($this->config_vars['terms'] as $config => $prop) {
      if ($fld = $form_state->getValue($config)) {
        // Has a value, test term exists.
        $id = ChadoCVTermAutocompleteController::getCVtermId($fld);    

        if (!$id) {
          $form_state->setErrorByName($config, $this->t('Error: could not save form. Required field
          @fld value does not exist.', ['@fld' => ucwords($prop['name'])]));
        }
      }
      else {
        // Field is empty.
        $form_state->setErrorByName($config, $this->t('Error: could not save form. Required field
          @fld is empty.', ['@fld' => ucwords($prop['name'])]));
      }
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {    
    // Save genus ontology.
    $values_genusontology = [];
    foreach($this->config_vars['ontology'] as $genus => $vars) {
      // Trait, method, unit, database and crop ontology.
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        $fld_value = $form_state->getValue($fld_name);
        
        $values_genusontology[ $genus ][ $config ] = $fld_value;
      }
    }

    $this->service_genusontology
      ->saveGenusOntologyConfigValues($values_genusontology);

    // Save allow new traits to be added during upload.
    $allow_new = $form_state->getValue('allow_new');
    $this->config(static::SETTINGS)
      ->set($this->sysvar_ontology . '.allownew', $allow_new)
      ->save();

    // Save terms.
    $values_term = [];
    foreach(array_keys($this->config_vars['terms']) as $config) {
      $fld = $form_state->getValue($config);
      $id = ChadoCVTermAutocompleteController::getCVtermId($fld);    
      
      $values_term[ $config ] = $id;
    }  
    
    $this->service_terms
      ->saveTermConfigValues($values_term);
  
    return parent::submitForm($form, $form_state);
  }
}