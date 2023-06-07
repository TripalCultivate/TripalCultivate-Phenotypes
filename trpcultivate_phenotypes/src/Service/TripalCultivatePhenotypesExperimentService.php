<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Experiments/Projects service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

/**
 * Class TripalCultivatePhenotypesExperimentService.
 */
class TripalCultivatePhenotypesExperimentService {
  
  /**
   * Chado Db.
   */
  protected $chado;

  /**
   * Constructor.
   */
  public function __construct() {
    // Chado database.
    $this->chado = \Drupal::service('tripal_chado.database');
  }

  /**
   * Get all experiment records.
   * 
   * @param return 
   *   Array, where key is project id and the value is the project name.
   */
  public function getExperiments() {
    $experiments = [];

    $query = "SELECT project_id, name FROM {1:project} ORDER BY name ASC";
    $result = $this->chado->query($query);

    if ($result) {
      $project = $result->fetchAllKeyed(0, 1);
    }

    
    return $db;
  }
}