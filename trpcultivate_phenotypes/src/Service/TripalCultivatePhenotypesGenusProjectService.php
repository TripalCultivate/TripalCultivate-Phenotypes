<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Genus Project service definition.
 */

 namespace Drupal\trpcultivate_phenotypes\Service;

 use \Drupal\Core\Config\ConfigFactoryInterface;
 use \Drupal\tripal_chado\Database\ChadoConnection;
 use \Drupal\tripal\Services\TripalLogger;
 
 /**
 * Class TripalCultivatePhenotypesGenusProjectService.
 */
class TripalCultivatePhenotypesGenusProjectService {
  /**
   * Configuration terms.genus.
   */
  private $sysvar_genus;

  /**
   * Configuration genus.ontology.
   */
  private $sysvar_genusontology;

  /**
   * Chado database and Tripal logger.
   */
  protected $chado;
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ChadoConnection $chado, TripalLogger $logger) {
    // Module Configuration variables.
    $module_settings = 'trpcultivate_phenotypes.settings';
    $config = $config_factory->getEditable($module_settings);

    // Configuration terms.genus.
    $this->sysvar_genus = $config->get('trpcultivate.phenotypes.ontology.terms.genus');
    // Configuration genus.ontology.
    $this->sysvar_genusontology = $config->get('trpcultivate.phenotypes.ontology.cvdbon');

    // Chado database.
    $this->chado = $chado;

    // Tripal Logger service.
    $this->logger = $logger;
  }
  
  /**
   * Assign a genus to an experiment/project.
   * 
   * @param int $project
   *   Project (project id number) the parameter $genus will be assigned to.
   * @param string $genus
   *   Genus name/title.
   * @param boolean $replace
   *   True to replace existing genus of a project with a different genus.
   *   Default to False.
   *
   * @return boolean
   *   True, genus was set successfully or false on error/fail.
   */
  function setGenusToProject($project, $genus, $replace = FALSE) {
    return 0;
  }

  /**
   * Get genus of an experiment/project.
   * 
   * @param int $project
   *   Project (project_id number) to search.
   *
   * @return array
   *   Keys is genus/organism id number and value is the genus name/title.    
   */
  public function getGenusOfProject($project) {
    $genus_project = 0;

    if ($project > 0) {
      $result = $this->chado->select('1:projectprop', 'prop')
        ->condition('prop.project_id', $project, '=')
        ->condition('prop.type_id', $this->sysvar_genus, '=')
        ->fields('prop', ['value'])
        ->rane(0, 1)
        ->execute()
        ->fetchField();
      
      // Resolve genus/organism_id.
      
    }

    return $genus_project;
  }

  /**
   * Get all genus that have been configured (traits, unit, method, database and crop ontology).
   *
   * @return array
   *   An array of genus names, sorted alphabetically.
   */
  public function getActiveGenus() {
    $genus = [];

    if ($this->sysvar_genusontology) {
      $genus_keys = array_keys($this->sysvar_genusontology);

      foreach($genus_keys as $active_genus) {
        // Each genus-ontology configuration variable name was
        // formatted where all spaces were replaced by underscore.
        // Reconstruct original value.
        $genus[] = ucfirst(str_replace('_', ' ', $active_genus));
      }
    }

    return sort($genus);
  }
}