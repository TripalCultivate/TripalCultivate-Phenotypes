<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorInterface;

/**
 * The base class for validator plugins.
 */
abstract class TripalCultivatePhenotypesValidatorBase extends TripalCultivateValidatorBase implements TripalCultivateValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigAllowNew() {
    $allownew = \Drupal::config('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.ontology.allownew');

    return $allownew;
  }

}
