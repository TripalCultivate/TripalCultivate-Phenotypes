<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ensure that the header row is not empty and that all expected column headers exist.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_header_row",
 *   validator_name = @Translation("Header Row Validator"),
 *   input_types = {"header-row"}
 * )
 */
class validHeader extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
   * Validate the header row.
   * 
   * @param array
   *    An array of values from a single row/line in the file where each element
   *    is a single column.
   * @param array $context
   *   @deprecated
   * 
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the header row is valid or not.
   *     - failedItems: the failed header row value provided. This will be empty if the file was valid.
   */
  public function validateRow($row_values, $context) {
    // Validator response values for a valid file.
    $case = 'Header row exists and match expected column headers';
    $valid = TRUE;
    $failed_items = '';

    // Header row.
    $header_row = $row_values;
    // @TODO: reference the expected headers defined by the importer.
    $importer_headers = ['Header 1', 'Header 2', 'Header 3', 'Header 4', 'Header 5'];

    // Test that the row values is not empty string, false, 0 or an empty array.
    if (empty($header_row)) {
      $case = 'Header row is an empty value';
      $valid = FALSE;
      // Since row is empty, this case is for empty input and
      // and setting this value to empty would oppose the valid
      // result, this text is used.
      $failed_items = 'header row is empty';  
    }
    else {
      // Test that expected headers exist in the row and in the correct order.
      // @TODO: confirm if the order of the header is important.
      $missing_headers = array_filter($importer_headers, function($expected_header) use($header_row) {
        return (!in_array($expected_header, $header_row));
      });

      if (count($missing_headers) > 0) {
        // Expected header/s missing from the header row.
        $case = 'Missing expected column headers';
        $valid = FALSE;
        $failed_items = implode(', ', $missing_headers);
      }
    }
     
    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}