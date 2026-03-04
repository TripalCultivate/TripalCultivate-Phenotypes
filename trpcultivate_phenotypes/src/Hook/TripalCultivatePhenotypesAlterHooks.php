<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Phenotypes module alter hooks.
 */
class TripalCultivatePhenotypesAlterHooks {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $drupaldb_connection;

  /**
   * Drupal current route match interface.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $current_routematch;

  /**
   * Construct alter hooks.
   *
   * @param \Drupal\Core\Database\Connection $drupaldb_connection
   *   Drupal database connection.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_routematch
   *   Current route match service.
   */
  public function __construct(Connection $drupaldb_connection, RouteMatchInterface $current_routematch) {

    $this->drupaldb_connection = $drupaldb_connection;
    $this->current_routematch = $current_routematch;
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   *
   * NOTE: this only removes the delete tab/option. The functionality to
   * delete using the delete entity route may still be used.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name, RefinableCacheableDependencyInterface &$cacheability) {

    // Remove the Delete tab if research experiment has phenotypes.
    // Apply this delete tab restriction to the following Tripal content types.
    $content_types = [
      'research_experiment',
    ];

    $page_parameters = $this->current_routematch->getParameters();

    if ($tripal_entity = $page_parameters->get('tripal_entity')) {
      if (in_array($tripal_entity->bundle(), $content_types)) {
        $research_experiment = $tripal_entity->get('exp_name')->getValue()[0];

        $has_pheno = $drupaldb_connection
          ->select('trpcultivate_phenocombo', 'tc')
          ->condition('tc.project_id', $research_experiment['record_id'], '=')
          ->countQuery()
          ->execute()
          ->fetchField();

        if ($has_pheno) {
          $data['tabs'][0]['entity.tripal_entity.delete_form'] = [];
        }
      }
    }
  }

}
