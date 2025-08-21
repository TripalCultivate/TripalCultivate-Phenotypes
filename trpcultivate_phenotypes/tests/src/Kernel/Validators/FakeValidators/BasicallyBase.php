<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the base class.
 *
 * @TripalCultivateValidator(
 *   id = "fake_basically_base",
 *   validator_name = @Translation("Basically Base Validator"),
 *   input_types = {"header-row", "data-row"}
 * )
 */
#[TripalCultivateValidator(
   id: 'fake_basically_base',
   validator_name: new TranslatableMarkup('Basically Base Validator'),
   input_types: ['header-row', 'data-row']
 )]
class BasicallyBase extends TripalCultivatePhenotypesValidatorBase {

}
