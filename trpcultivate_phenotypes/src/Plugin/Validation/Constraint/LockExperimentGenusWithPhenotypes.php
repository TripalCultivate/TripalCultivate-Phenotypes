<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as AttributeConstraint;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that a genus with phenotypes cannot be altered, duplicated or removed.
 */
#[AttributeConstraint(
  id: 'LockExperimentGenusWithPhenotypes',
  label: new TranslatableMarkup('Lock Experiment Genus with Phenotypes'),
  type: 'tripal_entity'
)]
class LockExperimentGenusWithPhenotypes extends Constraint {

  /**
   * The message that will be shown if the genus has failed validation.
   *
   * @var string
   */
  public string $genus_failed = 'Update failed: Genus "%genus" of this research experiment is linked to the Phenotypes module and must be a unique entry in the Germplasm Genus field. Click @reload to restore form values if you have removed or altered a genus.';

  /**
   * Field entity property requirements for attaching constraint.
   *
   * @var string
   */
  public array $field_req = [
    'term' => 'genus',
    'base_table' => 'project',
  ];

  /**
   * The field requirement term namespace.
   *
   * @var string
   */
  public string $term_namespace = '';

  /**
   * Constructor.
   */
  public function __construct() {

    $term_id = \Drupal::service('trpcultivate_phenotypes.terms')
      ->getTermId($this->field_req['term']);

    $this->term_namespace = ChadoCVTermAutocompleteController::formatCVterm($term_id);
  }

}
