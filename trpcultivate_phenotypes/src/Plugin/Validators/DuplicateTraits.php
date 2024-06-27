<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   *   validator so far within the input file. More specifically,
   *   - TRAIT-NAME: array of methods associated with this trait.
   *     - METHOD-NAME: array of units associated with this trait-method combo
   *       - UNIT-NAME: 1 (indicates this full trait-method-unit combo exists)
   *
   *   NOTE: All capital words are intended to be replaced by the actual full
   *   name of the term.
   */
  protected $unique_traits = [];

  /**
   * Traits Service
   */
  protected $service_traits;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TripalCultivatePhenotypesTraitsService $service_traits) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->service_traits = $service_traits;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.traits')
    );
  }

  /**
   * Validate the values within the cells of this row.
   *
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

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    // Typically checkIndices() doesn't take an associative array but
    // because it checks the values not the keys, it will work in this
    // case as well.
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

    $trait = $row_values[$context['indices']['trait']];
    $method = $row_values[$context['indices']['method']];
    $unit = $row_values[$context['indices']['unit']];

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
    $trait_combo = $this->service_traits->getTraitMethodUnitCombo($trait, $method, $unit);

    if (!empty($trait_combo)) {
      // Duplicate found
      $validator_status = [
        'title' => 'Duplicate Trait Name + Method Short Name + Unit combination',
        'status' => 'fail',
        'details' => 'The combination of ' . $trait . ', ' . $method . ', and ' . $unit . ' is already found in the database.'
      ];
      return $validator_status;
    }

    // Finally, if not seen before, add to the global array
    $this->unique_traits[$trait][$method][$unit] = 1;

    // Then set the status to pass
    $validator_status = [
      'title' => 'Unique Trait Name + Method Short Name + Unit combination',
      'status' => 'pass',
      'details' => 'Confirmed that the current trait being validated is unique.'
    ];

    return $validator_status;
  }

  /**
   * Getter for the $unique_traits array.
   *
   * @return $unique_traits
   *   The array of unique trait name + method name + unit combinations.
   *   More specifically, the array is as follows with capitalized words replaced
   *   by the term name.
   *   - TRAIT-NAME: array of methods associated with this trait.
   *     - METHOD-NAME: array of units associated with this trait-method combo
   *       - UNIT-NAME: 1 (indicates this full trait-method-unit combo exists)

   *     that have been encountered by this validator so far within the input
   *     file
   */
  public function getUniqueTraits() {
    return $this->unique_traits;
  }
}
