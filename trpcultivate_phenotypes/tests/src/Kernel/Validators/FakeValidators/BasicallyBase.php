<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;

/**
 * Fake Validator that does not implement any of it's own methods.
 * Used to test the base class.
 *
 * @TripalCultivatePhenotypesValidator(
 * id = "fake_basically_base",
 * validator_name = @Translation("Basically Base Validator"),
 * input_types = {"header-row", "data-row"}
 * )
 */
class BasicallyBase extends TripalCultivatePhenotypesValidatorBase {

}
