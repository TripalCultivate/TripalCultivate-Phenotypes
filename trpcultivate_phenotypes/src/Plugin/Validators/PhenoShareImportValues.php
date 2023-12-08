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
      'details' => [
        '#EMPTY_VALUE'  =>  ['details' => 'Empty values'],
        '#TYPE_MISMATCH' => ['details' => 'Unexpected data type'],
        '#UNRECOGNIZED' =>  ['details' => 'Unrecognized Trait, Unit, Method or Germplasm'],
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
               
        foreach($data as $i => $value) {
          
          
          if (empty($value) && isset($this->column_headers[ $i ])) {
            // EMPTY VALUES.
            $failed_rows[ $line_no ][] = $this->column_headers[ $i ]; 
          }
          else {
            if ($this->column_headers[ $i ] == 'Trait Name') {
              // Check if header is trait - trait exits
              $trait_exists = $this->plugin_helper->validatorTraitExists($value);
              if (!$trait_exists) {
                $failed_rows[ $line_no ][] = $this->column_headers[ $i ]; 
              }
            }


            // Check if header is method - method exits
            // Check if header is unit - unit exits

            // Check if header is year - must be four digit and no future year
            // Check if header is replicate - integer
            // Check if header is value - check the data type for the unit and compare.
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
      // Construct failed lines to say: line number - list of headers that has empty value.
      $line = 'Empty values found in the following line number and column header: ';
      foreach($failed_rows as $line_no => $headers) {
        $line .= '@ line #' . $line_no . ' Column(s): ' . implode(', ', $headers) . ' ';
      }

      // Report validation result.
      $validator_status = [
        'title' => 'Column Headers have Values',
        'status' => 'fail',
        'details' => $line
      ];  
    }

    return $validator_status;
  }
}