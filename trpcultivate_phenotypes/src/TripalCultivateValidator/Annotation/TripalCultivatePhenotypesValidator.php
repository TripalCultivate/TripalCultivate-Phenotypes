<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a data validator annotation object.
 *
 * @see Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorManager
 * @see Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorInterface
 *
 * @Annotation
 */
class TripalCultivatePhenotypesValidator extends Plugin {
  /**
   * The validator plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The validator human-readable name.
   *
   * @var string
   */
  public $validator_name;

  /**
   * The type of the data this validator supports validating.
   *
   * This should be one or more of the following:
   *  - metadata: for validating the form values of the importer not including
   *    the file.
   *  - file: for validating the file object but not it's contents.
   *  - header-row: for validating the first row in the file.
   *  - data-row: for validating all data rows in the file.
   */
  public array $input_types;

}
