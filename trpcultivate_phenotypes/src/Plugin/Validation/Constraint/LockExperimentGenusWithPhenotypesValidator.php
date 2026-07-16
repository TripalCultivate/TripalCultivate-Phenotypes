<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\tripal\Entity\TripalEntity;
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
   * determine whether genus-experiment constraint should be applied.
   * Currently set to - genus (TAXRANK:0000005) and resolved to the cvterm id
   * configured in Phenotypes module.
   *
   * @var string
   */
  private const CONSTRAINT_TERM_KEY = 'genus';

  /**
   * The project_id (backend record id) of the experiment.
   *
   * @var int|null
   */
  protected int|null $project_id = NULL;

  /**
   * The constrait term resolved cvterm_id.
   *
   * @var int
   */
  protected int $config_genus_cvterm_id;

  /**
   * List of configured genus of the hosted Phenotypes module.
   *
   * @var array
   */
  protected array $pheno_configgenus = [];

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
    // Parameters are assigned to protected properties via constructor
    // property promotion.
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
   * NOTE: the constraint is attached at entity level in order to validate
   * all fields that reference genus values.
   *
   * @param mixed $tripal_entity
   *   The Tripal Entity being validated containing the field(s) being
   *   checked for genus values.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint definition which includes messages.
   */
  public function validate(mixed $tripal_entity, Constraint $constraint): void {

    $this->project_id = $tripal_entity->getBackendRecordId('chado_storage');

    $this->config_genus_cvterm_id = $this->service_PhenoTerms
      ->getTermId(self::CONSTRAINT_TERM_KEY);

    $this->pheno_configgenus = $this->service_PhenoGenusOntology
      ->getConfiguredGenusList();

    // Skip field-constraint check if system has no genus configured, or if the
    // term genus does not have a cvterm_id.
    if ($this->project_id === NULL || !$this->config_genus_cvterm_id || !$this->pheno_configgenus) {
      return;
    }

    // Find fields that manage a chado project.projectprop record using the
    // genus cvterm as it's type_id.
    // Note: If a field has no value, then it cannot be found via this helper
    // method. See the method documentation for more details on why.
    $genus_property_fields = $this->findFieldsWithGenusProperty($tripal_entity);

    // Trigger the constraint if an experiment has no remaining genus values
    // but still has configured experiment-trait-method-unit combinations
    // which by definition are genus-specific.
    if ($genus_property_fields == []) {
      // @todo replace with phenocombo service.
      $exp_has_phenocombo = $this->chado_connection->select('trpcultivate_phenocombo', 'combo')
        ->condition('combo.project_id', $this->project_id, '=')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($exp_has_phenocombo) {
        $this->context
          ->buildViolation(
            Markup::create(strtr($constraint->all_genus_failed, [
              '%content-type' => $tripal_entity->getBundle()->label(),
              '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
            ]))
          )
          ->addViolation();
      }

      return;
    }

    // Find the TripalPropertyType key storing the genus for all fields found
    // using findGenusFieldProperty(). This TripalPropertyType should store the
    // chado projectprop.value column where the projectprop.type_id of the same
    // record references the genus cvterm.
    $field_properties_to_validate = $this->findGenusFieldValue($genus_property_fields, $tripal_entity);
    if ($field_properties_to_validate == []) {
      return;
    }

    // Validate genus fields.
    foreach ($field_properties_to_validate as $constraint_field) {
      $field_values = $tripal_entity->get($constraint_field['field_name'])->getValue();
      $this->validateGenusField($constraint_field, $field_values, $constraint);
    }
  }

  /**
   * This method compiles a list of all fields that store a chado projectprop
   * record with the type_id referencing the genus cvterm. This is done by
   * looking for a TripalPropertyValue for the projectprop.type_id specifically
   * and checking it's value matches the cvterm_id of the genus cvterm.
   *
   * Note: Since we need to look at the value of projectprop.type_id property
   * and Drupal only includes values for a field if it is not empty, we cannot
   * detect genus property fields without a genus being assigned.
   *
   * @param \Drupal\tripal\Entity\TripalEntity $tripal_entity
   *   Tripal Entity @see \Drupal\tripal\Entity\TripalEntity.
   *
   * @return array
   *   The field names that implement the path property string.
   */
  protected function findFieldsWithGenusProperty(TripalEntity $tripal_entity): array {

    // This variable is a list of fields describing a property and whose
    // property has the same type_id as `genus (TAXRANK:0000005)`. The value
    // is the table alias in this property for the projectprop table.
    $genus_property_fields = [];
    $chado_fields = $tripal_entity->getTripalStorageFields('chado_storage');

    // Loop through all chado fields looking for those with a path including the
    // projectprop.type_id. This variable is a list of fields describing a
    // property and whose property has the same type_id as
    // `genus (TAXRANK:0000005)`. The value is the table alias in this property
    // for the projectprop table.
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
            if ($item->get($property_key)->getValue() == $this->config_genus_cvterm_id) {
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

    return $genus_property_fields;
  }

  /**
   * Find the value property of fields that implement the path property string.
   *
   * @param array $genus_property_fields
   *   An array of field names that have been identified to implement the path
   *   property string.
   * @param \Drupal\tripal\Entity\TripalEntity $tripal_entity
   *   Tripal Entity @see \Drupal\tripal\Entity\TripalEntity.
   *
   * @return array
   *   An array of field names that contains the genus value.
   */
  protected function findGenusFieldValue($genus_property_fields, TripalEntity $tripal_entity): array {

    $field_properties_to_validate = [];

    // Get the property in each of the genus_property_fields that looks at the
    // projectprop.value column where the same alias is used as was for
    // the type_id.
    foreach ($genus_property_fields as $field_name => $projectprop_alias) {
      foreach ($tripal_entity->getTripalFieldPropertyKeys($field_name) as $property_key) {

        $aliased_path = $tripal_entity->getTripalFieldPropertyInfo($field_name, $property_key, 'path');
        if ($aliased_path !== NULL && str_contains($aliased_path, $projectprop_alias . '.project_id;value')) {
          $field_properties_to_validate[] = [
            'field_name' => $field_name,
            'property_key' => $property_key,
            'content_type' => $tripal_entity->getBundle()->label(),
          ];
        }
      }
    }

    return $field_properties_to_validate;
  }

  /**
   * Validate the genus value of the field identified to implement genus.
   *
   * The genus value is validated within the context of the experiment.
   *
   * @param array $field
   *   An associative array of fields containing genus. Each field element
   *   contains the following keys:
   *   - 'field_name': the name of the field.
   *   - 'property_key': the property key used to reference field value.
   *   - 'content_type': the label of the content type the field belongs to.
   * @param array $field_values
   *   The array of genus values of the field. The value is an array keyed by
   *   either the string 'value' or 'genus_value'.
   *   ie. [genus_value => Lens] or [value => Triticum].
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   Constraint definition.
   */
  protected function validateGenusField(array $field, array $field_values, Constraint $constraint): void {

    // Create summary count of each unique genus value.
    $field_values = array_filter(array_column($field_values, $field['property_key']));
    $count_bygenus = array_count_values($field_values);

    // @todo replace with phenocombo service.
    $query = $this->chado_connection->select('trpcultivate_phenocombo', 'combo');
    $query->join('1:cvterm', 'term', 'combo.attr_id = term.cvterm_id');
    $query->join('1:cv', 'vocab', 'term.cv_id = vocab.cv_id');

    foreach ($this->pheno_configgenus as $genus) {
      $genus_config = $this->service_PhenoGenusOntology
        ->getGenusOntologyConfigValues($genus);

      if (!$genus_config) {
        continue;
      }

      // A phenotype to a genus would suffice enforcement check.
      $has_pheno = $query
        ->condition('combo.project_id', $this->project_id, '=')
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
              '%content-type' => $field['content_type'],
              '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
            ]))
          )
          ->atPath($field['field_name'])
          ->addViolation();

        break;
      }
    }
  }

}
