<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\Project;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the Project trait.
 *
 * @TripalCultivateValidator(
 *   id = "validator_requiring_project",
 *   validator_name = @Translation("Validator Using Project Trait"),
 *   input_types = {"metadata"}
 * )
 */
#[TripalCultivateValidator(
   id: 'validator_requiring_project',
   validator_name: new TranslatableMarkup('Validator Using Project Trait'),
   input_types: ['metadata']
 )]
#[RunTestsInSeparateProcesses]
class ValidatorProject extends TripalCultivateValidatorBase {

  use Project;

}
