<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\ColumnCount;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\FileTypes;

/**
 * Validate that a line in a data file is properly delimited.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "valid_delimited_file",
 *   validator_name = @Translation("Valid Delimited File Validator"),
 *   input_types = {"raw-row"}
 * )
 */
class ValidDelimitedFile extends TripalCultivatePhenotypesValidatorBase {

  /**
   * Validator Traits required by this validator.
   *
   * - FileTypes: get the MIME type of the input file (getFileMimeType)
   * - ColumnCount: get the expected number of columns (getExpectedColumns)
   */
  use FileTypes;
  use ColumnCount;

  /**
   * Perform validation of a raw row in a data file.
   *
   * Checks include:
   * - Line is not empty.
   * - It has some delimiter used to separate values.
   * - When split, the number of values returned is equal to the expected number
   *   of values in getExpectedColumns().
   *
   * @param string $raw_row
   *   A line in the data file that is not processed (ie. split by delimiter).
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the raw row is properly delimited, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with any of the following
   *      keys. This is an empty array if row is properly delimited.
   *      - 'raw_row': The raw row as it was provided.
   *      - 'expected_columns': The number of columns expected in the input file
   *        as determined by calling getExpectedColumns().
   *      - 'strict': A boolean indicating whether the number of expected
   *        columns by the validator is strict (TRUE) or is the minimum number
   *        required (FALSE).
   */
  public function validateRawRow(string $raw_row) {

    // Parameter check, verify that raw row is not an empty string.
    if (empty(trim($raw_row))) {
      return [
        'case' => 'Raw row is empty',
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
        ],
      ];
    }

    // Get the expected number of columns.
    $expected_columns = $this->getExpectedColumns();

    // Get the supported delimiters based on the input file's mime type.
    $input_file_mime_type = $this->getFileMimeType();
    $input_file_type_delimiters = $this->getFileDelimiters($input_file_mime_type);

    // Check the row includes at least one delimiter returned by
    // getFileDelimiters().
    $delimiters_used = [];
    foreach ($input_file_type_delimiters as $delimiter) {
      if (strpos($raw_row, $delimiter)) {
        array_push($delimiters_used, $delimiter);
      }
    }

    // Not one of the supported delimiters was detected in the raw row.
    if (empty($delimiters_used)) {

      // Validation passes if the raw row is not delimited and the expected
      // number of columns is set to 1.
      if ($expected_columns['number_of_columns'] == 1) {
        return [
          'case' => 'Raw row has expected number of columns',
          'valid' => TRUE,
          'failedItems' => [],
        ];
      }

      return [
        'case' => 'None of the delimiters supported by the file type was used',
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
        ],
      ];
    }

    $columns = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($raw_row, $input_file_mime_type);
    $no_cols = count($columns);

    if ($no_cols > $expected_columns['number_of_columns']) {
      // The line has more columns than expected.
      if ($expected_columns['strict']) {
        return [
          'case' => 'Raw row exceeds number of strict columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row,
            'expected_columns' => $expected_columns['number_of_columns'],
            'strict' => $expected_columns['strict'],
          ],
        ];
      }
    }

    if ($no_cols < $expected_columns['number_of_columns']) {
      // The line has less column than expected.
      return [
        'case' => 'Raw row has insufficient number of columns',
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
          'expected_columns' => $expected_columns['number_of_columns'],
          'strict' => $expected_columns['strict'],
        ],
      ];
    }

    return [
      'case' => 'Raw row is delimited',
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

}
