<?php

/**
 * @file
 * Manage property and operation pertaining to data ontology information.
 *
 * Contains Default Controlled Vocabulary terms.
 * Source: TRIPAL CORE - http://tripal.info
 *         ONTOBEE - http://www.ontobee.org
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesOntologyService {
  
  /**
   * Module configuration.
   */
  protected $config_read;
  protected $config_edit;

  /**
   * Holds terms.
   */
  private $terms;

  /**
   * Configuration heirarchy for terms.
   */
  private $sysvar_terms;

  /**
   * Tripal Logger Service.
   */
  private $logger;

  /**
   * Constructor.
   */
  public function __construct() {
    $module_settings = 'trpcultivate_phenotypes.settings';

    // Read only configuration.
    $this->config_read = \Drupal::config($module_settings);
    // Editable configuration.
    $this->config_edit = \Drupal::configFactory()->getEditable($module_settings);
    // Load terms.
    $this->terms = $this->defineTerms();
    // Set configuration heirarchy for terms.
    $this->sysvar_terms = 'trpcultivate.phenotypes.ontology.terms';

    // Tripal Logger service.
    $this->logger = \Drupal::service('tripal.logger');
  }
  
  /**
   * Define terms.
   * Each term set is defined using the array structure below:
   * Format:
   *   array['cv name'] = array(
   *     'name' => string: name,
   *     'definition' => string: definition
   *     
   *     // List of terms (each item will be an entry in chado.cv).
   *     // #config element is mapping information that maps a term to configuration variable.
   *     'terms' => array(...) 
   */
  public function defineTerms() {
    $terms = [];
    
    // genus
    $terms['taxonomic_rank'] = [
      'name' => 'taxonomic_rank',
      'definition' => 'A vocabulary of taxonomic ranks (species, family, phylum, etc).',

      'terms' => [
        [
          '#config' => 'genus',
          'id' => 'TAXRANK:0000005',
          'name' => 'genus',
          'definition' => 'The genus.',
        ],
    
        // Additional terms in this cv here.
      ],
    ];
    
    // unit
    $terms['uo'] = [
      'name' => 'uo',
      'definition' => 'Units of Measurement Ontology.',

      'terms' => [
        [
          '#config' => 'unit',
          'id' => 'UO:0000000',
          'name' => 'unit',
          'definition' => '',
        ],

        // Additional terms in this cv here.
      ],
    ];
    
    // related
    $terms['synonym_type'] = [
      'name' => 'synonym_type',
      'definition' => 'A local vocabulary added for synonynm types.',

      'terms' => [
        [
          '#config' => 'related',
          'id' => 'internal:related',
          'name' => 'related',
          'definition' => 'Is related to.',
        ],

        // Additional terms in this cv here.
      ],
    ];
    
    // year
    $terms['tripal_pub'] = [
      'name' => 'tripal_pub',
      'definition' => 'Tripal Publication Ontology. A temporary ontology until a more 
        formal appropriate ontology to be identified.',

      'terms' => [
        [
          '#config' => 'year',
          'id' => 'TPUB:0000059',
          'name' => 'Year',
          'definition' => 'The year the work was published. This should be a 4 digit year.',
        ],

        // Additional terms in this cv here.
      ],
    ];
    
    // method
    // location
    // replicate
    // collector
    // entry
    $terms['NCIT'] = [
      'name' => 'NCIT',
      'definition' => 'The NCIT OBO Edition project aims to increase integration of the 
        NCIt with OBO Library ontologies NCIt is a reference terminology that includes 
        broad coverage of the cancer domain, including cancer related diseases, findings 
        and abnormalities. NCIt OBO Edition releases should be considered experimental.',

      'terms' => [
        [
          '#config' => 'method',
          'id' => 'NCIT:C71460',
          'name' => 'method',
          'definition' => 'A means, manner of procedure, or systematic course of action 
            that have to be performed in order to accomplish a particular goal.',
        ],

        [
          '#config' => 'location',
          'id' => 'NCIT:C25341',
          'name' => 'location',
          'definition' => 'A position, site, or point in space where something can be found.',
        ],

        [
          '#config' => 'replicate',
          'id' => 'NCIT:C28038',
          'name' => 'replicate',
          'definition' => 'A role played by a biological sample in the context of an 
            experiment where the intent is that biological or technical variation is measured.'
        ],

        [
          '#config' => 'collector',
          'id' => 'NCIT:C45262',
          'name' => 'Collected By',
          'definition' => 'Indicates the person, group, or institution who performed 
            the collection act.',
        ],

        [
          '#config' => 'entry',
          'id' => 'NCIT:C43381',
          'name' => 'Entry',
          'definition' => 'An item inserted in a written or electronic record.',
        ],

        [
          '#config' => 'name',
          'id' => 'NCIT:C42614',
          'name' => 'name',
          'definition' => 'The words or language unit by which a thing is known.',
        ],

        // Additional terms in this cv here.
      ],
    ];

    // plot
    $terms['AGRO'] = [
      'name' => 'AGRO',
      'definition' => 'Agricultural experiment plot',

      'terms' => [
        [
          '#config' => 'plot',
          'id' => 'AGRO:00000301',
          'name' => 'plot',
          'definition' => 'A site within which an agricultural experimental process is conducted',
        ]
      ],

      // Additional terms in this cv here.
    ];
    
    
    return $terms;
  }

  /**
   * Insert terms.
   * 
   * @param array $terms.
   *   Associative array of two levels, where the top level is the cv
   *   and the level below is an array of terms belong to a parent cv.
   *   
   *   array['cv name'] = array(
   *     'name' => string: name,
   *     'definition' => string: definition
   *     
   *     // List of terms (each item will be an entry in chado.cv).
   *     'terms' => array(...)
   *   )
   * 
   * @return boolean
   *   True if all terms were inserted successfully and false otherwise.
   */  
  public function loadTerms($terms) {
    $error = 0;
    
    if ($terms) {
      // Install terms.
      foreach($terms as $cv) {
        // Each cv housing terms.
        $cv_row = [
          'name' => $cv['name'],
        ];

        foreach($cv['terms'] as $term) {
          // Remove configuration mapping element.
          if (isset($term['#config'])) {
            $config_name = $term['#config'];
            unset($term['#config']);
          }

          // Each term in a cv.
          $cvterm_row = [
            'name' => $term['name'],
            'cv_id' => ['name' => $cv['name']]
          ];
          
          $cvterm = (function_exists('chado_get_cvterm')) 
            ? chado_get_cvterm($cvterm_row) : tripal_get_cvterm($cvterm_row);

          if (!$cvterm) {
            // No match of this term in the database, see if cv exists.

            $cv_id = (function_exists('chado_get_cv')) 
              ? chado_get_cv($cv_row) : tripal_get_cv($cv_row);
             
            if (!$cv_id) {
              $cv_id = (function_exists('chado_insert_cv')) 
                ? chado_insert_cv($cv_row['name'], $cv['definition']) 
                : tripal_insert_cv($cv_row['name'], $cv['definition']);

              if (!$cv_id) {
                // Error inserting cv.
                $error = 1;
                $this->logger->error('Error. Could not insert cv.');
              }
            }            
          
            // Insert the term.
            $cvterm = function_exists('chado_insert_cvterm')
              ? chado_insert_cvterm($term) : tripal_insert_cvterm($term);
          }
          
          // Set the term id as the configuration value of the
          // term configuration variable.
          $this->config_edit->set($this->sysvar_terms . '.' . $config_name, $cvterm->cvterm_id)
            ->save();
        }
      }
    }


    return ($error) ? FALSE: TRUE;
  }


  /**
   * Map terms to configuration variable.
   * 
   * @return array
   *   Keyed by term and value being the configuration variable name encoded 
   *   in the term definition #config key it maps to.
   */
  public function mapDefaultTermToConfig() {
    $terms_map = [];

    // Fetch the mapping information of each term stored
    // in #config element of each term definition.
    foreach($this->terms as $terms) {
      foreach($terms['terms'] as $term) {
        
        // Save term and term configuration variable it maps to.
        // Read as term maps to term configuration variable.
        $terms_map[ $term['name'] ] = $term['#config'];
      }
    }


    return $terms_map;
  }
}