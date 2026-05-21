<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates LockExperimentGenusWithPhenotypes constraint.
 */
class LockExperimentGenusWithPhenotypesValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The term referenced by a field that would qualify constraint application.
   *
   * This value acts as an indentifier that field definitions can use to
   * determine whether genu-experiment constraint should be applied.
   * Currently set to - genus (TAXRANK:0000005) and resolved to the cvterm id
   * configured in Phenotypes module.
   *
   * @var string
   */
  private const CONSTRAINT_TERM_KEY = 'genus';

  /**
   * Constructor.
   *
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   The genus ontology service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService $service_PhenoTerms
   *   The terms service.
   */
  public function __construct(
    protected ChadoConnection $chado_connection,
    protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    protected TripalCultivatePhenotypesTermsService $service_PhenoTerms,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('tripal_chado.database'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.terms'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * NOTE: the constraint is attached at entity level and parameter #1 is
   * a tripal_entity entity type.
   */
  public function validate($tripal_entity, Constraint $constraint): void {

    $config_genus_cvterm_id = $this->service_PhenoTerms
      ->getTermId(self::CONSTRAINT_TERM_KEY);

    $pheno_configgenus = $this->service_PhenoGenusOntology
      ->getConfiguredGenusList();

    // Skip field-constraint check if system has no genus configured, or if the
    // term genus does not have a cvterm_id.
    if (!$config_genus_cvterm_id || !$pheno_configgenus) {
      return;
    }

    // Loop through all chado fields looking for those with a path including the
    // projectprop.type_id. This variable is a list of fields describing a
    // property and whose property has the same type_id as
    // `genus (TAXRANK:0000005)`. The value is the table alias in this property
    // for the projectprop table.
    $genus_property_fields = [];
    $chado_fields = $tripal_entity->getTripalStorageFields('chado_storage');

    foreach ($chado_fields as $field_name) {
      foreach ($tripal_entity->getTripalFieldPropertyKeys($field_name) as $property_key) {

        // Get the unaliased path for this property.
        $path = $tripal_entity->getTripalFieldPropertyPath($field_name, $property_key);

        // If this property type defines the path...
        if ($path !== NULL && str_contains($path, 'projectprop.project_id;type_id')) {
          // Check the value of this property with the known cvterm_id of the
          // genus term and if it matches then this is a field we are interested
          // in! Save the field name and the alias for the table.
          $field_values = $tripal_entity->get($field_name);
          foreach ($field_values as $item) {
            if ($item->get($property_key)->getValue() == $config_genus_cvterm_id) {
              // Not all property types include a table_mapping, check if it
              // does and if not then just use projectprop.
              $table_mapping = $tripal_entity->getTripalFieldPropertyInfo($field_name, $property_key, 'table_alias_mapping');

              if (is_array($table_mapping)) {
                $genus_property_fields[$field_name] = array_search(
                  'projectprop',
                  $tripal_entity->getTripalFieldPropertyInfo($field_name, $property_key, 'table_alias_mapping')
                );
              }
              else {
                $genus_property_fields[$field_name] = 'projectprop';
              }
            }
          }
        }
      }
    }

    // None of the genus/organism target fields contain a value.
    // Trigger the constraint if an experiment has no remaining genus values
    // while still containing phenocombo records.
    if ($genus_property_fields == []) {
      // @todo replace with phenocombo service.
      $exp_has_phenocombo = $this->chado_connection->select('trpcultivate_phenocombo', 'combo')
        ->condition('combo.project_id', $tripal_entity->getBackendRecordId('chado_storage'), '=')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exp_has_phenocombo) {
        $this->context
          ->buildViolation(
            Markup::create(strtr($constraint->all_genus_failed, [
              '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
            ]))
          )
          ->addViolation();
      }

      return;
    }

    // Get the property in each of the genus_property_fields that looks at the
    // projectprop.value column where the same alias is used as was for
    // the type_id.
    $field_properties_to_validate = [];
    foreach ($genus_property_fields as $field_name => $projectprop_alias) {
      foreach ($tripal_entity->getTripalFieldPropertyKeys($field_name) as $property_key) {

        $aliased_path = $tripal_entity->getTripalFieldPropertyInfo($field_name, $property_key, 'path');
        if ($aliased_path !== NULL && str_contains($aliased_path, $projectprop_alias . '.project_id;value')) {
          $field_properties_to_validate[] = $field_name;
        }
      }
    }

    if ($field_properties_to_validate == []) {
      return;
    }

    // Validate genus/organism field values.
    foreach ($field_properties_to_validate as $constraint_fieldname) {
      // Find the key that corresponds to the field value and create summary
      // count of each unique value.
      $field_values = $tripal_entity->get($constraint_fieldname)->getValue();
      $value_key = array_key_exists('value', reset($field_values)) ? 'value' : 'genus_value';

      $field_values = array_filter(array_column($field_values, $value_key));
      $count_bygenus = array_count_values($field_values);

      // @todo replace with phenocombo service.
      $query = $this->chado_connection->select('trpcultivate_phenocombo', 'combo');
      $query->join('1:cvterm', 'term', 'combo.attr_id = term.cvterm_id');
      $query->join('1:cv', 'vocab', 'term.cv_id = vocab.cv_id');

      foreach ($pheno_configgenus as $genus) {
        $genus_config = $this->service_PhenoGenusOntology
          ->getGenusOntologyConfigValues($genus);

        if (!$genus_config) {
          continue;
        }

        // A phenotype to a genus would suffice enforcement check.
        $has_pheno = $query
          ->condition('combo.project_id', $tripal_entity->getBackendRecordId('chado_storage'), '=')
          ->condition('vocab.cv_id', $genus_config['trait'], '=')
          ->countQuery()
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
