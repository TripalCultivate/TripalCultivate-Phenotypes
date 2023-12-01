<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Trait service definition.
 * 
 * This service handles all processes involving traits/unit/method or 
 * cvterms that are used as phenotypic traits/unit/method.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\tripal_chado\Database\ChadoConnection;
use \Drupal\tripal\Services\TripalLogger;

/**
 * Class TripalCultivatePhenotypesTraitsService.
 */
class TripalCultivatePhenotypesTraitsService {
  // Genus ontology service.
  protected $service_genus_ontology;

  // Terms service.
  protected $service_terms;

  // Chado database connection.
  protected $chado;

  // Tripal logger.
  protected $logger;
  
  // Genus ontology configuration values.
  private $config = [];

  // Terms configuration values.
  private $terms = [];

  /**
   * Constructor.  
   */
  public function __construct(TripalCultivatePhenotypesGenusOntologyService $genus_ontology, TripalCultivatePhenotypesTermsService $terms, ChadoConnection $chado, TripalLogger $logger) {
    // Genus ontology service.
    $this->service_genus_ontology = $genus_ontology;
    // Terms service.
    $this->service_terms = $terms;
    // Chado connection.
    $this->chado = $chado;
    // Tripal logger service.
    $this->logger = $logger;

    // Fetch all configured genus (active genus).
    $active_genus = $this->service_genus_ontology->getConfiguredGenusList(); 

    if ($active_genus) {
      // Create a map of genus-ontology configuration values accessible by genus with
      // all values (id) resolved to db name or cv name.
      foreach($active_genus as $genus) {
        // Fetch genus trait, method, unit, db and ontology configuration values.
        $genus_config = $this->service_genus_ontology->getGenusOntologyConfigValues($genus);

        // Resolve each configuration entry to matching cv or db id number.
        foreach($genus_config as $config => $value) {
          if ($value > 0) {
            // Ontology might be set to 0 - as it is optional in the config page.
            // Map only values that have been set.

            if ($config == 'database') {
              // DB configuration. 
              $rec = chado_get_db(['db_id' => $value]);
            }
            else {
              // CV configuration.
              $rec = chado_get_cv(['cv_id' => $value]);
            }

            if ($rec) {
              $this->config[ $genus ][ $config ] = ['id' => $value, 'name' => $rec->name];
            }
          }
        }
      }
    }

    // Terms configurations.
    // Terms focused on adding traits, required by this service.
    // NOTE: method to trait and unit to method both point to the
    //       save term name: related (by default) but both can be configured 
    //       to point to 2 different terms.
    $req_terms = [
      'method', // Collection method.
      'unit',   // Trait unit of measurement. 
      'unit_to_method_relationship_type',  // Relate unit - method.
      'method_to_trait_relationship_type', // Relate method - trait.
      'additional_type' // Unit data type.
    ];

    foreach($req_terms as $term) {
      $this->terms[ $term ] = $this->service_terms->getTermId($term);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.terms'),
      $container->get('tripal_chado.database'),
      $container->get('tripal.logger')
    );
  }  
  
