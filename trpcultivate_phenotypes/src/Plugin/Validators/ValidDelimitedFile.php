<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\FileTypes;
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
   * This validator requires the following validator traits:
   * - FileTypes - getFileMimeType: get the MIME type of the input file.
   */
  use FileTypes;
  
  /**
   * The key used by the setter method to create a validator configuration element 
   * in the context array, as well as the key used by the getter method 
   * to reference and retrieve the element values. 
   * 
   * @var string
   */
  private $validator_context_key = 'ValidDelimitedFile';


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
   * Perform validation of a data file raw row.
   * Checks include:
   *  - Line is not empty.
   *  - It has some delimiter used to separate values.
   *  - When split, the number of values returned is equal to the expected number of values
   *    set by the validator setter method.
   * 
   * @param string $raw_row
   *   A line in the data file that can be the headers row (line no. 1) or a data row.
   * 
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the raw row is delimited or not.
   *     - failedItems: the failed raw row. This will be an empty array if the line was delimited.
   */
  public function validateRawRow($raw_row) {
    
    // Parameter check, verify that raw row is not an empty string value.
    if (empty(trim($raw_row))) {
      return [
        'case' => 'Raw row is empty',
        'valid' => FALSE,
        'failedItems' => ['raw_row' => 'is an empty string value']
      ];
    }

    // Check if the line has some delimiters used (only if number of expected columns is greater than 1).
    $expected_columns = $this->getExpectedColumns();
    if ($expected_columns['number_of_columns'] == 1) {
      return [
        'case' => 'Data file raw row is delimited',
        'valid' => TRUE,
        'failedItems' => []
      ];
    }

    // Specifically, check the line includes at least one of the delimiters returned by the get file delimiter method. 
    $input_file_mime_type = $this->getFileMimeType();
    $input_file_type_delimiters = $this->getFileDelimiters($input_file_mime_type);

    $delimiters_used = [];
    foreach($input_file_type_delimiters as $delimiter) {
      if (strpos($raw_row, $delimiter)) {
        array_push($delimiters_used, $delimiter);
      }
    }
      
    // Not one of the supported delimiters was detected in the raw row.
    if (empty($delimiters_used)) {
      return [
        'case' => 'None of the delimiters supported by the file type was used',
        'valid' => FALSE,
        'failedItems' => ['raw_row' => $raw_row]
      ];
    }

    // Split the line and see if the number of values returned equals to the expected number of columns.
    // Use the strict flag of the validator columns configuration to compare the columns returned by
    // split method and the configured columns.

    // A strict flag set to True means exact match whereas False requires at least the configured columns.
    $delimiters_checked = [];
    foreach($delimiters_used as $delimiter) {
      $columns = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($raw_row, $input_file_mime_type);

      if ($expected_columns['strict']) {
        // A strict comparison - exact match only.
        if (count($columns) != $expected_columns['number_of_columns']) {
          array_push($delimiters_checked, $delimiter);
        }
      }
      else {
        // Not a strict comparison - at least x number of columns.
        if (count($columns) < $expected_columns['number_of_columns']) {
          array_push($delimiters_checked, $delimiter);
        }
      }
    }

    // If all delimiters failed the checks, then the line failed due to none of the
    // delimiter was able to split the line into the expected number of columns.
    if ($delimiters_used == $delimiters_checked) {
      return [  
        'case' => 'Raw row is not delimited',
        'valid' => FALSE,
        'failedItems' => ['raw_row' => $raw_row]
      ];
    }

    return [
      'case' => 'Data file raw row is delimited',
      'valid' => TRUE,
      'failedItems' => []
    ];
  }

  /**
   * Set a number of required columns.
   * 
   * @param integer $number_of_columns
   *   An integer value greater than zero.  
   * @param bool $strict
   *   This will indicate whether the value $number_of_columns is the minimum
   *   number of columns required in an input file's row, or if it is strictly the only
   *   acceptable number of columns.
   *   - FALSE (default) = minimum number of columns
   *   - TRUE = the strict number of required columns
   * 
   * @return void
   * 
   * @throws \Exception
   *  - The value 0 as number of columns.
   */
  public function setExpectedColumns($number_of_columns, $strict = FALSE) {

    $context_key = $this->validator_context_key;

    if ($number_of_columns <= 0) {
      throw new \Exception('setExpectedColumns() in validator requires an integer value greater than zero.');
    }

    $this->context[ $context_key ] = [
      'number_of_columns' => $number_of_columns,
      'strict'  => $strict 
    ];
  }

  /**
   * Get the number of columns set.
   * 
   * @return array
   *   The expected column number and strict flag validator configuration
   *   set by the setter method, keyed by:
   *   - number_of_columns: the number of expected column number.
   *   - strict: strict comparison flag.
   * 
   * @throws \Exception
   *  - The column number was not configured by the setter method.
   */
  public function getExpectedColumns() {

    $context_key = $this->validator_context_key;

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve the number of expected columns as one has not been set by setExpectedColumns().');
    }
  }
}