<?php

/**
 * @file
 * Provides Autocomplete Project.
 */

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the Project autocomplete.
 */
class TripalCultivatePhenotypesProjectAutocompleteController extends ControllerBase {
  /**
   * Controller method, autocomplete experiments.
   * 
   * @param Request request
   * @param int $count
   *   Desired number of matching names to suggest.
   *   Default to 5 items.
   * @param int $type
   *   Project type set in projectprop.type_id to restrict projects
   *   to specific type.
   *   Default to 0, return project regardless of type.
   * 
   * @return Json Object
   *   Matching experiment rows where project id and project name
   *   as the row key and value, respectively.
   */
  public function handleAutocomplete(Request $request, int $count = 5, int $type_id = 0) {
    // Array to hold matching experiment records.
    $response = null;
    
    if ($request->query->get('q')) {
      // Get typed in string input from the URL.
      $string = trim($request->query->get('q'));

      if (strlen($string) > 1 && $count > 0) {
        // Proceed to autocomplete when string is at least 2 characters
        // long and result count is set to a value greater than 0.

        // Transform string as a search keyword pattern.
        $keyword = '%' . strtolower($string) . '%';

        if ($type_id > 0) {
          // Restrict to type provided by route parameter.
          $sql = "SELECT name FROM {1:project} AS p LEFT JOIN {1:projectprop} AS t USING (project_id)
            WHERE p.name LIKE :keyword AND t.type_id = :type_id ORDER BY p.name ASC LIMIT %d";

          $args = [':keyword' => $keyword, ':type_id' => $type_id];
        }
        else {
          // Match projects regardless of type.
          $sql = "SELECT name FROM {1:project} AS p 
            WHERE p.name LIKE :keyword ORDER BY p.name ASC LIMIT %d";
          
          $args = [':keyword' => $keyword];
        }

        $query = sprintf($sql, $count);

        // Prepare Chado database connection and execute sql query by providing value 
        // for :keyword placeholder text.
        $connection = \Drupal::service('tripal_chado.database');
        $results = $connection->query($query, $args);
        $results->allowRowCount = TRUE;

        // Compose response result.
        if ($results->rowCount()) {
          foreach($results as $record) {
            $response[] = [
              'value' => $record->name, // Value returned and value displayed by textfield.
              'label' => $record->name  // Value shown in the list of options.
            ];
          }
        }
      }  
    }

    return new JsonResponse($response);
  }

  /**
   * Fetch the project id number given a project name value.
   * 
   * @param string $project
   *   Project name value.
   * 
   * @return integer
   *   Project id number of the project name or 0 if no project was found.
   */
  public static function getProjectId(string $project): int {
    $id = 0;

    if (strlen($term) > 0) {
      $sql = "SELECT project_id AS id FROM {1:project} WHERE name = :name LIMIT 1";

      $connection = \Drupal::service('tripal_chado.database');
      $result = $connection->query($sql, [':name' => $project]);
      $result->allowRowCount = TRUE;

      if ($result->rowCount > 0) {
        $id = $result->fetchField();
      }
    }

    return $id;
  }

  /**
   * Fetch the project name number given a project id value.
   * 
   * @param int $project
   *   Project id number value.
   * 
   * @return integer
   *   Project name of the project id number or empty string if no project was found.
   */
  public static function getProjectName(int $project): string {
    $name = '';

    if ($project > 0) {
      $sql = "SELECT name FROM {1:project} WHERE project_id = :project_id LIMIT 1";

      $connection = \Drupal::service('tripal_chado.database');
      $result = $connection->query($sql, ['project_id' => $project]);
      $result->allowRowCount = TRUE;

      if ($result->rowCount > 0) {
        $name = $result->fetchField();
      }
    }

    return $name;
  }
}