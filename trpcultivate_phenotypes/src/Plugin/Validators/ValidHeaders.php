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

    // Reference the list of expected headers.
    $expected_headers = $this->getHeaders();
    
    
    // @TODO: needs a check if headers array has less or more than the expected headers? 

    
    // Array to store missing headers.
    $missing_headers = []; 
    // Array to store headers in the wrong order.
    $wrong_order_headers = [];

    foreach ($expected_headers as $index => $header) {
      // Each header name in the expected headers array will be verified for both 
      // index order and presence in the headers provided.

      if (!in_array($header, $headers)) {
        // Missing header.
        array_push($missing_headers, $header);
      }
      else {
        if (isset($headers[ $index ]) && $headers[ $index ] != $header) {
          // Header in the wrong order.
          array_push($wrong_order_headers, $header);
        }
      }
    }
    
    // The headers array contains both missing and wrong order headers.
    if ($missing_headers && $wrong_order_headers) {
      return [
        'case' => 'Missing expected headers and headers not in the correct order',
        'valid' => FALSE,
        'failedItems' => [
          'missing' => $missing_headers,
          'wrong_order' => $wrong_order_headers
        ]
      ];
    }

    // The headers array contains missing headers only.
    if ($missing_headers) {
      return [
        'case' => 'Missing expected headers',
        'valid' => FALSE,
        'failedItems' => [
          'missing' => $missing_headers
        ]
      ];
    }

    // The headers array contains only headers in the wrong order.
    if ($wrong_order_headers) {
      return [
        'case' => 'Headers not in the correct order',
        'valid' => FALSE,
        'failedItems' => [
          'wrong_order' => $wrong_order_headers
        ]
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
