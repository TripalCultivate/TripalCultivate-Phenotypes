<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Provides setters focused for setting a project used by the importer
 * to package datasets, and getter to retrieve the set value.
 */ 
trait Project {
  /**
   * The key used by the setter method to create a project element 
   * in the context array, as well as the key used by the getter method 
   * to reference and retrieve the project element value.
   * 
   * @var string
   */
   private string $trait_key = 'project';

  /**
   * Sets a single project for use by a validator.
   *
   * Note: This project must exist in the chado.project table already and as long as it
   * does both the project_id and project name will be saved for use by the validator.
   *
   * @param string|int $project
   *   A string value is a project name (project.name), whereas an integer value
   *   is a project id number (project.project_id).
   * 
   * @return void
   * 
   * @throws \Exception
   *  - Project name is an empty string value if project name is provided (string data type parameter).
   *  - Project id is 0 if project id is provided (integer data type parameter).
   */
  public function setProject(string|int $project) {
    
    // Determine if the value provided to the parameter is a project name (string)
    // or a project id number (integer).
    if (is_numeric($project)) {
      // Project id number.
      if ($project <= 0) {
        throw new \Exception('The Project Trait requires project id number to be a number greater than 0.');  
      }

      // Look up the project id to retrieve the project name.
      $project_rec = ChadoProjectAutocompleteController::getProjectName($project);
      
      $set_project = [
        'id' => $project,       // Id
        'name' => $project_rec, // Name
      ];
    }
    else {
      // Project name.
      if (trim($project) === '') {
        throw new \Exception('The Project Trait requires project name to be a non-empty string value.');  
      }
      
      // Look up the project name to retrieve the project id number.
      $project_rec = ChadoProjectAutocompleteController::getProjectId($project);

      $set_project = [
        'id' => $project_rec,   // Id
        'name' => $project,     // Name
      ];
    }
    
    if ($set_project['id'] <= 0 || empty($set_project['name'])) {
      throw new \Exception('The Project Trait requires a project that exists in the database.');
    }

    $this->context[ $this->trait_key ] = $set_project; 
  }

  /**
   * Returns a single project which has been verified to exist by the setter for use
   * by a validator.
   *
   * @return array
   *   The project set by the setter method. The project includes the project id number 
   *   and project name keyed by id and name, respectively.
   * 
   * @throws \Exception
   *  - If the 'project' key does not exist in the context array 
   *    (ie. the project element has NOT been set).
   */
  public function getProject() {
    
    if (array_key_exists($this->trait_key, $this->context)) {
      return $this->context[ $this->trait_key ];
    }
    else {
      throw new \Exception('Cannot retrieve project from the context array as one has not been set by setProject() method.');
    }
  }
}
