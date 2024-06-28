<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Validate Project.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_project",
 *   validator_name = @Translation("Project Validator"),
 *   validator_scope = "PROJECT",
 * )
 */
class Project extends TripalCultivatePhenotypesValidatorBase {
  /**
   * Validate items in the phenotypic data upload assets.
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validate() {
    // Validate ...
    $validator_status = [
      'title' => 'Project/Experiment Exists',
      'status' => 'pass',
      'details' => ''
    ];

    // Instructed to skip this validation. This will set this validator as upcoming or todo.
    // This happens when other prior validation failed and this validation could only proceed
    // when input values have been rectified.
    if ($this->skip) {
      $validator_status['status'] = 'todo';
      return $validator_status;
    }

    // Project:
    //   - Is not empty
    //   - Exists in chado.project

    if (empty($this->project)) {
      $validator_status['status']  = 'fail';
      $validator_status['details'] = 'Project/Experiment field is empty. Please enter a value and try again.';
    }
    else {
      // Has a project, check if it existed in chado.projects table.
      $project_id = ChadoProjectAutocompleteController::getProjectId($this->project);

      if (!$project_id) {
        $validator_status['status']  = 'fail';
        $validator_status['details'] = 'Project/Experiment does not exist. Please enter a value and try again.';
      }
    }

    return $validator_status;
  }
}
