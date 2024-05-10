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
    //   - Ensure that each column header has a value.
    $error_types = [
      'empty' => [
        'key' => '#EMPTY', 
        'info' => 'Empty values',
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
        // Empty value
        foreach($this->column_headers as $i => $header) {
          if (!isset($data[ $i ]) || empty($data[ $i ])) {
            // Empty values.
            // Track both line number and which column header.
            $failed_rows[ $error_types['empty']['key'] ][ $line_no ][] = $header;
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