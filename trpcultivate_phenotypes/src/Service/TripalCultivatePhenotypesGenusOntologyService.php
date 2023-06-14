<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Genus Ontology service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\Core\Config\ConfigFactoryInterface;
use \Drupal\tripal_chado\Database\ChadoConnection;
use \Drupal\tripal\Services\TripalLogger;

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesGenusOntologyService {
  
  /**
   * Chado DB, module configuration and logger.
   */
  protected $config;
  protected $chado;
  protected $logger;

  /**
   * Holds genus - ontology configuration variable.
   */
  private $genus_ontology;

  /**
   * Configuration hierarchy for configuration: cvdbon.
   */
  private $sysvar_ontology;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ChadoConnection $chado, TripalLogger $logger) {
    // Configuration.
    $this->sysvar_ontology = 'trpcultivate.phenotypes.ontology.cvdbon';
    $module_settings = 'trpcultivate_phenotypes.settings';
    $this->config = $config_factory->getEditable($module_settings);
    
    // Chado database.
    $this->chado = $chado;
    
    // Tripal Logger service.
    $this->logger = $logger;

    // Define all default terms.
    $this->genus_ontology = $this->defineGenusOntology();
  }

  /**
   * Fetch all genus from chado.organism in the host site and construct a genus ontology
   * configuration values described above. Each genus will contain a configuration value 
   * for trait+unit+method, database and crop ontology.
   *
   * @return array
   *    Associative array where each element is keyed by genus and configuration values
   *    for trait, unit, method, database and crop ontology stored in a array as the value.
   * 
   *    ie: [genus_a] = [
   *           trait,
   *           unit,
   *           method,
   *           database,
   *           crop_ontology
   *        ];
   */
  public function defineGenusOntology() {
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
   * Register a configuration entry and set each genus ontology configuration values to
   * a default value of 0 (not set).
   *
   * @return boolean
   *   True all genus ontology configuration created and set a default value, False on error.
   */
  public function loadGenusOntology() {
    $error = 1;

    if (!empty($this->genus_ontology)) {
      $genus_ontology_configvars = [];
      // Default value of all configuration variable.
      // Not set.
      $default_value = 0;  

      foreach($this->genus_ontology as $genus => $vars) {
        // Create an array keyed by the genus.
        // Genus from genus_ontology property has been sanitized
        // upon definition in the constructor.
        $genus_ontology_configvars[ $genus ] = [];
        
        // Create configuration vars traits, unit, method, database and crop ontology.
        foreach($vars as $var) {
          $genus_ontology_configvars[ $genus ][ $var ] = $default_value;
        }

        // At this point each genus now has configuration vars and
        // ready to register a configuration entry.
        // configuration ...cvdbon.genus.genus [trait, unit, methid, database, crop_ontology]
        $this->config
          ->set($this->sysvar_ontology  . '.' . $genus, $genus_ontology_configvars[ $genus ]);
      }

      $this->config->save();
      $error = 0;
    }

    return ($error) ? FALSE : TRUE;
  }

  /**
   * Remove any formatting from a string and convert space to underscore
   * 
   * @param $genus
   *   String, genus.
   * 
   * @return string
   *   Genus name where all leading and trailing spaces removed and
   *   in word (multi-word genus) spaces replaced by an underscore.
   */
  public function formatGenus($genus) {
    return (empty($genus)) ? null : str_replace(' ', '_', strtolower(trim($genus)));
  }
}