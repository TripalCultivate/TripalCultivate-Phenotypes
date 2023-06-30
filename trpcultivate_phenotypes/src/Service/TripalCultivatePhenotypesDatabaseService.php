<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Database service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

/**
 * Class TripalCultivatePhenotypesDatabaseService.
 */
class TripalCultivatePhenotypesDatabaseService {
  
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
   * Get all database records.
   * 
   * @param return 
   *   Array, where key is db id and the value is the db name.
   */
  public function getDatabase() {
    $db = null;

    $query = "SELECT db_id, name FROM {1:db} ORDER BY name ASC";
    $result = $this->chado->query($query);

    if ($result) {
      $db = $result->fetchAllKeyed(0, 1);
    }


    return $db;
  }
}