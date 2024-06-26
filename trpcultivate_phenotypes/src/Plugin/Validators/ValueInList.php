<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that column only contains a set list of values.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_value_in_list",
 *   validator_name = @Translation("Value In List Validator"),
 *   validator_scope = "FILE ROW",
 * )
 */
class ValueInList extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
   *   An associative array with the following key:
   *   - indices => an array of indices corresponding to the cells in $row_values to act on
   *   - valid_values => an array of values that are allowed within the cell(s) located
   *     at the indices specified in $context['indices']
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validateRow($row_values, $context) {

    // Check our inputs - will throw an exception if there's a problem
    $this->checkIndices($row_values, $context['indices']);

    $valid = TRUE;
    $failed_indices = [];
    // Keep track if we find a value that is the same but the wrong case (for
    // example, all caps was used when only title case is valid). This flag will
    // contribute to our error case reporting.
    $wrong_case = FALSE;
    // Convert our array of valid values to lower case for case insensitive
    // comparison
    $valid_values_lwr = array_map('strtolower', $context['valid_values']);

    // Iterate through our array of row values
    foreach($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices
      if (in_array($index, $context['indices'])) {
        // Check if our cell value is within the valid_values array
        if (!in_array($cell, $context['valid_values'])) {
          if (in_array(strtolower($cell), $valid_values_lwr)) {
            // We technically have a match, but the case doesn't match the valid value
            $wrong_case = TRUE;
          }
          $valid = FALSE;
          array_push($failed_indices, $index);
        }
      }
    }
    // Check if any values were invalid
    if (!$valid) {
      $failed_list = implode(', ', $failed_indices);
      $validator_status = [
        'title' => 'Invalid value(s) in required column(s)',
        'status' => 'fail',
        'details' => 'Invalid value(s) at index: ' . $failed_list
      ];
      if ($wrong_case) {
        $validator_status['title'] .= ' with >=1 case insensitive match';
      }
    } else {
      $passed_list = implode(', ', $context['indices']);
      $valid_values = implode(', ', $context['valid_values']);
      $validator_status = [
        'title' => 'Values in required column(s) were valid',
        'status' => 'pass',
        'details' => 'Value at index ' . $passed_list . ' was one of: ' . $valid_values . '.'
      ];
    }
    return $validator_status;
  }
}
