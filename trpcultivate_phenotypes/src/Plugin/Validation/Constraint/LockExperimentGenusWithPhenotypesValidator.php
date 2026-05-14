<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates LockExperimentGenusWithPhenotypes constraint.
 */
class LockExperimentGenusWithPhenotypesValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constraint field property term setting requirements.
   *
   * The constraint is applied at the entity level. Any field on the entity type
   * configured with the term definition below will trigger the constraint
   * validator during the value validation.
   *
   * @var array
   *
   * @see TripalCultivate Base/config/install/tripal.tripalfield_collection.trpcultivate_experiments.yml (genus)
   */
  private const CONSTRAINT_TERM_REQUIREMENT = [
    'idspace' => 'TAXRANK',
    'accession' => '0000005',
  ];

  /**
   * Constraint field property base table setting requirement.
   *
   * @var string
   */
  private const CONSTRAINT_FIELD_BASETABLE = 'project';

  /**
   * Constructor.
   *
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   The genus ontology service.
   */
  public function __construct(
    protected ChadoConnection $chado_connection,
    protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('tripal_chado.database'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * NOTE: the constraint is attached at entity level and parameter #1 is
   * a tripal_entity entity type.
   */
  public function validate($tripal_entity, Constraint $constraint): void {

    // Determine the field entity with the required term properties.
    $constraint_fieldname = '';

    foreach ($tripal_entity->getFields() as $field) {
      $field_settings = $field->getSettings();

      $base_table = $field_settings['storage_plugin_settings']['base_table'] ?? NULL;
      if ($base_table != self::CONSTRAINT_FIELD_BASETABLE) {
        continue;
      }

      $idspace = $field_settings['termIdSpace'] ?? NULL;
      $accession = $field_settings['termAccession'] ?? NULL;

      if ($idspace == self::CONSTRAINT_TERM_REQUIREMENT['idspace'] && $accession == self::CONSTRAINT_TERM_REQUIREMENT['accession']) {
        // Field name with all constraint requirements met.
        $constraint_fieldname = $field->getName();

        break;
      }
    }

    // Validate the value of the field entity identified.
    if ($constraint_fieldname !== '') {
      $pheno_configgenus = $this->service_PhenoGenusOntology->getConfiguredGenusList();

      $field_values = array_filter(
        array_column($tripal_entity->get($constraint_fieldname)->getValue(), 'value')
      );

      // Skip this step whenever system has zero configured genus or the field
      // entity has no genus value.
      if (count($pheno_configgenus) > 0 || $field_values) {
        $count_bygenus = array_count_values($field_values);

        $query = $this->chado_connection->select('trpcultivate_phenocombo', 'tp');
        $query->join('1:cvterm', 't', 'tp.attr_id = t.cvterm_id');
        $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

        foreach ($pheno_configgenus as $genus) {
          $genus_config = $this->service_PhenoGenusOntology
            ->getGenusOntologyConfigValues($genus);

          if (!$genus_config) {
            continue;
          }

          // A phenotype to a genus would suffice enforcement check.
          $has_pheno = $query
            ->fields('tp', ['combo_id'])
            ->condition('tp.project_id', $tripal_entity->getBackendRecordId('chado_storage'), '=')
            ->condition('v.cv_id', $genus_config['trait'], '=')
            ->range(0, 1)
            ->execute()
            ->fetchField();

          // Genus has phenotypes and is missing/altered/has duplicates from the
          // list of germplasm genus of the research experiment entity.
          $not_unique = (isset($count_bygenus[$genus]) && $count_bygenus[$genus] > 1) ? 1 : 0;

          if ($has_pheno > 0 && (!in_array($genus, $field_values) || $not_unique)) {
            $this->context
              ->buildViolation(
                Markup::create(strtr($constraint->genus_failed, [
                  '%genus' => $genus,
                  '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
                ]))
              )
              ->atPath($constraint_fieldname)
              ->addViolation();

            break;
          }
        }
      }
    }
  }

}
