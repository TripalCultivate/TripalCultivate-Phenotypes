<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;

/**
 * Phenotypes module alter hooks.
 */
class TripalCultivatePhenotypesAlterHooks {

  use StringTranslationTrait;

  /**
   * The integration this hook applies to.
   *
   * @var string
   */
  const PHENO_COMBO_INTEGRATION = 'pheno_combo';

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
   */
  public function __construct(
    Connection $drupaldb_connection,
    RouteMatchInterface $current_routematch,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    PhenoIntegrationSettings $service_PhenoIntegration,
  ) {

    $this->chado_connection = $chado_connection;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;

    // This entity page is not backup-related and is treated as pheno_combo
    // integration. This check determinse whether the content type has any
    // pheno-combo records, if it does, the delete button is disabled to prevent
    // removing this entity that still has dependent records.
    $tripal_entity = $current_routematch->getParameters()->get('tripal_entity');

    if ($tripal_entity
        && method_exists($tripal_entity, 'bundle')
        && $service_PhenoIntegration->isContentTypePhenoSupported(self::PHENO_COMBO_INTEGRATION, $tripal_entity->bundle())) {

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

      $form['#validate'][] = [$this, 'phenoGenusExperimentEditFormValidate'];
    }
  }

  /**
   * Form edit validate callback.
   *
   * Enforces the genus-experiment-phenotype relationship by ensuring uniqueness
   * and preventing modification or removal of the genus entry once phenotypic
   * data has been associated.
   */
  public function phenoGenusExperimentEditFormValidate($form, FormStateInterface $form_state) {

    // All genus configured in Phenotypes.
    $pheno_configgenus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    $germgenus_field = FieldStorageConfig::loadByName('tripal_entity', self::FIELD_ORGANISM);

    if (count($pheno_configgenus) > 0 && $this->has_pheno && $germgenus_field) {
      // Genus as provided in the Design/Germplasm/Germplasm Genus field.
      // Removes the trailing genus field value set to empty string.
      $exp_germgenus = array_filter(
        array_column($form_state->getValue(self::FIELD_ORGANISM), 'value')
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
