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
    
    // Create a map of genus-ontology configuration values accessible by genus with
    // all values (id) resolved to db name or cv name.
    foreach($active_genus as $i => $genus) {
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
            $this->config[ $i ][ $config ] = ['id' => $value, 'name' => $rec->name];
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
   *   - Genus: the organism genus the trait is for (e.g. Lens).
   * 
   * @see Header property and Trait Importer.
   * NOTE: 
   *   - Tripal Importer Plugin loading of file is performed using database transaction.
   *   - Although Genus is collected in the Importer form, It is not saved as part of the terms/traits.
   *     Genus functions as a pointer to which CV to to save the terms as set in the Genus-ontology configuration.  
   * 
   * @return
   *   An array with the following keys where each value is the new cvterm:
   *   trait, method, unit.
   */
  public function insertTrait($trait, $genus) {
    // Fetch configuration settings of the genus.
    $genus_config = $this->config[ $genus ];
  
    // Query to check term.
    $sql = 'SELECT cvterm_id FROM {cvterm} WHERE %s = :value AND cv_id IN (SELECT cv_id FROM {cv} WHERE name = :cv_name)';

    // TRAIT:
    // Save trait. Trait object.
    $rec_trait = chado_insert_cvterm([
      'cv_name' => $genus_config['trait']['name'],
      'id' => $genus_config['database']['name'] . ':' . $trait['Trait Name'],
      'name' => $trait['Trait Name'],
      'definition' => $trait['Trait Description'] 
    ]);

    if (!$rec_trait) {
      // Could not insert cvterm.
      $this->logger->error('Error. Could not insert term/trait @key.', ['@key' => $trait['Trait Name']]);
    }
    
    // METHOD:
    // Save method.
    // Reuse method if it existed otherwise create a new record.
    $sql_method = sprintf($sql, 'definition');
    $rec_method = $this->chado->query($sql_method, [
      ':value' => $trait['Collection Method'], 
      ':cv_name' => $this->terms['method']
    ])
      ->fetchField();
    
    if ($rec_method > 0) {
      // Method already exists.
      $method = chado_get_cvterm(['cvterm_id' => $rec_method]);
    }
    else {
      // Create the a new method.
      $method = chado_insert_cvterm([
        'cv_name' => $genus_config['method']['name'],
        'id' => $genus_config['database']['name'] . ':' . $trait['Method Short Name'],
        'name' => $trait['Method Short Name'],
        'definition' => $trait['Collection Method']   
      ]);

      if (!$method) {
        // Could not insert cvterm.
        $this->logger->error('Error. Could not insert method @key.', ['@key' => $trait['Method Short Name']]);
      }
    }
    
    // Method object.
    $rec_method = $method;
    
    // UNIT:
    // Save unit.
    // Reuse unit if it existed otherwise create a new record.
    $sql_unit = sprintf($sql, 'name');
    $rec_unit = $this->chado->query($sql_unit, [
      ':value' => $trait['Unit'],
      ':cv_name' => $this->terms['unit']
    ])
      ->fetchField();

    if ($rec_unit > 0) {
      // Unit already exits.
      $unit = chado_get_cvterm(['cvterm_id' => $rec_unit]);
    }
    else {
      // Create a new unit.
      $unit = chado_insert_cvterm([
        'cv_name' => $genus_config['unit']['name'],
        'id' => $genus_config['database']['name'] . ':' . $trait['Unit'],
        'name' => $trait['Unit'],
        'definition' => $trait['Unit']
      ]);

      if (!$unit) {
        // Could not insert cvterm.
        $this->logger->error('Error. Could not insert unit @key.', ['@key' => $trait['Unit']]);
      }
    }
    
    // Unit object.
    $rec_unit = $unit;

    // PROPERTIES AND RELATIONSHIPS:
    
    // Supplemental metadata to unit - to tell whether unit is numerical (Quantitative)
    // or descriptive (Qualitative) data type.
    $unit_type = [
      'cvterm_id' => $rec_unit->cvterm_id,
      'type_id' => $this->terms['additional_type']
    ];

    $rec_unit_prop = chado_select_record('cvtermprop', ['cvtermprop_id'], $unit_type);
    if (!$rec_unit_prop) {
      $unit_type['value'] = $trait['Type'];
      chado_insert_record('cvtermprop', $unit_type);
    }
  
    // Relate the trait with the method.
    // Method ABC (object) is used in/by Trait XYZ (subject).
    $trait_method_relationship = [
      'subject_id' => $rec_trait->cvterm_id,
      'type_id' => $this->terms['method_to_trait_relationship_type'],
      'object_id' => $rec_method->cvterm_id
    ];

    $rec_trait_method = chado_select_record('cvterm_relationship', ['cvterm_relationship_id'], $trait_method_relationship);
    if (!$rec_trait_method) {
      chado_insert_record('cvterm_relationship', $trait_method_relationship);
    }
    
    // Relate the method with the unit.
    // Method ABC (subject) is measuring Unit EFG (object).
    $method_unit_relationship = [
      'subject_id' => $rec_method->cvterm_id,
      'type_id' => $this->terms['unit_to_method_relationship_type'],
      'object_id' => $rec_unit->cvterm_id
    ];

    $rec_method_unit = chado_select_record('cvterm_relationship', ['cvterm_relationship_id'], $method_unit_relationship);
    if (!$rec_method_unit) {
      chado_insert_record('cvterm_relationship', $method_unit_relationship);
    }

    // Return the created trait, method and unit.
    return [
      'trait' => $rec_trait,
      'method' => $rec_method,
      'unit' => $rec_unit
    ];
  }
}
