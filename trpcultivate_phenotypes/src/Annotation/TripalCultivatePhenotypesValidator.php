<?php

/**
 * @file
 * Contains \Drupal\trpcultivate_phenotypes\Annotation\TripalCultivatePhenotypesValidator.
 * 
 * @see Plugin manager in src\TripalCultivatePhenotypesValidatorManager.php
 */

namespace Drupal\trpcultivate_phenotypes\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a data validator annotation object.
 * 
 * @see Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorManager
 * @see Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorInterface
 * 
 * @Annotation
 */
class TripalCultivatePhenotypesValidator extends Plugin {
  /**
   * The validator plugin ID.
   * 
   * @var string.
   */
  public $id;

  /**
   * The validator human-readable name.
   * 
   * @var string.
   */
  public $validator_name;

  /**
   * The scope a validator will perform a check
   * ie. FILE level check, Project/Genus level check or Data Values level check.
   * 
   * @var string.
   */
  public $validator_scope;
}