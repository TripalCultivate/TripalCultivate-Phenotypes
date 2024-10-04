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
   *     - failedItems: an associative array indicating the failed raw row under the 'raw_row' key. This will be an empty array if the line was delimited.
   */
  public function validateRawRow(string $raw_row) {

    // Parameter check, verify that raw row is not an empty string value.
    if (empty(trim($raw_row))) {
      return [
        'case' => 'Raw row is empty',
        'valid' => FALSE,
        'failedItems' => ['raw_row' => 'is an empty string value']
      ];
    }

    // Reference the expected number of columns.
    $expected_columns = $this->getExpectedColumns();

    // Based on the file mime type of the input file, reference the delimiter
    // defined specific to the type.
    $input_file_mime_type = $this->getFileMimeType();
    $input_file_type_delimiters = $this->getFileDelimiters($input_file_mime_type);

    // Check if the line has some delimiters used, specifically, check the line
    // includes at least one of the delimiters returned by getFileDelimiters().
    $delimiters_used = [];
    foreach($input_file_type_delimiters as $delimiter) {
      if (strpos($raw_row, $delimiter)) {
        array_push($delimiters_used, $delimiter);
      }
    }

    // Not one of the supported delimiters was detected in the raw row.
    if (empty($delimiters_used)) {

      // Return a valid response if the raw raw is not delimited value
      // and the expected number of columns is set to 1.
      if ($expected_columns['number_of_columns'] == 1) {
        return [
          'case' => 'Raw row has expected number of columns',
          'valid' => TRUE,
          'failedItems' => []
        ];
      }

      return [
        'case' => 'None of the delimiters supported by the file type was used',
        'valid' => FALSE,
        'failedItems' => ['raw_row' => $raw_row]
      ];
    }

    // With a list of delimiters identified in the raw row, test each delimiter to see
    // if it can split the raw row into values and meet the expected number of columns.
    // Store every delimiter that failed into the failed delimiters array.
    $delimiters_failed = [];

    foreach($delimiters_used as $delimiter) {
      $columns = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($raw_row, $input_file_mime_type);

      if ($expected_columns['strict']) {
        // A strict comparison - exact match only.
        if (count($columns) != $expected_columns['number_of_columns']) {
          array_push($delimiters_failed, $delimiter);
        }
      }
      else {
        // Not a strict comparison - at least x number of columns.
        if (count($columns) < $expected_columns['number_of_columns']) {
          array_push($delimiters_failed, $delimiter);
        }
      }
    }

    // If failed delimiters array contains every delimiters in the list of delimiters used,
    // then not one of the delimiters was able to split the line as required.
    if ($delimiters_used == $delimiters_failed) {
      return [
        'case' => 'Raw row is not delimited',
        'valid' => FALSE,
        'failedItems' => ['raw_row' => $raw_row]
      ];
    }

    return [
      'case' => 'Raw row is delimited',
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
   *   number of columns required in an input file's row, or if it is strictly the only.
   *   acceptable number of columns.
   *   - FALSE (default) = minimum number of columns.
   *   - TRUE = the strict number of required columns.
   *
   * @return void
   *
   * @throws \Exception
   *  - The number of columns is less than or equals to 0.
   */
  public function setExpectedColumns(int $number_of_columns, bool $strict = FALSE) {

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
