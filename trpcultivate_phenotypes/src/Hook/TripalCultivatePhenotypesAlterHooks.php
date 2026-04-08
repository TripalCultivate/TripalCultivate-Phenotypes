<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\tripal_chado\Database\ChadoConnection;

/**
 * Phenotypes module alter hooks.
 */
class TripalCultivatePhenotypesAlterHooks {

  /**
   * Determine if the research experiment entity has phenotypes.
   *
   * @var bool
   */
  protected bool $has_pheno = FALSE;

  /**
   * Tripal entity definitions.
   *
   * @var array
   */
  private const array TRIPAL_ENTITY = [
    'type' => 'tripal_entity',
    'bundle' => 'research_experiment',
  ];

  /**
   * Construct alter hooks.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $current_routematch
   *   Entity field manager service.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   Tripal Chado database connection.
   */
  public function __construct(
    RouteMatchInterface $current_routematch,
    ChadoConnection $chado_connection,
  ) {

    $page_params = $current_routematch->getParameters();

    // Operate on Tripal Entities.
    if ($tripal_entity = $page_params->get(self::TRIPAL_ENTITY['type'])) {
      if (method_exists($tripal_entity, 'bundle') && $tripal_entity->bundle() == self::TRIPAL_ENTITY['bundle']) {

        $has_pheno = $chado_connection
          ->select('0:trpcultivate_phenocombo', 'tc')
          ->fields('tc', ['combo_id'])
          ->condition('tc.project_id', $tripal_entity->getBackendRecordId('chado_storage'), '=')
          ->range(0, 1)
          ->execute()
          ->fetchField();

        $this->has_pheno = ($has_pheno) ? TRUE : FALSE;
      }
    }
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   *
   * NOTE: this only removes the delete tab/option. The functionality to
   * delete using the delete entity route may still be used.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name, RefinableCacheableDependencyInterface &$cacheability) {

    if (isset($data['tabs'][0]['entity.tripal_entity.delete_form']) && $this->has_pheno) {
      $data['tabs'][0]['entity.tripal_entity.delete_form']['#link']['localized_options'] = [
        'attributes' => [
          'class' => ['visually-hidden'],
        ],
      ];
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Disables the delete action button (if provided by admin theme) and attaches
   * a custom form validation function to edit form of research exp. entity.
   *
   * NOTE: this only removes the delete tab/option. The functionality to
   * delete using the delete entity route may still be used.
   *
   * @see phenoGenusExperimentEditFormValidate()
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {

    if ($form_id == 'tripal_entity_research_experiment_edit_form') {
      if (isset($form['actions']['delete']) && $this->has_pheno) {
        $form['actions']['delete']['#attributes'] = [
          'class' => ['visually-hidden'],
        ];
      }
    }
  }

  /**
   * Implements hook_entity_type_alter()
   *
   * Enforces the genus-experiment-phenotype relationship by ensuring uniqueness
   * and preventing modifications or removal of the genus entry once phenotypic
   * data has been associated.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {

    $tripal_entity = 'tripal_entity';

    if (isset($entity_types[$tripal_entity])) {
      if ($entity_types[$tripal_entity]->id() == $tripal_entity) {

        $entity_types[$tripal_entity]
          ->addConstraint('LockExperimentGenusWithPhenotypes', []);
      }
    }
  }

}
