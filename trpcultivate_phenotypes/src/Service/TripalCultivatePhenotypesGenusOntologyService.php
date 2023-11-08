<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Genus Ontology service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;

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
  private $sysvar_genus_ontology;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ChadoConnection $chado, TripalLogger $logger) {
    // Configuration.
    $this->sysvar_genus_ontology = 'trpcultivate.phenotypes.ontology.cvdbon';
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
        // configuration ...cvdbon.genus.genus [trait, unit, method, database, crop_ontology]
        $this->config
          ->set($this->sysvar_genus_ontology  . '.' . $genus, $genus_ontology_configvars[ $genus ]);
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

  /**
   * Save genus ontology configuration values.
   * 
   * @param array $config_values
   *   Configuration values submitted from a form implementation.
   *   Each element is keyed by the genus. A value of an associative array 
   *   for each genus key contains the following configuration variables:
   *
   *   trait, unit, method, database and crop_ontology
   * 
   *   ie: $config_values[ genus ] = [
   *     'trait' => form field for trait value,
   *     'unit'    => form field for unit value,
   *     'method'    => form field for method value,
   *     'database'    => form field for database value,
   *     'crop_ontology' => form field for crop_ontology value
   *   ],
   *   ...
   *
   * @return boolean
   *   True, configuration saved successfully and False on error. 
   */
  public function saveGenusOntologyConfigValues($config_values) {
    $error = 0;

    if (!empty($config_values) && is_array($config_values)) {
      // Make sure genus key exists.
      $genus_keys = array_keys($this->genus_ontology);

      foreach($config_values as $genus => $values) {
        $genus_key = $this->formatGenus($genus);
        
        if (in_array($genus_key, $genus_keys)) {
          // A valid genus key. Test each configuration variables
          // and allow only configuration name that matches genus ontology 
          // configuration schema definition.
          $genus_ontology_values = [];
          
          foreach($values as $config_name => $config_value) {
            if (in_array($config_name, $this->genus_ontology[ $genus_key ])) {
              // Save.
              $genus_ontology_values[ $config_name ] = $config_value;
            }
            else {
              // Not expecting this configuration name.
              $this->logger->error('Error. Failed to save configuration. Unexpected configuration name: ' . $config_name);
              $error = 1;
              break; break;
            }
          }
          
          // Stage a genus ontology configuration - ready for saving.
          $this->config
            ->set($this->sysvar_genus_ontology . '.' . $genus_key, $genus_ontology_values);
        }
        else {
          // Genus key not found.
          $this->logger->error('Error. Failed to save configuration. Unexpected genus: ' . $genus);
          $error = 1;
          break;
        }
      }

      if ($error == 0) {
        $this->config->save();
      }
    }
    else {
      $error = 1;
    }

    return ($error) ? FALSE : TRUE;
  }

  /**
   * Get genus ontology configuration values.
   *
   * @param string $genus
   *   Genus
   *
   * @return array
   *   Associated genus configuration values trait, unit, method, database and crop ontology.
   */
  public function getGenusOntologyConfigValues($genus) {
    $config_values = 0;
    
    if ($genus) {
      $genus_keys = array_keys($this->genus_ontology);
      $genus_key = $this->formatGenus($genus);

      if (in_array($genus_key, $genus_keys)) {
        $config_values = $this->config
          ->get($this->sysvar_genus_ontology . '.' . $genus_key);
      }
    }

    return $config_values;
  }
}