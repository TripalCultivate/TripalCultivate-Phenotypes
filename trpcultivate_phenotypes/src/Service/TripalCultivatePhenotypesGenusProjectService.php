<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Genus Project service definition.
 */

 namespace Drupal\trpcultivate_phenotypes\Service;

 use \Drupal\Core\Config\ConfigFactoryInterface;
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
   * Tripal logger.
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TripalLogger $logger) {
    // Module Configuration variables.
    $module_settings = 'trpcultivate_phenotypes.settings';
    $config = $config_factory->getEditable($module_settings);

    // Configuration terms.genus.
    $this->sysvar_genus = $config->get('trpcultivate.phenotypes.ontology.terms.genus');
    
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
   *    
   */
  public function getGenusOfProject($project) {
    return 0;
  }

  /**
   * Get all genus that have been configured (traits, unit, method, database and crop ontology).
   *
   * @return array
   *   An array of genus names, sorted alphabetically.
   */
  public function getActiveGenus() {
    return 0;
  }
}