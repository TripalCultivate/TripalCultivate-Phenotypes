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
   * Placeholders will be interpolated at runtime. Specifically,
   * - %genus: genus failing validation.
   * - %content-type: the label of the content type being validated.
   * - @reload: a link to force reloading of the form.
   *
   * @var string
   *
   * @see src/Plugin/Validation/Constraint/LockExperimentGenusWithPhenotypesValidator.php
   */
  public string $genus_failed = 'Update failed: Genus "%genus" of this %content-type is linked to phenotypic data and must remain present and unique. Click @reload to restore form values if you tried to remove or change the genus.';

  /**
   * The message that will be shown if all genus have been removed.
   *
   * Placeholders will be interpolated at runtime. Specifically,
   * - %content-type: the label of the content type being validated.
   * - @reload: a link to force reloading of the form.
   *
   * @var string
   *
   * @see src/Plugin/Validation/Constraint/LockExperimentGenusWithPhenotypesValidator.php
   */
  public string $all_genus_failed = 'Update failed: %content-type has phenotypic data registered, thus, linked genus must remain present. Click @reload to restore form values if you have removed all genus.';

}
