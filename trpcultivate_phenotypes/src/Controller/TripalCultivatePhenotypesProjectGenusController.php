<?php

/**
 * @file
 * Controller to fetch project genus.
 */

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Class definition TripalCultivatePhenotypesProjectGenusController.
 */
class TripalCultivatePhenotypesProjectGenusController extends ControllerBase {
  /**
   * AJAX callback to fetch the genus of a project.
   */
  public function getProjectGenus() {
    $result = '';
    // Get the input value from the AJAX request which is the
    // name of the project not the project id. 
    $project = \Drupal::request()->request->get('project');

    if (!empty($project)) {
      // Resolve the project name to the project id number.
      $project_id = ChadoProjectAutocompleteController::getProjectId($project);
      
      if ($project_id > 0) {
        // Fetch the genus.
        $project_genus = \Drupal::service('trpcultivate_phenotypes.genus_project')
          ->getGenusOfProject($project_id);
        
        if ($project_genus) {
          $result = $project_genus['genus'];
        }
      }
    }

    return new JsonResponse($result);
  }
}