<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\trpcultivate_phenotypes\Plugin\Helper\ValuesValidatorPluginHelper;
use Drupal\file\Entity\File;

/**
 * Validate Data Values of Phenotypes - Share Importer.
 * 
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_phenoshare_values",
 *   validator_name = @Translation("PhenoShare Importer Values Validator"),
 *   validator_scope = "PHENOSHARE IMPORT VALUES",
 * )
 */
class PhenoShareImportValues extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  // Values validator plugin helper.
  protected $plugin_helper;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ValuesValidatorPluginHelper $plugin_helper) { 
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->plugin_helper = $plugin_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.values_validator_plugin_helper')
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
      'title' => 'All columns have values and value matched the column data type',
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
    //   - Trait Name, Method Name, Unit and Germplasm must exist. NOTE: check if setting to allow new trait, method and unit
    //     configuration is set to NOT allow before validating.
    //   - Expected Type/Value - Year is a 4 digit year no more than the current year
    //     Value must coincide with the unit type, qualitative or quantitative or text or number respectively
    //     Replicate is number > 0

    $error_types = [
      'empty' => [
        'key' => '#EMPTY', 
        'info' => 'Empty value',
      ],
      'unrecognized' => [
        'key' => '#UNRECOGNIZED ',
        'info' => 'Unrecognized value',
      ],
      'unexpected' => [
        'key' => '#UNEXPECTED',
        'info' => 'Unexpected value',
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
    // Header by keys.
    $header_key = array_flip($this->column_headers);

    // Call values validator plugin helper.
    $this->plugin_helper->setGenus($this->genus);
    
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
        
        // Empty values.
        // Counter for empty value in a line.
        $empty_ctr = 0;
        foreach($this->column_headers as $i => $header) {
          if (!isset($data[ $i ]) || $data[ $i ] == '') {
            $empty_ctr++;
            $failed_rows[ $error_types['empty']['key'] ][ $line_no ][] = $header;
          }
        }

        // Perform block only when there is no empty value in a column
        // through the use of empty counter variable value.
        if ($empty_ctr == 0) {
          // UNRECOGNIZED VALUES VALIDATION:
          $unrecognized = $error_types['unrecognized']['key'];

          // Trait Name:
          $col = 'Trait Name';
          $trait_name = $data[ $header_key[ $col ] ];
          $trait_exists = $this->plugin_helper->validatorTraitExists($trait_name);
          if (!$trait_exists) {
            // Trait name does not exist.
            $failed_rows[ $unrecognized ][ $col ][ $line_no ][] = $col . ': ' . $trait_name; 
          }
          
          // Method Name:
          // This is the Method Short Name of a Trait.
          $col = 'Method Name';
          $method_name = $data[ $header_key[ $col ] ];
          $method_exists = $this->plugin_helper->validatorMethodNameExists($trait_name, $method_name);
          if (!$method_exists) {
            // Method name does not exist.
            $failed_rows[ $unrecognized ][ $col ][ $line_no ][] = $col . ': ' . $method_name; 
          }

          // Unit:
          $col = 'Unit';
          $unit_name = $data[ $header_key[ $col ] ];
          $unit_exists = $this->plugin_helper->validatorUnitNameExists($trait_name, $method_name, $unit_name);
          if (!$unit_exists) {
            // Unit name does not exist.
            $failed_rows[ $unrecognized ][ $col ][ $line_no ][] = $col . ': ' . $unit_name; 
          }


          // UNEXPECTED DATA TYPE OF VALUES VALIDATION:
          $unexpected = $error_types['unexpected']['key'];

          // Year:
          // A four digit value and no more than the current year.
          $col = 'Year';
          $year = $data[ $header_key[ $col ] ];
          
          $is_year = $this->plugin_helper->validatorMatchDataType($year, 'FOUR_DIGIT_YEAR');
          if (!$is_year['status']) {
            // Year is not valid. Expected value is 4 digit value.
            $failed_rows[ $unexpected ][ $col ][ $line_no ][] = $col . ': ' . $year . ' (expected: ' . $is_year['info'] . ')'; 
          }

          // Replicate:
          // Number greater than 0.
          $col = 'Replicate';
          $replicate = $data[ $header_key[ $col ] ];
          $is_replicate = $this->plugin_helper->validatorMatchDataType($replicate, 'NO_ZERO_NUMBER');
          if (!$is_replicate['status']) {
            // Replicate is not valid. Expected value is number greater than 0.
            $failed_rows[ $unexpected ][ $col ][ $line_no ][] = $col . ': ' . $replicate . ' (expected: ' . $is_replicate['info'] . ')';
          }

          if ($trait_exists && $method_exists && $unit_exists) {
            // Value and Unit:
            // Unit data type of either qualitative (text) or quantitative (number).
            $col = 'Value';
            $value = $data[ $header_key[ $col ] ];
            $is_match = $this->plugin_helper->validatorMatchValueToUnit($trait_name, $method_name, $unit_name, $value);
            if (!$is_match['status']) {
              $failed_rows[ $unexpected ][ $col ][ $line_no ][] = $col . ': ' . $value . '(expected: ' . $is_match['info'] . ')';
            }
          }
        }
      }  

      $line_no++;
    } 

    // Close the file.
    fclose($handle);
  
    // It seems the file has no data rows.
    if (!$line_check) {
      // Report validation result.
      $validator_status = [
        'title' => 'All columns have values and value matched the column data type',
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

          
          // @TODO: Report lines that failed.
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