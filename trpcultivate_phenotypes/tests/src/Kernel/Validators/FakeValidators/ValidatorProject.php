<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\Project;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;

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
class ValidatorProject extends TripalCultivateValidatorBase {

  use Project;

}
