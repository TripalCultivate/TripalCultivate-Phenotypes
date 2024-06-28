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
 * Validate empty cells of an importer.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_empty_cell",
 *   validator_name = @Translation("Empty Cell Validator"),
 *   validator_scope = "FILE ROW",
 * )
 */
class EmptyCell extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
   *
   * @param array $row_values
   *   The contents of the file's row where each value within a cell is
   *   stored as an array element
   * @param array $context
   *   An associative array with the following key:
   *   - indices => an array of indices corresponding to the cells in $row_values to act on
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validateRow($row_values, $context) {

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem
    $this->checkIndices($row_values, $context['indices']);

    $empty = FALSE;
    $failed_indices = [];
    // Iterate through our array of row values
    foreach($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices
      if (in_array($index, $context['indices'])) {
        // First trim the contents of our cell in case we have whitespace
        $cell = trim($cell);
        // Check if our content is empty and report an error if it is
        if (!isset($cell) || empty($cell)) {
          $empty = TRUE;
          array_push($failed_indices, $index);
        }
      }
    }
    // Check if empty values were found that should not be empty
    if ($empty) {
      $failed_list = implode(', ', $failed_indices);
      $validator_status = [
        'title' => 'Empty value found in required column(s)',
        'status' => 'fail',
        'details' => 'Empty values at index: ' . $failed_list
      ];
    } else {
      $validator_status = [
        'title' => 'No empty values found in required column(s)',
        'status' => 'pass',
        'details' => ''
      ];
    }
    return $validator_status;
  }
}
