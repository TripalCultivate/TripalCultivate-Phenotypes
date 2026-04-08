<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
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
   * Determine if the research experiment entity has phenotypes.
   *
   * @var bool
   */
  protected bool $has_pheno = FALSE;

  /**
   * The genus field name in a research experiment bundle.
   *
   * @var string
   */
  protected string $genus_field = '';

  /**
   * Tripal entity definitions.
   *
   * @var array
   */
  const array TRIPAL_ENTITY = [
    'type' => 'tripal_entity',
    'bundle' => 'research_experiment',
  ];

  /**
   * Construct alter hooks.
   *
   * @param \Drupal\Core\Database\Connection $entityfield_manager
   *   Drupal database connection.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $current_routematch
   *   Entity field manager service.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   Tripal Chado database connection.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService $service_PhenoTerms
   *   Phenotypes terms service.
   */
  public function __construct(
    EntityFieldManagerInterface $entityfield_manager,
    RouteMatchInterface $current_routematch,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesTermsService $service_PhenoTerms,
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

        // In research experiment, a field entity that defines a genus-project
        // property will enforce a field constraint. Name is referenced in this
        // block and is used in a an alter hook.
        // @see TripalCultivatePhenotypesAlterHooks::entityTypeAlter()
        $constraint = ['term' => 'genus', 'base_table' => 'project'];

        $term_namespace = ChadoCVTermAutocompleteController::formatCVterm(
          $service_PhenoTerms->getTermId($constraint['term'])
        );

        $bundle_fields = $entityfield_manager->getFieldDefinitions(
          self::TRIPAL_ENTITY['type'],
          self::TRIPAL_ENTITY['bundle']
        );

        foreach ($bundle_fields as $field) {
          $field_settings = $field->getSettings();
          $table = $field_settings['storage_plugin_settings']['base_table'] ?? NULL;

          if ($table != $constraint['base_table']) {
            continue;
          }

          $idspace = $field_settings['termIdSpace'] ?? NULL;
          $accession = $field_settings['termAccession'] ?? NULL;
          if ($term_namespace == $constraint['term'] . ' (' . $idspace . ':' . $accession . ')') {
            $this->genus_field = $field->getName();

            break;
          }
        }
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
   *
   * The determination of the field is establised in the class constructor.
   * @see TripalCultivatePhenotypesAlterHooks::__constructor()
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {

    if (isset($entity_types[self::TRIPAL_ENTITY['type']]) && $this->genus_field && $this->has_pheno) {
      $entity_types[self::TRIPAL_ENTITY['type']]->addConstraint(
        'LockExperimentGenusWithPhenotypes',
        ['genus_field' => $this->genus_field]
      );
    }
  }

}
