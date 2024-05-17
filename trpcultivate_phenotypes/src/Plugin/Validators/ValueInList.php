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
   *   - indices => an array of indices corresponding to the cells in $row to act on
   *   - valid_values => an array of values that are allowed within the cell(s) located
   *     at indices in $context['indices']
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
    // Iterate through our array of row values
    foreach($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices
      if (in_array($index, $context['indices'])) {
        // Check if our cell value is within the valid_values array
        if (!in_array($cell, $context['valid_values'])) {
          $valid = FALSE;
          array_push($failed_indices, $index);
        }
      }
    }
    // Check if any values were invalid
    if (!$valid) {
      $failed_list = implode(', ', $failed_indices);
      $validator_status = [
        'title' => 'Invalid value(s) found in required column(s)',
        'status' => 'fail',
        'details' => 'Invalid value(s) at index: ' . $failed_list
      ];
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

  /**
   * Validate items in the phenotypic data upload assets.
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   *
   *   NOTE: keep track of the line no to point user exactly which line failed.
   */
  public function validate() {
    // Validate ...
    $validator_status = [
      'title' => 'Column Headers have Values',
      'status' => 'pass',
      'details' => ''
    ];

    // Instructed to skip this validation. This will set this validator as upcoming or todo.
    // This happens when other prior validation failed and this validation could only proceed
    // when input values in the failed validator have been rectified.
    if ($this->skip) {
      $validator_status['status'] = 'todo';
      return $validator_status;
    }

    // Values:
    //   - Column type value is either Quantitative or Qualitative.

    $error_types = [
      'unexpected' => [
        'key' => '#UNEXPECTED',
        'info' => 'Unexpected Type (Qualitative or Quantitative only)',
      ]
    ];

    // Load file object.
    $file = File::load($this->file_id);
    // Open and read file in this uri.
    $file_uri = $file->getFileUri();
    $handle = fopen($file_uri, 'r');

    // Line counter.
    $line_no = 0;
    // Line check - line that has value and is not empty.
    $line_check = 0;
    // Array to hold all failed line, sorted by error type.
    $failed_rows = [];
    // Count each time a trait is process. This will be used
    // to check for any duplicate trait name in the same genus.
    $trait_count = [];
    // Header by keys.
    $header_key = array_flip($this->column_headers);


    // Begin column and row validation.
    while(!feof($handle)) {
      // Current row.
      $line = fgets($handle);

      if ($line_no > 0 && !empty(trim($line))) {
        $line_check++;

        // Header row is index 0.
        // Continue to data row. No need to use the headers in the file
        // instead use the headers property from the importer.

        // Data rows.
        // On wards, line number starts at #1.

        // Line split into individual data point.
        $data_columns = str_getcsv($line, "\t");

        // Sanitize every data in rows and columns.
        $data = array_map(function($col) { return isset($col) ? trim(str_replace(['"','\''], '', $col)) : ''; }, $data_columns);

        // Validate.

        // Empty and unexpected value.
        foreach($this->column_headers as $i => $header) {
          if (isset($data[ $i ]) || empty($data[ $i ])) {
            // Has value. Check for unexpected values.
            if ($header == 'Type') {
              $type = strtolower($data[ $i ]);

              if (!in_array($type, ['qualitative', 'quantitative'])) {
                // Unexpected value in Type column.
                $failed_rows[ $error_types['unexpected']['key'] ]['Trait'][] = $line_no;
              }
            }
          }
        }

        // Reset data.
        unset($data);
      }

      $line_no++;
    }

    // Close the file.
    fclose($handle);

    // Prepare summary report.
    if (count($failed_rows) > 0) {
      $line = [];

      // Each error type, construct error message.
      foreach($error_types as $type => $error) {
        if (isset($failed_rows[ $error_types[ $type ]['key'] ])) {
          // Error type.
          $line[ $error_types[ $type ]['key'] ] = $error['info'];

          // Error line number and column header.
          if ($type == 'empty') {
            foreach($failed_rows[ $error_types[ $type ]['key'] ] as $line_no => $header) {
              $str_headers = implode(', ', $header);
              $line[ $error_types[ $type ]['key'] ] .= ' @ line #' . $line_no . ' Column(s): ' . $str_headers;
            }
          }
          else {
            foreach($failed_rows[ $error_types[ $type ]['key'] ] as $line_no) {
              $str_lines = implode(', ', $line_no);
              $line[ $error_types[ $type ]['key'] ] .= ' @ line #' . $str_lines;
            }
          }
        }
      }

      // Report validation result.
      $validator_status = [
        'title' => 'Column Headers have Values',
        'status' => 'fail',
        'details' => $line,
      ];
    }

    return $validator_status;
  }
}
