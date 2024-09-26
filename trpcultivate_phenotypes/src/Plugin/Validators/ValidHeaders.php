<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
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
   */
  use Headers;

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

    // Parameter check, verify that the headers array input is not an empty array.
    if (empty($headers)) {
      // Headers array is an empty array.
      return [
        'case' => 'Header row is an empty value',
        'valid' => FALSE,
        'failedItems' => ['headers' => 'headers array is an empty array']
      ];
    }

    // Check that no headers are missing by verifying that all expected headers are present.
    // Get all the headers defined by the importer regardless of type.
    $expected_headers = $this->getHeaders();
    $expected_header_names = array_values($expected_headers);

    $missing_headers = array_filter($expected_header_names, function ($names) use ($headers) {
      return (!in_array($names, $headers));
    });

    if ($missing_headers) {
      $missing_headers = implode(', ', $missing_headers);

      return [
        'case' => 'Missing expected headers',
        'valid' => FALSE,
        'failedItems' => ['headers' => $missing_headers]
      ];
    }

    // Check that the sequence of headers specified by the Importer is the same as the order
    // of headers as in the headers array.
    $not_in_order = [];

    foreach ($expected_headers as $index => $header) {
      if (isset($headers[$index]) && $headers[$index] != $header) {
        // Check that in this index in the expected headers, the header name is the same as the name
        // in the headers array in the same position or index.

        // Save only the header that does not match.
        array_push($not_in_order, $header);
      }
    }

    if ($not_in_order) {
      $not_in_order = implode(', ', $not_in_order);

      return [
        'case' => 'Headers not in the correct order',
        'valid' => FALSE,
        'failedItems' => ['headers' => $not_in_order]
      ];
    }

    // Validator response values if headers array is valid.
    return [
      'case' => 'Headers exist and match expected headers',
      'valid' => TRUE,
      'failedItems' => []
    ];
  }
}
