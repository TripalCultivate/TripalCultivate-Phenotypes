<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Provides setters focused for setting a project used by the importer
 * to package datasets and a getter to retrieve the set value.
 */
trait Project {
  // The key used to reference the project set or get from the
  // context property in the parent class.
  private string $trait_key = 'project';

  /**
   * Sets a project.
   *
   * @param string|int $project
   *   A string value is the project name (project.name), whereas an integer value
   *   is the project id number (project.project_id).
   * 
   * @throws \Exception
   *   Project is an empty string or the value 0.
   * 
   * @return void
   */
  public function setProject(string|int $project) {
    // Project must have a value and not 0.
    if (empty($project) || (is_numeric($project) && (int) $project <= 0)) {
      throw new \Exception('Invalid project provided.');
    }

    // Project must exists.
    // Determine what was provided to the project field: project id or name.
    if (is_numeric($project)) {
      // Value is integer. Project id was provided.
      // Test project by looking up the id to retrieve the project name.
      $project_rec = ChadoProjectAutocompleteController::getProjectName((int) $project); 

      $set_project = [
        'id' => $project,
        'name' => $project_rec
      ];
    }
    else {
      // Value is string. Project name was provided.
      // Test project by looking up the name to retrieve the project id.
      $project_rec = ChadoProjectAutocompleteController::getProjectId($project);

      $set_project = [
        'id' => $project_rec,
        'name' => $project
      ];
    }

    if ($project_rec <= 0 || empty($project_rec)) {
      throw new \Exception('The project provided does not exist in chado.project table.');
    }

    $this->context[ $this->trait_key ] = $set_project; 
  }

  /**
   * Returns the project set.
   *
   * @return array
   *   The project set that includes the project id number and project name
   *   keyed by id and name, respectively.
   * 
   * @throws \Exception
   *   If the 'project' key does not exist in the context array (ie. the project
   *   array has NOT been set).
   */
  public function getProject() {
    // The trait key element project should exists in the context property.
    if (!array_key_exists($this->trait_key, $this->context)) {
      throw new \Exception("Cannot retrieve project set by the importer.");
    }

    return $this->context[ $this->trait_key ];
  }
}
