<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\ColumnCount;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\Headers;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that the header row is not empty and that all expected column headers exist.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "valid_headers",
 *   validator_name = @Translation("Header Row Validator"),
 *   input_types = {"header-row"}
 * )
 */
class ValidHeaders extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * This validator requires the following validator traits:
   * - Headers - getHeaders: get all headers.
   * - ColumnCount - getExpectedColumns: get the expected number of columns and strict comparison flag.
   */
  use Headers, ColumnCount;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * Validate the header row.
   * Checks include:
   *  - Each header value is a non-empty string.
   *  - No header is missing.
   *  - The order of headers defined by the Importer should match the order of headers array.
   *
   * @param array $headers
   *   An array of headers created by splitting the first line of the data file into separate values.
   *   The index of the header represents the order they appear in the line.
   *
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the header is valid or not.
   *     - failedItems: the failed headers. This will be an empty array if the header was valid.
   */
  public function validateRow($headers) {
    $input_headers = $headers;

    // Parameter check, verify that the headers array input is not an empty array.
    if (empty($headers)) {
      // Headers array is an empty array.
      return [
        'case' => 'Header row is an empty value',
        'valid' => FALSE,
        'failedItems' => ['headers' => 'headers array is an empty array']
      ];
    }

    // Reference the list of expected headers.
    $expected_headers = $this->getHeaders();
    dpm($expected_headers, 'Expected headers');
    dpm($headers, 'Input Headers');

    foreach ($expected_headers as $header) {
      // Each header name in the expected headers array will be verified for both
      // index order and presence in the headers provided. Terminate varification
      // on the first instance of failed result.

      // Take one item from the headers input and compare it to
      // the current expected header.
      $cur_input_header = array_shift($input_headers);
      dpm($header . ' --- ' . $cur_input_header);

      if ($cur_input_header && $header != trim($cur_input_header)) {
        return [
          'case' => 'Headers do not match expected headers >' . $header . $cur_input_header,
          'valid' => FALSE,
          'failedItems' => $headers
        ];
      }
    }

    // Reference the expected number of columns and strict comparison flag.
    $expected_columns =  $this->getExpectedColumns();

    if ($expected_columns['strict'] && $expected_columns['number_of_columns'] != count($headers)) {
      // The importer specified a strict requirement for the headers input array
      // to have a specific number of elements, and this check found more or less
      // than the required.

      return [
        'case' => 'Headers provided does not have the expected number of headers',
        'valid' => FALSE,
        'failedItems' => $headers
      ];
    }

    // At this point the headers input array is valid.
    return [
      'case' => 'Headers exist and match expected headers',
      'valid' => TRUE,
      'failedItems' => []
    ];
  }
}
