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
 *   id = "trpcultivate_phenotypes_validator_valid_tsv_data_file",
 *   validator_name = @Translation("Valid TSV Data File Validator"),
 *   input_types = {"header", "raw-row"}
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
      
      // Check if the line has some delimiter used.

        // Check if the line uses other delimiter and values are properly escaped and wrapped in quotes.

           // Split the line and see if the number of values returned equals to the expected number of values.


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
}