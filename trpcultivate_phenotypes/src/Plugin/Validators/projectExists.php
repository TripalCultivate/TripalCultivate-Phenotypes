<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that project exits.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_project_exists",
 *   validator_name = @Translation("Project Exists Validator"),
 *   input_types = {"metadata"}
 * )
 */
class projectExists extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * Validate that project provided exists and configured.
   * 
   * @param array $form_values
   *   The values entered to any form field elements implemented by the importer.
   *   Each form element value can be accessed using the field element key
   *   ie. field name/key project - $form_values['project'].
   * 
   *   This array is the result of calling $form_state->getValues().
   *
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the project value is valid or not.
   *     - failedItems: the failed project value provided. This will be empty if the value was valid.
   */
  public function validateMetadata($form_values) {
  }
}