  /**
   * Create/Insert phenotypic trait.
   * 
   * @param array $trait
   *   Trait added to this module has the following data keys:
   *   - Trait Name: the name of the trait (e.g. Plant Height).
   *   - Trait Description: the description of the trait (e.g. the height of the plant from the
   *     ground to it's tallest point without stretching the plant.)
   *   - Method Short Name: a short name for the method (e.g. Average of 5 plants)
   *   - Collection Method: a description of the method used to collect the phenotypic data
   *     (e.g. measured 5 plants from the plot and then averaged them.)
   *   - Unit: the full word describing the unit used in the method (e.g. centimeters)
   *   - Type: Quantitative or Qualitative.
   * @param string $genus
   *   Genus: the organism genus the trait is for (e.g. Lens).
   * @param object $schema
   *   Tripal DBX Chado Connection object
   *   
   * @see Header property and Trait Importer.
   * NOTE: 
   *   - Tripal Importer Plugin loading of file is performed using database transaction.
   *   - Although Genus is collected in the Importer form, It is not saved as part of the terms/traits.
   *     Genus functions as a pointer to which CV to save the terms as set in the Genus-ontology configuration.  
   * 
   * @return
   *   An array with the following keys where each value is the id of new cvterm:
   *   trait, method, unit.
   */
  public function insertTrait($trait, $genus, $schema = NULL) {
    // Fetch configuration settings of the genus.
    $genus_config = $this->config[ $genus ];
    
    if (!$genus_config) {
      return 0;
    }
    
    // Query term.
    $sql = "SELECT cvterm_id FROM {1:cvterm} INNER JOIN {1:cv} USING (cv_id) 
      WHERE cvterm.name = :value AND cv.cv_id = :id LIMIT 1";

    // TRAIT, METHOD and UNIT data array.
    $arr_trait = [
      'trait' =>  [
        'name' =>  $trait['Trait Name'],
        'description' => $trait['Trait Description'],
      ],
      'method' => [
        'name' => $trait['Method Short Name'],
        'description' => $trait['Collection Method'],
      ],
      'unit'  =>  [
        'name' => $trait['Unit'],
        'description' => $trait['Unit'],
      ]
    ];
    
    // Create trait.
    foreach($arr_trait as $type => $values) {
      // Check if a record exists in the same Genus.
      $value = ($type == 'method') ? $values['description'] : $values['name'];
      $id = $this->chado->query($sql, [':value' => $value, ':id' => $genus_config[ $type ]['id']])
        ->fetchField();
      
      // Found a record. If trait is found, trigger an error as same trait 
      // is already in the genus, otherwise reuse information.
         
      if ($id && $type == 'trait') {
        $this->logger->error('Error. Trait @key already exists in the Genus.', ['@key' => $trait['Trait Name']]);
      }
      
      if ($id) {
        $arr_trait[ $type ]['id'] = $id;
      }
      else {
        $rec = [
          'id' => $genus_config['database']['name'] . ':' . $values['name'],
          'name' => $values['name'],
          'cv_name' => $genus_config[ $type ]['name'],
          'definition' => $values['description']   
        ];
        
        $ins = chado_insert_cvterm($rec, [], $schema);
        if (!$ins) {
          // Could not insert cvterm.
          $this->logger->error('Error. Could not insert unit @key.', ['@key' => $values['name']]);
        }

        $arr_trait[ $type ]['id'] = $ins->cvterm_id;
      } 
    }

    // RELATIONSHIPS: trait-method and method-unit.
    // Query relationship.
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship} 
      WHERE subject_id = :s_id AND type_id = :t_id AND object_id = :o_id";

    $arr_rel = [
      'method-trait' => $this->terms['method_to_trait_relationship_type'], 
      'method-unit'  => $this->terms['unit_to_method_relationship_type']
    ];

    // Create relationships.
    foreach($arr_rel as $type => $rel) {
      // Check if relationship exists.
      if ($type == 'method-trait') {
        $subject = $arr_trait['trait']['id'];
        $object  = $arr_trait['method']['id'];
      }
      else {
        $subject = $arr_trait['method']['id'];
        $object  = $arr_trait['unit']['id'];
      }

      $exists = $this->chado->query($sql, [':s_id' => $subject, ':t_id' => $rel, ':o_id' => $object])
        ->fetchField();
      
      if (!$exists) {
        $this->chado->insert('1:cvterm_relationship')
          ->fields([
            'subject_id' => $subject,
            'type_id' => $rel,
            'object_id' => $object
          ])
          ->execute();
      }
    }

    // UNIT DATA TYPE:
    $sql = "SELECT cvtermprop_id FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado->query($sql, [':c_id' => $arr_trait['unit']['id'], ':t_id' => $this->terms['additional_type']])
      ->fetchField();
    
    if (!$data_type) {
      $this->chado->insert('1:cvtermprop')
        ->fields([
          'cvterm_id' => $arr_trait['unit']['id'],
          'type_id' => $this->terms['additional_type'],
          'value' => $trait['Type']
        ])
        ->execute();
    }

    // Return record id created.
    if (isset($arr_trait['trait']['id']) && isset($arr_trait['method']['id']) && isset($arr_trait['unit']['id'])) {
      return [
        'trait' => $arr_trait['trait']['id'],
        'method' => $arr_trait['method']['id'],
        'unit'  => $arr_trait['unit']['id']
      ];
    }
    else {
      return 0;
    }
  }
}