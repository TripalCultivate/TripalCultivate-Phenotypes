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
 * Validate a line in a data file is properly delimited.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "valid_delimited_file",
 *   validator_name = @Translation("Valid Delimited File Validator"),
 *   input_types = {"raw-row"}
 * )
 */
class ValidDelimitedFile extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
   * Perform validation of a line in a data file.
   * Checks include:
   *  - Line is not empty.
   *  - It has some delimiter used to separate values.
   *  - Other delimiters used in the same data file are escaped and/or in a quote.
   *  - When split, the number of values returned is equal to the expected number of values.
   * 
   * @param string $raw_row
   *   A line in the data file that can be the headers row (line no. 1) or a data row.
   * 
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the header row is tab-separated or not.
   *     - failedItems: the failed header row. This will be an empty array if the file was valid.
   */
  public function validateRawRow($raw_row) {
    
    // Validator response values for a valid header row.
    $case = 'Data file content is valid tab-separated values (tsv)';
    $valid = TRUE;
    $failed_items = [];
    
    // Check if the line is empty.
    if (empty(trim($raw_row))) {
      
      $expected_columns = $this->getExpectedColumns();

      if ($expected_columns['number_of_columns'] > 1) 
        // Check if the line has some delimiter used (only if number of expected columns > 1)
        // Specifically the line includes at least one of the delimiters returned by the get file delimiter method. 
        $file_mime_type = $this->getFileMimeType();
        $file_delimiters = $this->getFileDelimiters($file_mime_type);
        
        $delimiter_used = [];
        foreach($file_delimiters as $delimiter) {
          if (strpos($raw_row, $delimiter)) {
            array_push($delimiter_used, $delimiter);
          }
        }
       
        // Split the line and see if the number of values returned equals to the expected number of values.
        $delimiter_check = [];

        if ($delimiter_used) {
          foreach($delimiter_used as $delimiter) {
            $columns = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($raw_row, $file_mime_type);
            
            if ($expected_columns['strict']) {
              // Strict comparison. Exact match (no more, no less).

              if(count($columns) != $expected_columns['number_of_columns']) {
                // Delimiter maybe established, continue the loop.
                // If all delimiters satisfy the count check then it is safe to assume that the line is properly
                // delimited and one of the delimiters was used.

                // Line is delimited correctly.
                continue;
              }
              else {
                // Line split failed, save the delimiter.
                array_push($delimiter_failed, $delimiter); 
              }
            }
            else {
              // Not strict comparison. At least x number of columns.

              if (count($columns) < $expected_columns['number_of_columns']) {
                // Delimiter maybe established, continue the loop.
                // If all delimiters satisfy the count check then safe to assume that the line is properly
                // delimited and one of the delimiters was used.

                // Line is delimited correctly.
                continue;
              }
              else {
                // Line split failed, save the delimiter.
                array_push($delimiter_check, $delimiter);
              }
            }
          }

          // If all delimiters failed the checks then the line failed due to none of the delimiter
          // was able to split the line into expected number of values.
          if ($delimiter_used == $delimiter_check) {
            $case = 'Line is not delimited correctly';
            $valid = FALSE;
            $failed_items = ['raw_row' => $raw_row];
          }
        }
        else {
          // Not using any one of the supported delimiters.
          $case = 'No delimiter used';
          $valid = FALSE;
          $failed_items = ['raw_row' => $raw_row];
        }
      }          
    }
    else {
      // The line provided is an empty string.
      $case = 'Raw line is empty';
      $valid = FALSE;
      $failed_items = ['raw_row' => $raw_row];
    }
    
    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }

  /**
   * Set a number of required columns.
   * 
   * @param integer $number_of_columns
   * @param bool $strict
   * 
   * @return void
   * 
   * @throws \Exception
   *  - A 0 number of columns
   */
  public function setExpectedColumns($number_of_columns, $strict = FALSE) {

    $context_key = get_class();

    if ($number_of_columns <= 0) {
      throw new \Exception('The setter method in ' . $context_key . ' requires an integer value greater than zero.' );
    }

    $this->context[ $context_key ] = [
      'number_of_columns' => $number_of_columns,
      'strict'  => $strict 
    ];
  }

  /**
   * Get the number of columns set.
   * 
   * @return array.
   * 
   * @throws \Exception
   *  - Column numbers not set. 
   */
  public function getExpectedColumns() {

    $context_key = get_class();

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve the values set by ' . $context_key . ' setter method.');
    }
  }
}