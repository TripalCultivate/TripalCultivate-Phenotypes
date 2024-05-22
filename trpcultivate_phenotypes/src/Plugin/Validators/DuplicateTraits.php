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
   * A nested array of already validated values
   */
  protected $unique_columns = [];

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
   *   - indices => an array of indices corresponding to the cells in $row_values to act on
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
    // using the indices stores in our $context array
    $trait = strtolower($row_values[$context['indices']['trait']]);
    $method = strtolower($row_values[$context['indices']['method']]);
    $unit = strtolower($row_values[$context['indices']['unit']]);

    // Now check for the presence of our array within our global array
    if (!empty($this->unique_columns)) {
      if ($this->unique_columns[$trait]) {
        if ($this->unique_columns[$method]) {
          if ($this->unique_columns[$unit]) {
            // Then we've found a duplicate

          }
        }
      }

    }

    // Check at the database level too

    // Finally, if not seen before, add to the global array
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
    //   - No duplicate trait name + method short name and unit for the same Genus.

    $error_types = [
      'duplicate' => [
        'key' => '#DUPLICATE',
        'info' => 'Duplicate traits (Trait Name + Method Short Name + Unit)',
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
        // Duplicate names.
        $unique_cols = '';
        if (isset($data[ $header_key['Trait Name'] ])
            && isset($data[ $header_key['Method Short Name'] ])
            && isset($data[ $header_key['Unit'] ])) {

          $unique_cols = $data[ $header_key['Trait Name'] ] . ' - '
            . $data[ $header_key['Method Short Name'] ] . ' - '
            . $data[ $header_key['Unit'] ];

          if (!in_array($unique_cols, array_keys($trait_count))) {
            $trait_count[ $unique_cols ] = [];
          }
        }

        // Record the line number trait names (combination) is used.
        // No need to track the column header as it is the combination of
        // Trait Name + Method Short Name and Unit.
        if ($unique_cols) {
          $trait_count[ $unique_cols ][] = $line_no;

          if (count($trait_count[ $unique_cols ]) > 1) {
            // Reference all duplicates.
            $failed_rows[ $error_types['duplicate']['key'] ][ $unique_cols ] = $trait_count[ $unique_cols ];
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
