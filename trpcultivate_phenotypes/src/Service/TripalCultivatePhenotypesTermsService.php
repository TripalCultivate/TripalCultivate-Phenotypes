<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Terms service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\Core\Config\ConfigFactoryInterface;
use \Drupal\tripal\Services\TripalLogger;


/**
 * Class TripalCultivatePhenotypesTermsService.
 */
class TripalCultivatePhenotypesTermsService {
  /**
   * Module configuration.
   */
  protected $config;

  /**
   * Holds configuration variable names
   * and terms it maps to.
   */
  private $terms;

  /**
   * Configuration hierarchy for configuration: terms.
   */
  private $sysvar_terms;

  /**
   * Tripal logger.
   */
  protected $logger;


  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TripalLogger $logger) {
    // Configuration terms.
    $this->sysvar_terms = 'trpcultivate.phenotypes.ontology.terms';

    // Module Configuration variables.
    $module_settings = 'trpcultivate_phenotypes.settings';
    $this->config = $config_factory->getEditable($module_settings);

    // Tripal Logger service.
    $this->logger = $logger;

    // Prepare array of default terms from configuration definition.
    $this->terms = $this->defineTerms();
  }

  /**
   * Define terms.
   * Each term set is defined using the array structure below:
   * @see config/schema for ontology terms - default_terms
   * Format:
   *   cv - 1 name
   *   cv - 1 definition
   *   terms
   *     config_map // Maps to which configuration variable.
   *     term - 1 name
   *     term - 1 id
   *     term - 1 definition
   *
   *     config_map
   *     term - 2 name
   *     term - 2 id
   *     term - 2 definition
   *
   *     ....
   *   ...
   *
   * @return array
   *   All configuration entity values keyed by configuration map value.
   */
  public function defineTerms() {
    $terms =[];

    // Fetch all terms in the terms config_entity and prepare an associative array
    // where each element is keyed by the configuration map value.
    $default_terms = $this->config->get('trpcultivate.default_terms.term_set');

    foreach($default_terms as $i => $cv) {
      foreach($cv['terms'] as $term_set) {
        // Add the cv information of the term.
        $term_set['cv'] = $cv;
        // Access a term by configuration map value.
        // ie: term['experiment_container']
        $terms[ $term_set['config_map'] ] = $term_set;
      }
    }

    return $terms;
  }

  /**
   * Insert and create term configuration variable.
   *
   * @return boolean
   *   True if all terms were inserted successfully and false otherwise.
   */
  public function loadTerms($schema = 'chado') {
    // Error flag is 0 - no error, all passes prove otherwise.
    $error = 0;
    $terms = $this->terms;

    if ($terms) {
      // Install terms.
      foreach($terms as $config_map => $config_prop) {
        $cvterm_row = [
          'name' => $config_prop['name'],
          'cv_id' => ['name' => $config_prop['cv']['name']]
        ];

        // Check if the term exists.
        $cvterm = chado_get_cvterm($cvterm_row, [], $schema);

        if (!$cvterm) {
          // No match of this term in the database, see if cv exists.
          $cv_row = [
            'name' => $config_prop['cv']['name']
          ];

          $cv_id = chado_get_cv($cv_row, [], $schema);

          if (!$cv_id) {
            // No match of this cv in the database. Create record.
            $cv_id = chado_insert_cv($cv_row['name'], $config_prop['cv']['definition'], [], $schema);

            if (!$cv_id) {
              // Error inserting cv.
              $error = 1;
              $this->logger->error('Error. Could not insert cv.');
            }
          }

          // Insert the term.
          unset($config_prop['cv']);
          $cvterm = chado_insert_cvterm($config_prop, [], $schema);
        }

        // Set the term id as the configuration value of the
        // term configuration variable.
        $this->config
          ->set($this->sysvar_terms . '.' . $config_map, $cvterm->cvterm_id);
      }

      // Save all configuration values.
      $this->config->save();
    }

    return ($error) ? FALSE : TRUE;
  }

  /**
   * Retrieves the ID of the term configured for a specific role.
   *
   * It is expected that the administrator can configure these terms. As such
   * this method will pull the value from configuration rather then look it up
   * in the database.
   *
   * @param string $term_key
   *   The unique identifier for the term of interest. This should be one of:
   *   data_collector: Data Collector.
   *   entry: Entry Number/Information.
   *   genus: Organism.
   *   location: Location.
   *   method: Collection Method.
   *   name: Name/Germplasm line.
   *   experiment_container: Plot.
   *   unit_to_method_relationship_type: Related - create relationships (unit - method).
   *   method_to_trait_relationship_type: Related - create relationships (method - trait).
   *   experiment_replicate: Planting replicate.
   *   unit: Unit of measurement.
   *   experiment_year: Year.
   * @see schema/trpcultivate_phenotypes.schema.yml for detailed
   *   description of each configuration variable name.
   *
   * @return integer
   *   The chado cvterm_id for the term associated with that key.
   *   0 if non-existent configuration name/key.
   */
  public function getTermId(string $term_key) {
    $id = 0;
    $term_key = trim($term_key);

    $valid_term_keys = array_keys($this->terms);
    if (!empty($term_key) && in_array($term_key, $valid_term_keys)) {
      $id = $this->config->get($this->sysvar_terms . '.' . $term_key);
    }

    return $id;
  }

  /**
   * Save term configuration values.
   *
   * @param array $config_values
   *   Configuration values submitted from a form implementation.
   *   Each element is keyed by the field name which is the configuration
   *   variable name and the value being the value as set in
   *   corresponding form field resolved to id number.
   *
   *   ie: $config_values[name] = 1; // Null term, Already resolved to id number.
   *   // name configuration variable name is set to Null term.
   *
   * @return boolean
   *   True, configuration saved successfully and False on error.
   */
  public function saveTermConfigValues($config_values) {
    $error = 0;

    if (!empty($config_values) && is_array($config_values)) {
      $term_keys = array_keys($this->terms);

      foreach($config_values as $config => $value) {
        // Make sure config name exists before saving a value.
        if (in_array($config, $term_keys)) {
          $this->config
            ->set($this->sysvar_terms . '.' . $config, $value);
        }
        else {
          $this->logger->error('Error. Failed to save configuration: ' . $config . '=' . $value);
          $error = 1;
          break;
        }
      }

      // Save all configuration values.
      if ($error == 0) {
        $this->config->save();
      }
    }
    else {
      $error = 1;
    }

    return ($error > 0) ? FALSE : TRUE;
  }
}
