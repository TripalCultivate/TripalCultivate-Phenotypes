<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager;
use Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoCvtermBuddy;
use Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoDbxrefBuddy;

/**
 * Phenotypes terms service.
 */
class TripalCultivatePhenotypesTermsService {
  /**
   * The Chado Buddy cvterm.
   *
   * @var Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoCvtermBuddy
   */
  protected ChadoCvtermBuddy $cvterm_buddy;

  /**
   * The Chado Buddy Dbxref.
   *
   * @var Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoDbxrefBuddy
   */
  protected ChadoDbxrefBuddy $dbxref_buddy;

  /**
   * Module configuration.
   *
   * @var config_entity
   */
  private $config;

  /**
   * Holds configuration variable names and terms it maps to.
   *
   * @var array
   */
  private $terms;

  /**
   * Configuration hierarchy for configuration: terms.
   *
   * @var string
   */
  private $sysvar_terms;

  /**
   * Tripal logger service.
   *
   * @var Drupal\tripal\Services\TripalLogger
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TripalLogger $logger,
    ChadoBuddyPluginManager $buddy_manager,
  ) {
    // Configuration terms.
    $this->sysvar_terms = 'trpcultivate.phenotypes.ontology.terms';

    // Module Configuration variables.
    $module_settings = 'trpcultivate_phenotypes.settings';
    $this->config = $config_factory->getEditable($module_settings);

    // Tripal Logger service.
    $this->logger = $logger;

    // Chado cvterm buddy.
    $this->cvterm_buddy = $buddy_manager->createInstance('chado_cvterm_buddy', []);
    // Chado dbxref buddy.
    $this->dbxref_buddy = $buddy_manager->createInstance('chado_dbxref_buddy', []);

    // Prepare array of default terms from configuration definition.
    $this->terms = $this->defineTerms();
  }

  /**
   * Define terms.
   *
   * Each term set is defined using the array structure below:
   * Format:
   *   cv - 1 name
   *   cv - 1 definition
   *   terms
   *     config_map // Maps to which configuration variable.
   *     term - 1 name
   *     term - 1 id
   *     term - 1 definition.
   *
   *     config_map
   *     term - 2 name
   *     term - 2 id
   *     term - 2 definition
   *
   *     ....
   *   ...
   *
   * @see config/schema
   *
   * @return array
   *   All configuration entity values keyed by configuration map value.
   */
  public function defineTerms() {
    $terms = [];

    // Fetch all terms in the terms config_entity and prepare an associative
    // array where each element is keyed by the configuration map value.
    $default_terms = $this->config->get('trpcultivate.default_terms.term_set');

    // Terms are not available as configuration values until job to install
    // terms has been executed. This value is null to start with.
    if ($default_terms) {
      foreach ($default_terms as $cv) {
        foreach ($cv['terms'] as $term_set) {
          // Add the cv information of the term.
          $term_set['cv'] = ['name' => $cv['name'], 'definition' => $cv['definition']];
          // Access a term by configuration map value.
          // ie: term['experiment_container'].
          $terms[$term_set['config_map']] = $term_set;
        }
      }
    }

    return $terms;
  }

  /**
   * Insert and create term configuration variable.
   *
   * @param string $schema
   *   The Chado schema name to use.
   *
   * @return bool
   *   True if all terms were inserted successfully and false otherwise.
   */
  public function loadTerms($schema = NULL) {
    $error = 0;
    $terms = $this->terms;

    if ($terms) {
      if ($schema) {
<<<<<<< Updated upstream
        $this->cvterm_buddy->setSchemaName($schema);
        $this->dbxref_buddy->setSchemaName($schema);
=======
        // $this->cvterm_buddy->connection->setSchemaName($schema);
        // $this->dbxref_buddy->connection->setSchemaName($schema);
>>>>>>> Stashed changes
      }

      // Install terms.
      foreach ($terms as $config_map => $config_prop) {
        [$idspace, $accession] = explode(':', $config_prop['id']);

        // Insert the cvterm only if both cv and db exist.
        // Check that the cv name exists.
        $cv_name = $config_prop['cv']['name'];
        $cv_exists = $this->cvterm_buddy->getCv(['cv.name' => $cv_name], []);
        if (empty($cv_exists)) {
          // Create the cv.
          $this->cvterm_buddy->upsertCv(['cv.name' => $cv_name], []);
        }

        // Check that the db (idspace) exists.
        $db_exists = $this->dbxref_buddy->getDb(['db.name' => $idspace], []);
        if (empty($db_exists)) {
          // Create the db.
          $this->dbxref_buddy->upsertDb(['db.name' => $idspace], []);
        }

        $term_values = [
          'db.name' => $idspace,
          'cv.name' => $config_prop['cv']['name'],
          'dbxref.accession' => $accession,
          'cvterm.name' => $config_prop['name'],
          'cvterm.definition' => $config_prop['definition'],
        ];

        $cvterm_exists = $this->cvterm_buddy->getCvterm(
          [
            'cv.name' => $term_values['cv.name'],
            'cvterm.name' => $term_values['cvterm.name'],
          ],
          []
        );

        if (empty($cvterm_exists)) {
          // Create the cv.
          $chado_cvterm_record = $this->cvterm_buddy->upsertCvterm($term_values, []);
          $cvterm_id = $chado_cvterm_record->getValue('cvterm.cvterm_id');
        }
        else {
          $cvterm_id = $cvterm_exists[0]->getValue('cvterm.cvterm_id');
        }

        // Set the term id as the configuration value of the
        // term configuration variable.
        if ($cvterm_id) {
          $this->config
            ->set($this->sysvar_terms . '.' . $config_map, $cvterm_id);
        }
        else {
          // Error inserting term.
          $error = 1;
          $this->logger->error('Phenotypes Term Service could not insert term: ' . $config_prop['name'] . ' (' . $config_prop['id'] . ')');
        }
      }
    }

    if (!$error) {
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
   *   - data_collector: Data Collector.
   *   - entry: Entry Number/Information.
   *   - genus: Organism.
   *   - location: Location.
   *   - name: Name/Germplasm line.
   *   - experiment_container: Plot.
   *   - unit_to_method_relationship_type: Related - create relationships
   *     (unit - method).
   *   - method_to_trait_relationship_type: Related - create relationships
   *     (method - trait).
   *   - experiment_replicate: Planting replicate.
   *   - unit_type: Unit of measurement.
   *   - experiment_year: Year.
   *   - trait_to_synonym_relationship_type: Prefered term.
   *
   * @see schema/trpcultivate_phenotypes.schema.yml
   *
   * @return int
   *   The chado cvterm_id for the term associated with that key.
   *   0 if non-existent configuration name/key.
   */
  public function getTermId(string $term_key) {
    $id = 0;
    $term_key = trim($term_key);

    if ($term_key && $this->terms) {
      $valid_term_keys = array_keys($this->terms);
      if (!empty($term_key) && in_array($term_key, $valid_term_keys)) {
        $id = $this->config->get($this->sysvar_terms . '.' . $term_key);
      }
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
   *   For example, the following indicates that the chado.cvterm.cvterm_id for
   *   the 'name' configuration variable is '1'. This resolves to the 'Null'
   *   cvterm when that cvterm_id is looked up in the cvterm table.
   *
   * @code
   *   $config_values['name'] = 1;
   * @endcode
   *
   * @return bool
   *   True if configuration saved successfully and False on error.
   */
  public function saveTermConfigValues($config_values) {
    $error = 0;

    if (!empty($config_values) && is_array($config_values) && $this->terms) {
      $term_keys = array_keys($this->terms);

      foreach ($config_values as $config => $value) {
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
