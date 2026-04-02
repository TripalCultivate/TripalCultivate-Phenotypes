<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates LockExperimentGenusWithPhenotypes constraint.
 */
class LockExperimentGenusWithPhenotypesValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {

    $entity = $value->getEntity();
    $genusontology_service = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
    $pheno_configgenus = $genusontology_service->getConfiguredGenusList();

    if (count($pheno_configgenus) > 0) {
      // Genus as provided in the Design/Germplasm/Germplasm Genus field.
      // Removes the trailing genus field value set to empty string.
      $exp_germgenus = array_filter(
        array_column($value->getValue(), 'value')
      );

      $count_bygenus = array_count_values($exp_germgenus);

      $chado_connection = \Drupal::service('tripal_chado.database');
      foreach ($pheno_configgenus as $genus) {
        $genus_config = $genusontology_service->getGenusOntologyConfigValues($genus);

        if (!$genus_config) {
          continue;
        }

        $query = $chado_connection->select('trpcultivate_phenocombo', 'tp');
        $query->join('1:cvterm', 't', 'tp.attr_id = t.cvterm_id');
        $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

        $experiment_id = $entity->getBackendRecordId('chado_storage');

        // A phenotype to a genus would suffice enforcement check.
        $has_pheno = $query
          ->fields('tp', ['combo_id'])
          ->condition('tp.project_id', $experiment_id, '=')
          ->condition('v.cv_id', $genus_config['trait'], '=')
          ->range(0, 1)
          ->execute()
          ->fetchField();

        // Genus has phenotypes and is missing/has duplicates from the list of
        // germplasm genus of the research experiment entity.
        $not_unique = (isset($count_bygenus[$genus]) && $count_bygenus[$genus] > 1) ? 1 : 0;

        if ($has_pheno > 0 && (!in_array($genus, $exp_germgenus) || $not_unique)) {
          $this->context->addViolation(
            Markup::create(
              strtr($constraint->genus_failed, [
                '%genus' => $genus,
                '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
              ])
            )
          );
        }
      }
    }
  }

}
