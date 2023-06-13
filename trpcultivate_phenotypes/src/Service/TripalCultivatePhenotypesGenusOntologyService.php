<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Genus Ontology service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\Core\Config\ConfigFactoryInterface;
use \Drupal\tripal_chado\Database\ChadoConnection;

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesOntologyService {
  
  /**
   * Chado DB and Module configuration.
   */
  protected $config;
  protected $chado;

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
  public function __construct(ConfigFactoryInterface $config_factory, ChadoConnection $chado) {
    // Configuration.
    $this->sysvar_ontology = 'trpcultivate.phenotypes.ontology.cvdbon';
    $module_settings = 'trpcultivate_phenotypes.settings';
    $this->config = $config_factory->getEditable($module_settings);
    
    // Chado database.
    $this->chado = $chado
    
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
}