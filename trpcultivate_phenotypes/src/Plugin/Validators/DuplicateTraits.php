<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Validate duplicate traits within a file
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_duplicate_traits",
 *   validator_name = @Translation("Duplicate Traits Validator"),
 *   validator_scope = "FILE ROW",
 * )
 */
class DuplicateTraits extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * A nested array of already validated values forming the unique trait name +
   *   method name + unit combinations that have been encountered by this
   *   validator so far within the input file
   */
  protected $unique_traits = [];

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
      $plugin_definition
    );
  }

  /**
   * Validate the values within the cells of this row.
   * @param array $row_values
   *   The contents of the file's row where each value within a cell is
   *   stored as an array element
   * @param array $context
   *   An associative array with the following keys:
   *   - indices => an associative array with the following keys:
   *     - 'trait': int, the index of the trait name column in $row_values
   *     - 'method': int, the index of the method name column in $row_values
   *     - 'unit': int, the index of the unit column in $row_values
   *
   * @return array
   *   An associative array with the following keys:
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validateRow($row_values, $context) {

    // Check our inputs - will throw an exception if there's a problem
    $this->checkIndices($row_values, $context['indices']);

    // Grab our trait, method and unit values from the $row_values array
    // using the indices stored in our $context array
    // We need to ensure that each array key we expect in $context['indices']
    // exists, otherwise throw an exception
    if (!isset($context['indices']['trait'])) {
      throw new \Exception(t('The trait name (key: trait) was not set in the $context[\'indices\'] array'));
    }
    if (!isset($context['indices']['method'])) {
       throw new \Exception(t('The method name (key: method) was not set in the $context[\'indices\'] array'));
    }
    if (!isset($context['indices']['unit'])) {
       throw new \Exception(t('The unit (key: unit) was not set in the $context[\'indices\'] array'));
    }
    $trait = strtolower($row_values[$context['indices']['trait']]);
    $method = strtolower($row_values[$context['indices']['method']]);
    $unit = strtolower($row_values[$context['indices']['unit']]);

    // Now check for the presence of our array within our global array
    if (!empty($this->unique_traits)) {
      if (isset($this->unique_traits[$trait][$method][$unit])) {
        // Then we've found a duplicate
        $validator_status = [
          'title' => 'Duplicate Trait Name + Method Short Name + Unit combination',
          'status' => 'fail',
          'details' => 'A duplicate trait was found within the input file'
        ];
        return $validator_status;
      }
    }

    // There are no duplicates in our file so far, now check at the database level
    // Grab our traits service
    // *************************************************************************
    // * @TODO: The following code assumes only one record at a time is returned
    // * by each of the getter methods, but in reality we want to accomodate multiple
    // * potential methods for a trait, and multiple units for a method. Thus,
    // * each result will need to be iterated through to find the right one if this
    // * is a duplicate. Issue #78 should address this in the getter functions
    // *************************************************************************
    $service_traits = \Drupal::service('trpcultivate_phenotypes.traits');
    $trait_record = $service_traits->getTrait($trait);
    if ($trait_record) {
      // If trait already exists, next check for method
      $method_record = $service_traits->getTraitMethod($trait);
      if ($method_record) {
        // Lastly, check for unit
        $unit_record = $service_traits->getMethodUnit($method_record->cvterm_id);
        if ($unit_record) {
          // Duplicate found
          $validator_status = [
            'title' => 'Duplicate Trait Name + Method Short Name + Unit combination',
            'status' => 'fail',
            'details' => 'A duplicate trait was found within the database'
          ];
          return $validator_status;
        }
      }
    }

    // Finally, if not seen before, add to the global array
    $this->unique_traits[$trait][$method][$unit] = 1;

    $validator_status = [
      'title' => 'Unique Trait Name + Method Short Name + Unit combination',
      'status' => 'pass',
      'details' => 'Confirmed that the current trait being validated is unique.'
    ];
    //print_r($this->unique_traits);

    return $validator_status;
  }

  /**
   * Getter for the $unique_traits array.
   *
   * @return $unique_traits
   *   - The array of unique trait name + method name + unit combinations
   *     that have been encountered by this validator so far within the input
   *     file
   */
  public function getUniqueTraits() {
    return $this->unique_traits;
  }
}
