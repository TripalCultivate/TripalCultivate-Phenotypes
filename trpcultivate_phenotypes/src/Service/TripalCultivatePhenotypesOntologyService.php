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

use Drupal\Core\Config\ImmutableConfig;

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesOntologyService {
  
  /**
   * Module configuration.
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct() {
    // Read only configuration.
    $this->config = \Drupal::config('trpcultivate_phenotypes.settings');
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
      foreach($terms as $cv_name => $cv) {
        // Each cv housing terms.
        $cv_row = [
          'name' => $cv_name,
          'definition' => $cv['definition']
        ];

        foreach($cv['terms'] as $term) {
          // Remove configuration mapping element.
          if (isset($term['#config'])) {
            unset($term['#config']);
          }

          // Each term in a cv.
          $cvterm_row = [
            'name' => $term['name'],
            'cv_id' => ['name' => $cv_name]
          ];
          
          $cvterm_id = (function_exists('chado_get_cvterm')) 
            ? chado_get_cv($cvterm_row) : tripal_get_cvterm($cvterm_row);
          
          if (!$cvterm_id) {
            // No match of this term in the database, see if cv exists.
            $cv_id = (function_exists('chado_get_cv')) 
              ? chado_get_cv($cv_row) : tripal_get_cv($cv_row);

            if (!$cv_id) {
              $cv_id = (function_exists('chado_insert_cv')) 
                ? chado_insert_cv($cv_row['name'], $cv_row['definition']) 
                : tripal_insert_cv($cv_row['name'], $cv_row['definition']);

              if (!$cv_id) {
                // Error inserting cv.
                $error = 1;
                break; break;
              }
            }            
          
            // Insert the term.
            $cvterm = function_exists('chado_insert_cvterm')
              ? chado_insert_cvterm($term) : tripal_insert_cvterm($term);
          }
        }
      }
    }


    return ($error) ? FALSE: TRUE;
  }

  /**
   * Set ontology of a trait/term.
   * 
   * @param integer $trait
   *   Trait/term cvterm id number this trait will be added to an ontology.
   * @param integer $ontology
   *   Ontology cvterm id number a trait will be associated to.
   * @param boolean $replace_default
   *   True, attempt to replace trait-ontology relationhsip default.
   */
  public function setTraitOntology($trait, $ontology, $replace_ontology = FALSE) {
    $sysvar_related = $this->config->get('trpcultivate.phenotypes.ontology.terms.related');

    if ($sysvar_related > 0 && $trait > 0 && $ontology > 0) {
      $values = [
        'type_id' => $sysvar_related,
        'object_id' => $trait,
        'subject_id' => $ontology
      ];

      // Check if there existed any ontology for this trait.
      $umatch = array_slice($values, 0, 2);
      $ontology_found = chado_generate_var('cvterm_relationship', $umatch);

      if ($ontology_found && $replace_ontology) {
        // When implied in replace ontology, replace previously set ontology.
        // Alter the subject_id part to new ontology.
        $uvalue = [$values, 2, 1];
      }
    }
  }
}