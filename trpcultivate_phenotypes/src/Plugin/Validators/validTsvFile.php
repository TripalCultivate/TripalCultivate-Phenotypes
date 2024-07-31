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
 * Validate that project exits.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_valid_tsv_data_file",
 *   validator_name = @Translation("Valid TSV Data File Validator"),
 *   input_types = {"header"}
 * )
 */
class validTsvFile extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
   * Perform file validation specific to a tab-separated values (tsv) data file.
   * 
   * @param string $row_values
   *   The contents of the file's first row (header row) where each value within a cell is
   *   stored as an array element.
   * 
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the header row is tab-separated or not.
   *     - failedItems: the failed header row. This will be empty if the file was valid.
   */
  public function validateRow($row_values, $context) {
    // @TODO: Remove context parameter (marked deprecated).
    
    // Validator response values for a valid header row.
    $case = 'Data file content is valid tab-separated values (tsv)';
    $valid = TRUE;
    $failed_items = '';
   
    $items = str_getcsv($row_values, "\t");
    // @TODO: a way to get the count of importer expected column headers.
    $expected_column_count = 7;
    
    // Tab check by comparing the number of items from splitting the string
    // by tab to the number of items expected.
    if (count($items) != $expected_column_count) {
      $case = 'Data file header row is not a tab-separated values';
      $valid = FALSE;
      $failed_items = $row_values;
    }

    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}