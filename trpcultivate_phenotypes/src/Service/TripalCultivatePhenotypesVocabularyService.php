<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Vocabulary service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

/**
 * Class TripalCultivatePhenotypesVocabularyService.
 */
class TripalCultivatePhenotypesVocabularyService {
  
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
   * Get all vocabulary records.
   * 
   * @param return 
   *   Array, where key is cv id and the value is the cv name.
   */
  public function getVocabularies() {
    $cv = null;

    $query = "SELECT cv_id, name FROM {1:cv} ORDER BY name ASC";
    $result = $this->chado->query($query);

    if ($result) {
      $cv = $result->fetchAllKeyed(0, 1);
    }


    return $cv;
  }
}