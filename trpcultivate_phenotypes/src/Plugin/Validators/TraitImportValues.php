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
 * Validate Data Values of Traits Importer.
 * 
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_traits_values",
 *   validator_name = @Translation("Traits Importer Values Validator"),
 *   validator_scope = "TRAIT IMPORT VALUES",
 * )
 */
class TraitImportValues extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
    // Keys to indicate and EMPTY value and DUPLICATE trait name in the same genus.
    $error_types = [
      'empty' => [
        'key' => '#EMPTY', 
        'info' => 'Empty values',
      ],
      'duplicate' => [
        'key' => '#DUPLICATE',
        'info' => 'Duplicate traits',
      ],
      'unexpected' => [
        'key' => '#UNEXPECTED',
        'info' => 'Unexpected Type (Qualitative or Quantitative only)',
      ] 
    ];

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
    //   - Column type value is either Quantitative or Qualitative.
    //   - No duplicate trait name + method short name and unit in the same Genus (data file).
    
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
        }

        if (count($trait_count[ $unique_cols ]) > 1) {
          // Reference all duplicates.
          foreach($trait_count[ $unique_cols ] as $duplicate_lines) {
            $failed_rows[ $error_types['duplicate']['key'] ][ $duplicate_lines ][] = '';
          }
        }

        // Empty and unexpected value.
        foreach($this->column_headers as $i => $header) {
          if (!isset($data[ $i ]) || empty($data[ $i ])) {
            // Empty values.
            // Track both line number and which column header.
            $failed_rows[ $error_types['empty']['key'] ][ $line_no ][] = $header;
          }
          else {
            // Has value. Check for unexpected values.
            if ($header == 'Type') {
              $type = strtolower($data[ $i ]);
              
              if (!in_array($type, ['qualitative', 'quantitative'])) {
                // Unexpected value in Type column.
                // No need to track the column header as it is the Type column.
                $failed_rows[ $error_types['unexpected']['key'] ][ $line_no ][] = '';
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

    // It seems the file has no data rows.
    if (!$line_check) {
      // Report validation result.
      $validator_status = [
        'title' => 'Column Headers have Values',
        'status' => 'fail',
        'details' => 'Data file has no data rows to process. Please upload a file and try again.'
      ];

      return $validator_status;
    }
    
    // Prepare summary report.
    if (count($failed_rows) > 0) {
      $line = [];
      
      // Each error type, construct error message.
      foreach($error_types as $type => $error) {
        if (isset($failed_rows[ $error_types[ $type ]['key'] ])) {
          // Error type.
          $line[ $error_types[ $type ]['key'] ] = $error['info'];

          // Error line number and column header.
          foreach($failed_rows[ $error_types[ $type ]['key'] ] as $line_no => $header) {
            // @TODO: Construct line message.
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