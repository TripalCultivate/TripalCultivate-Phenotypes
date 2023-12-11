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
    
    // Keys to indicate an empty value, data type mismatch
    // and unrecognized trait, method, unit and/or germplasm.
    $empty = '#EMPTY_VALUE';
    $mismatch = '#TYPE_MISMATCH';
    $unrecognized = '#UNRECOGNIZED';

    $validator_status = [
      'title' => 'All columns have values and value matched the column data type',
      'status' => 'pass',
      'details' => [
        $empty   =>  ['details' => 'Empty values'],
        $mismatch  =>  ['details' => 'Unexpected data type'],
        $unrecognized => ['details' => 'Unrecognized Trait, Unit, Method or Germplasm'],
      ],
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
    
    // Load file object.
    $file = File::load($this->file_id);
    // Open and read file in this uri.
    $file_uri = $file->getFileUri();
    $handle = fopen($file_uri, 'r');
    
    // Line counter.
    $line_no = 0;
    // Array to hold all failed line.
    $failed_rows = [];

    // Call values validator plugin helper.
    $this->plugin_helper->setGenus($this->genus);

    // Begin column and row validation.
    while(!feof($handle)) {
      // Current row.
      $line = fgets($handle);

      if ($line_no > 0 && !empty(trim($line))) {
        // Header row is index 0.
        // Continue to data row. No need to use the headers in the file
        // instead use the headers property from the importer.  
     
        // Data rows.
        // On wards, line number starts at #1.

        // Line split into individual data point.
        $data_columns = str_getcsv($line, "\t");

        // Sanitize every data in rows and columns.
        $data = array_map(function($col) { return isset($col) ? trim(str_replace(['"','\''], '', $col)) : ''; }, $data_columns);
        // Quick access of data values by column header.
        $header_keys = array_flip($this->column_headers);

        foreach($data as $i => $value) {
          // Reference the trait name.
          $trait_name = $data[ $header_keys['Trait Name'] ];
          // Reference the method name.
          $method_name = $data[ $header_keys['Method Name'] ];
          
          if (isset($this->column_headers[ $i ])) {
            // Current header to check.
            $current_header = $this->column_headers[ $i ];
            
            // Validate:
            if (empty($value)) {
              // Empty cell.
              $failed_rows[ $empty ][ $line_no ][] = $current_header;
            }
            else {
              // Has a value, validate if data entry exists.
              
              if ($current_header == 'Trait Name') {
                // Check if header is trait - trait exits.
                $trait_exists = $this->plugin_helper->validatorTraitExists($value);
                if (!$trait_exists) {
                  $failed_rows[ $unrecognized ]['TRAIT'][ $line_no ][] = $current_header; 
                }
              }
  
              if ($current_header == 'Method Name') {
                // Check if header is method name - method name exits.
                $method_exists = $this->plugin_helper->validatorMethodNameExists($trait_name, $value);
                if (!$method_exists) {
                  $failed_rows[ $unrecognized ]['METHOD'][ $line_no ][] = $current_header; 
                }
              }
  
              if ($current_header == 'Unt') {
                // Check if header is unit - method unit exits.
                $unit_exists = $this->plugin_helper->validatorUnitNameExists($trait_name, $method_name, $value);
                if (!$unit_exists) {
                  $failed_rows[ $unrecognized ]['UNIT'][ $line_no ][] = $current_header; 
                }
              }
            }
          }
        }

        unset($data);
      }

      $line_no++;
    } 
  
    // Close the file.
    fclose($handle);

    // Array to hold all failed line.
    if (count($failed_rows) > 0) {

      // Report validation result.
      $validator_status = [
        'title' => 'Column Headers have Values',
        'status' => 'fail',
        'details' => ''
      ];  
    }

    return $validator_status;
  }
}