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

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesOntologyService {
  
  /**
   * Define cv and cvterm of terms used by term/otology configuration.
   * Terms are created during install of TripcalCultivate Phenotypes.
   * 
   * @return boolean
   *   True if all terms were inserted successfully and false otherwise.
   */  
  public function loadTerms() {
    $ontology = [];
    $error = 0;

    // genus
    $ontology['taxonomic_rank'] = [
      'name' => 'taxonomic_rank',
      'definition' => 'A vocabulary of taxonomic ranks (species, family, phylum, etc).',

      'terms' => [
        [
          'id' => 'TAXRANK:0000005',
          'name' => 'genus',
          'definition' => 'The genus.',
        ],
    
        // Additional terms in this cv here.
      ],
    ];
    
    // unit
    $ontology['uo'] = [
      'name' => 'uo',
      'definition' => 'Units of Measurement Ontology.',

      'terms' => [
        [
          'id' => 'UO:0000000',
          'name' => 'unit',
          'definition' => '',
        ],

        // Additional terms in this cv here.
      ],
    ];
    
    // related
    $ontology['synonym_type'] = [
      'name' => 'synonym_type',
      'definition' => 'A local vocabulary added for synonynm types.',

      'terms' => [
        [
          'id' => 'internal:related',
          'name' => 'related',
          'definition' => 'Is related to.',
        ],

        // Additional terms in this cv here.
      ],
    ];
    
    // year
    $ontology['tripal_pub'] = [
      'name' => 'tripal_pub',
      'definition' => 'Tripal Publication Ontology. A temporary ontology until a more 
        formal appropriate ontology to be identified.',

      'terms' => [
        [
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
    $ontology['NCIT'] = [
      'name' => 'NCIT',
      'definition' => 'The NCIT OBO Edition project aims to increase integration of the 
        NCIt with OBO Library ontologies NCIt is a reference terminology that includes 
        broad coverage of the cancer domain, including cancer related diseases, findings 
        and abnormalities. NCIt OBO Edition releases should be considered experimental.',

      'terms' => [
        [
          'id' => 'NCIT:C71460',
          'name' => 'method',
          'definition' => 'A means, manner of procedure, or systematic course of action 
            that have to be performed in order to accomplish a particular goal.',
        ],

        [
          'id' => 'NCIT:C25341',
          'name' => 'location',
          'definition' => 'A position, site, or point in space where something can be found.',
        ],

        [
          'id' => 'NCIT:C28038',
          'name' => 'replicate',
          'definition' => 'A role played by a biological sample in the context of an 
            experiment where the intent is that biological or technical variation is measured.'
        ],

        [
          'id' => 'NCIT:C45262',
          'name' => 'Collected By',
          'definition' => 'Indicates the person, group, or institution who performed 
            the collection act.',
        ],

        [
          'id' => 'NCIT:C43381',
          'name' => 'Entry',
          'definition' => 'An item inserted in a written or electronic record.',
        ],

        [
          'id' => 'NCIT:C42614',
          'name' => 'name',
          'definition' => 'The words or language unit by which a thing is known.',
        ],

        // Additional terms in this cv here.
      ],
    ];

    // plot
    $ontology['AGRO'] = [
      'name' => 'AGRO',
      'definition' => 'Agricultural experiment plot',

      'terms' => [
        [
          'id' => 'AGRO:00000301',
          'name' => 'plot',
          'definition' => 'A site within which an agricultural experimental process is conducted',
        ]
      ],

      // Additional terms in this cv here.
    ];
    
    // Install terms.
    foreach($ontology as $cv_name => $cv) {
      // Each cv housing terms.
      $cv_row = [
        'name' => $cv_name,
        'definition' => $cv['definition']
      ];

      foreach($cv['terms'] as $term) {
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
        }
        
        // Insert the term.
        $cvterm = function_exists('chado_insert_cvterm')
          ? chado_insert_cvterm($term) : tripal_insert_cvterm($term);
      }
    }


    return ($error) ? FALSE: TRUE;
  }
}