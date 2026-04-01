<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that a genus with phenotypes cannot be altered, duplicated or removed.
 *
 * @Constraint(
 *   id = "LockExperimentGenusWithPhenotypes",
 *   label = @Translation("Lock Experiment Genus with Phenotypes", context = "Validation"),
 * )
 */
class LockExperimentGenusWithPhenotypes extends Constraint {

  /**
   * The message that will be shown if the genus has failed validation.
   *
   * @var string
   */
  public $genus_failed = 'The "%value" of this research experiment is linked to the Phenotypes Module and must be unique entry in the Germplasm Genus field';

}
