<?php

/**
 * @file
 * Controller to fetch project genus.
 */

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class definition TripalCultivatePhenotypesProjectGenusController.
 */
class TripalCultivatePhenotypesProjectGenusController extends ControllerBase {
  public function getProjectGenus() {
    $result = '';
    // Get the input value from the AJAX request which is the
    // name of the project not the project id. 
    $project = \Drupal::request()->request->get('value');

    if (!empty($project)) {
      // Resolve the project name to the project id number.
      $rec_project = chado_select_record('project', ['project_id'], ['name' => $project]);
      
      if ($rec_project[0]) {
        $project_id = $rec_project[0]->project_id;

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