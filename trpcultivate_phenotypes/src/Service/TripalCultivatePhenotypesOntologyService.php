<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Ontology service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesOntologyService {
  
  /**
   * Chado DB and Module configuration.
   */
  protected $config_read;
  protected $config_edit;
  protected $chado;

  /**
   * Holds genus - ontology configuration variable.
   */
  private $genus_ontology;

  /**
   * Configuration heirarchy for ontology.
   */
  private $sysvar_ontology;

  /**
   * Tripal Logger Service.
   */
  private $logger;

  /**
   * Constructor.
   */
  public function __construct() {
    // Immutable and editable configuration.
    $module_settings   = 'trpcultivate_phenotypes.settings';
    $this->config_read = \Drupal::config($module_settings);
    $this->config_edit = \Drupal::configFactory()->getEditable($module_settings);
    
    // Chado database.
    $this->chado = \Drupal::service('tripal_chado.database');
    
    // Define all default terms.
    $this->genus_ontology = $this->defineGenusOntology();

    // Configuration hierarchy for terms.
    $this->sysvar_ontology = 'trpcultivate.phenotypes.ontology.cvdbon';

    // Tripal Logger service.
    $this->logger = \Drupal::service('tripal.logger');
  }

  /**
   * Construct genus-ontology based configuration variable names
   * (cv+method+unit, database and crop ontology).
   */
  public function defineGenusOntology() {
    // Each genus will contain:
    // - A cv+method+unit configuration.
    // - A db configuration.
    // - A crop ontology configuration.
    $genus_ontology = [];

    // Fetch genus in host site.
    $query = "SELECT genus FROM {1:organism} GROUP BY genus ORDER BY genus ASC";
    $result = $this->chado->query($query);

    if ($result) {
      foreach($result as $genus) {
        // genus-ontology configuration.
        $config_genus = $this->formatGenus($genus->genus);

        $genus_ontology[ $config_genus ] = [
          'trait',
          'method',
          'unit',
          'database',
          'crop_ontology'
        ];
      }
    }


    return $genus_ontology;
  }

  /**
   * Create genus ontology configuration values.
   * 
   * @param $value
   *   Integer, value to set each genus ontology configuration.
   *   Default to 0, on install each configuration will be set to this value.
   * 
   * @return boolean
   *   True if genus ontology configuration values were set to a value.
   */
  public function loadGenusOntology($value = 0) {
    $config_genus_ontology = [];

    foreach($this->genus_ontology as $genus => $vars) {
      $config_genus_ontology[ $genus ] = [];

      foreach($vars as $var) {
        $config_genus_ontology[ $genus ][ $var ] = $value;
      }

      // Set a value to each genus ontology configuration.
      $this->config_edit
        ->set($this->sysvar_ontology . '.' . $genus, $config_genus_ontology[ $genus ]);
    }

    $this->config_edit
      ->save();

 
    return TRUE;
  }

  /**
   * Get genus ontology configuration variable set.
   * 
   * @param $genus
   *   String, genus.
   * 
   * @return configuration array
   *   cv, method, unit, db and crop ontology configuration variable and
   *   current value held by each variable.
   */
  public function getGenusOntologyConfigValue($genus) {
    $value = null;
    $genus = $this->formatGenus($genus);

    if ($genus && in_array($genus, array_keys($this->genus_ontology))) {
      $config_name = $genus;

      $value = $this->config_read->get($this->sysvar_ontology . '.' . $config_name);
    }

    return $value;
  }

  /**
   * Remove any formatting from a string and convert space to underscore
   */
  public function formatGenus($genus) {
    return str_replace(' ', '_', strtolower(trim($genus)));
  }
}