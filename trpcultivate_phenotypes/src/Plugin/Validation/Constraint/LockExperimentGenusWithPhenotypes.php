<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as AttributeConstraint;
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
   * Placeholder %genus and @reload are interpolated at runtime, with the user
   * entered genus value and the reload link, respectively.
   *
   * @var string
   *
   * @see src/Plugin/Validation/Constraint/LockExperimentGenusWithPhenotypesValidator.php
   */
  public string $genus_failed = 'Update failed: Genus "%genus" of this research experiment is linked to the Phenotypes module and must be a unique entry in the Germplasm Genus field. Click @reload to restore form values if you have removed or altered a genus.';

}
