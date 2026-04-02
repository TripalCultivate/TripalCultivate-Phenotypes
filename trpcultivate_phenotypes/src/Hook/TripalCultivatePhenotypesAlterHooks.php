<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;

/**
 * Phenotypes module alter hooks.
 */
class TripalCultivatePhenotypesAlterHooks {

  use StringTranslationTrait;

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
   * Phenotypes terms service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService
   */
  protected TripalCultivatePhenotypesTermsService $service_PhenoTerms;

  /**
   * Determine if the research experiment entity has phenotypes.
   *
   * @var bool
   */
  protected bool $has_pheno = FALSE;

  /**
   * The experiment id that references a row in projects table.
   *
   * @var int
   */
  private int $experiment_id = 0;

  /**
   * Construct alter hooks.
   *
   * @param \Drupal\Core\Database\Connection $drupaldb_connection
   *   Drupal database connection.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_routematch
   *   Current route match service.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   Tripal Chado database connection.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   Phenotypes genus ontology service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService $service_PhenoTerms
   *   Phenotypes terms service.
   */
  public function __construct(
    Connection $drupaldb_connection,
    RouteMatchInterface $current_routematch,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesTermsService $service_PhenoTerms,
  ) {

    $this->chado_connection = $chado_connection;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
    $this->service_PhenoTerms = $service_PhenoTerms;

    $page_params = $current_routematch->getParameters();

    // If a research experiment Tripal entity, determine if it has a phenotypes.
    // Only one row would suffice the requirement of 'has_phenotypes'.
    if ($tripal_entity = $page_params->get('tripal_entity')) {
      if (method_exists($tripal_entity, 'bundle') && $tripal_entity->bundle() == 'research_experiment') {

        $this->experiment_id = $tripal_entity->getBackendRecordId('chado_storage');

        $has_pheno = $drupaldb_connection
          ->select('trpcultivate_phenocombo', 'tc')
          ->fields('tc', ['combo_id'])
          ->condition('tc.project_id', $this->experiment_id, '=')
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

    if (isset($data['tabs'][0]['entity.tripal_entity.delete_form']) && $this->experiment_id && $this->has_pheno) {
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
      if (isset($form['actions']['delete']) && $this->experiment_id && $this->has_pheno) {
        $form['actions']['delete']['#attributes'] = [
          'class' => ['visually-hidden'],
        ];
      }
    }
  }

  /**
   * Implemnts hook_entity_bundle_field_info_alter().
   *
   * Enforces the genus-experiment-phenotype relationship by ensuring uniqueness
   * and preventing modification or removal of the genus entry once phenotypic
   * data has been associated.
   *
   * This applies to any field in tripal entity that references Chado project
   * table and stores organism (genus) value as a project property.
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(&$fields, EntityTypeInterface $entity_type, $bundle) {

    // Operate on Tripal entities only.
    if ($entity_type->id() !== 'tripal_entity') {
      return;
    }

    // The term corresponding to genus used by the Phenotypes module.
    // @see settings.yml in trpcultivate_phenotypes/config/install
    $constraint = [
      'term' => 'genus',
      'table' => 'project',
    ];

    // Resolve the term to the cvterm id and construct the term along with
    // the id space and accession values.
    $term_namespace = ChadoCVTermAutocompleteController::formatCVterm(
      $this->service_PhenoTerms->getTermId($constraint['term'])
    );

    // Inspect each field for reference to the project base table and matching
    // term, id space, and accession.
    foreach ($fields as $field) {
      $field_settings = $field->getSettings();

      $table = $field_settings['storage_plugin_settings']['base_table'] ?? NULL;
      $idspace = $field_settings['termIdSpace'] ?? NULL;
      $accession = $field_settings['termAccession'] ?? NULL;

      if ($table != $constraint['table'] || is_null($idspace) || is_null($accession)) {
        continue;
      }

      if ($term_namespace == $constraint['term'] . ' (' . $idspace . ':' . $accession . ')') {
        // This field has project as base storage and creates a genus project
        // property using the term set by phenotypes module.
        // Apply lock constraint.
        $fields[$field->getName()]->addConstraint('LockExperimentGenusWithPhenotypes', []);
      }
    }
  }

}
