<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;

/**
 * Phenotypes genus ontology service.
 */
class TripalCultivatePhenotypesGenusOntologyService {

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Tripal logger service.
   *
   * @var Drupal\tripal\Services\TripalLogger
   */
  protected $logger;

  /**
   * Holds genus - ontology configuration variable.
   *
   * @var null
   */
  private $genus_ontology = NULL;

  /**
   * Configuration hierarchy for configuration: cvdbon.
   *
   * @var string
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
    $this->chado_connection = $chado;

    // Tripal Logger service.
    $this->logger = $logger;
  }

  /**
   * Fetch all genus from chado.organism in the host site.
   *
   * For each organism, initialize an empty template array. 
   * This array will be used by other genus ontology configuration
   * methods, ie. methods in this service.
   *
   * NOTE: There are no actual configuration values being populated here.
   *
   * @return array
   *   Associative array where each element is keyed by genus and configuration
   *   values for trait, unit, method, database and crop ontology stored in a
   *   array as the value.
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
    $result = $this->chado_connection->query($query);

    if ($result) {
      foreach ($result as $genus) {
        // Create a genus-ontology configuration string identifier.
        $config_genus = $this->formatGenus($genus->genus);

        $genus_ontology[$config_genus] = [
          'trait',
          'method',
          'unit',
          'database',
          'crop_ontology',
        ];
      }
    }

    return $genus_ontology;
  }

  /**
   * Register a configuration entry.
   *
   * Uses the template created by self::defineGenusOntology() to set each
   * configuration value (e.g. trait, method, unit) to its
   * default value (i.e. 0 which indicates not set) for all unique genus
   * in the chado.organism table.
   *
   * @return bool
   *   True if all genus ontology configuration entries were created and set to
   *   a default value, False on error.
   */
  public function loadGenusOntology() {

    // If we haven't defined the genus ontology terms yet,
    // then do that first.
    if (empty($this->genus_ontology)) {
      $this->genus_ontology = $this->defineGenusOntology();
    }

    $genus_ontology_configvars = [];
    // Default value of all configuration variable.
    // Not set.
    $default_value = 0;

    foreach ($this->genus_ontology as $genus => $vars) {
      // Create an array keyed by the genus.
      // Genus from genus_ontology property has been sanitized
      // upon definition in the constructor.
      $genus_ontology_configvars[$genus] = [];

      // Create configuration vars traits, unit, method, database and
      // crop ontology.
      foreach ($vars as $var) {
        $genus_ontology_configvars[$genus][$var] = $default_value;
      }

      // At this point each genus now has configuration vars and
      // ready to register a configuration entry.
      // configuration ...cvdbon.genus.genus
      // [trait, unit, method, database, crop_ontology].
      $this->config
        ->set($this->sysvar_genus_ontology . '.' . $genus, $genus_ontology_configvars[$genus]);
    }

    $this->config->save();

    return TRUE;
  }

  /**
   * Remove any formatting from a string and convert space to underscore.
   *
   * @param string $genus
   *   Genus name.
   *
   * @return string
   *   Genus name where all leading and trailing spaces removed and
   *   in word (multi-word genus) spaces replaced by an underscore.
   */
  public function formatGenus($genus) {
    return (empty($genus)) ? NULL : str_replace(' ', '_', strtolower(trim($genus)));
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
   * @return bool
   *   True if configuration saved successfully and False on error.
   */
  public function saveGenusOntologyConfigValues($config_values) {
    $error = 0;

    // If we haven't defined the genus ontology terms yet,
    // then do that first.
    if (empty($this->genus_ontology)) {
      $this->genus_ontology = $this->defineGenusOntology();
    }

    if (!empty($config_values) && is_array($config_values)) {
      // Make sure genus key exists.
      $genus_keys = array_keys($this->genus_ontology);

      foreach ($config_values as $genus => $values) {
        $genus_key = $this->formatGenus($genus);

        if (in_array($genus_key, $genus_keys)) {
          // A valid genus key. Test each configuration variables
          // and allow only configuration name that matches genus ontology
          // configuration schema definition.
          $genus_ontology_values = [];

          foreach ($values as $config_name => $config_value) {
            if (in_array($config_name, $this->genus_ontology[$genus_key])) {
              // Save.
              $genus_ontology_values[$config_name] = $config_value;
            }
            else {
              // Not expecting this configuration name.
              $this->logger->error('Error. Failed to save configuration. Unexpected configuration name: ' . $config_name);
              $error = 1;
              break 2;
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
   *   Genus name.
   *
   * @return array
   *   Associated genus configuration values trait, unit, method, database and
   *   crop ontology.
   */
  public function getGenusOntologyConfigValues($genus) {
    $config_values = 0;

    // If we haven't defined the genus ontology terms yet,
    // then do that first.
    if (empty($this->genus_ontology)) {
      $this->genus_ontology = $this->defineGenusOntology();
    }

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

  /**
   * Retrieve a sorted list of all configured genus.
   *
   * @return array
   *   Array where the key and value are the genus.
   */
  public function getConfiguredGenusList() {
    // Fetch all unique genus.
    $query = "SELECT genus FROM {1:organism} GROUP BY genus ORDER BY genus ASC";
    $result = $this->chado_connection->query($query);

    // Array to hold active genus.
    $active_genus = [];

    foreach ($result as $genus) {
      $genus = $genus->genus;
      $genus_key = $this->formatGenus($genus);

      // Test if a genus is active by using the trait configuration.
      $config_trait = $this->config
        ->get($this->sysvar_genus_ontology . '.' . $genus_key . '.' . 'trait');

      if ($config_trait && $config_trait > 0) {
        $active_genus[] = $genus;
      }
    }

    return $active_genus;
  }

}
