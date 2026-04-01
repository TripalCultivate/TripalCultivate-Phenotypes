<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

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
  }

}
