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
   * The name of the field that contains the genus.
   *
   * @var string
   */
  public const FIELD_ORGANISM = 'exp_organism';

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConneciton
   */
  protected ChadoConnection $chado_connection;

  /**
   * Phenotypes genus ontology service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

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
  private const TRIPAL_ENTITY = [
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
          ->select('trpcultivate_phenocombo', 'tc')
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
   * Implements hook_entity_type_alter().
   *
   * Enforces the genus-experiment-phenotype relationship by ensuring uniqueness
   * and preventing modifications or removal of the genus entry once phenotypic
   * data has been associated.
   *
   * We use this hook to alter the Tripal Core tripal_entity type
   * research_experiment bundle. We are adding an entity-level constraint which
   * will in turn target a specific field and apply field-highlighting on error.
   *
   * NOTE: We are not adding a field level constraint for a couple of reasons:
   * 1. We do not want to make any assumptions regarding the name or type of
   *   the field targeting the projectprop type > genus.
   * 2. We need to ensure this constraint targets the field after the field
   *   collections have been added which prevents us from doing this in
   *   the module install phase.
   *
   * @see src/Plugin/Validation/Constraint/LockExperimentGenusWithPhenotypesValidator.php
   */
  public function phenoGenusExperimentEditFormValidate($form, FormStateInterface $form_state) {

    // All genus configured in Phenotypes.
    $pheno_configgenus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    $germgenus_field = FieldStorageConfig::loadByName('tripal_entity', self::FIELD_ORGANISM);

    if (count($pheno_configgenus) > 0 && $this->has_pheno && $germgenus_field) {
      // Genus as provided in the Design/Germplasm/Germplasm Genus field.
      // Removes the trailing genus field value set to empty string.
      $exp_germgenus = array_filter(
        array_column($form_state->getValue(self::FIELD_ORGANISM), 'genus_value')
      );

      $count_bygenus = array_count_values($exp_germgenus);

      foreach ($pheno_configgenus as $genus) {
        $genus_config = $this->service_PhenoGenusOntology
          ->getGenusOntologyConfigValues($genus);

        if (!$genus_config) {
          continue;
        }

        $query = $this->chado_connection->select('trpcultivate_phenocombo', 'tp');
        $query->join('1:cvterm', 't', 'tp.attr_id = t.cvterm_id');
        $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

        // A phenotype to a genus would suffice enforcement check.
        $has_pheno = $query
          ->fields('tp', ['combo_id'])
          ->condition('tp.project_id', $this->experiment_id, '=')
          ->condition('v.cv_id', $genus_config['trait'], '=')
          ->range(0, 1)
          ->execute()
          ->fetchField();

        // Genus has phenotypes and is missing/has duplicates from the list of
        // germplasm genus of the research experiment entity.
        $not_unique = (isset($count_bygenus[$genus]) && $count_bygenus[$genus] > 1) ? 1 : 0;

        if ($has_pheno > 0 && (!in_array($genus, $exp_germgenus) || $not_unique)) {
          $form_state->setErrorByName(
            self::FIELD_ORGANISM,
            $this->t('Update failed: Genus "@genus" of this research experiment is linked to the Phenotypes module and must be a unique entry in the Germplasm Genus field. Click @reload to restore form values if you have removed or altered a genus.', [
              '@genus' => $genus,
              '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
            ])
          );
        }
      }
    }
  }

}
