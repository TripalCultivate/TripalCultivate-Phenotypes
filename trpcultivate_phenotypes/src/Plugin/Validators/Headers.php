<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Validate Required Headers.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_headers",
 *   validator_name = @Translation("Headers Validator"),
 *   validator_scope = "HEADERS",
 * )
 */
class Headers extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * File System Service.
   */
  protected $service_file_system;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // DI Drupal file system service.
    $this->service_file_system = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system')
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
   */
  public function validate() {
    // Validate ...
    $validator_status = [
      'title' => 'File has All the Column Headers Expected',
      'status' => 'pass',
      'details' => ''
    ];

    // Instructed to skip this validation. This will set this validator as upcoming or todo.
    // This happens when other prior validation failed and this validation could only proceed
    // when input values in the failed validator have been rectified.
    if ($this->skip) {
      $validator_status['status'] = 'todo';
      return $validator_status;
    }

    // Headers:
    //   - Header line is not empty.
    //   - No missing headers.

    $file_column_headers = [];

    // Open file and read the first line, the column headers line.
    $file = File::load($this->file_id);
    if ($file) {
      $uri = $file->getFileUri();
      $file_path = $this->service_file_system->realPath($uri);

      if ($file_path) {
        $file_handle = fopen($file_path, 'r');
        if ($file_handle) {
          // Get the first line - column headers only.
          $file_column_headers = fgets($file_handle);
          fclose($file_handle);

          $file_column_headers = str_getcsv($file_column_headers, "\t");
        }
      }
    }

    // First remove any empty array elements.
    $file_column_headers = array_filter($file_column_headers);

    // Then check if there are any.
    if (empty($file_column_headers)) {
      // No file has been uploaded into the data file field.
      $validator_status['status']  = 'fail';
      $validator_status['details'] = 'No column headers found in the file. Please upload a file and try again.';
    }
    else {
      // This last check ensures that expected headers defined by the importer are
      // present in the data file for both share and collect importer. Collect importer
      // may have more headers but the headers defined in the importer are assumed to
      // be required headers and must exist in the data file.

      $missing_headers = array_filter($this->column_headers, function($h) use($file_column_headers) {
        return (!in_array($h, $file_column_headers));
      });

      if (count($missing_headers) > 0) {
        // List the columns missing in relation to the required headers.
        $list_missing_headers = implode(', ', $missing_headers);
        $validator_status['status']  = 'fail';
        $validator_status['details'] = 'Columns headers: ' . $list_missing_headers . ' is/are missing in the file. Please upload a file and try again.';
      }
    }

    return $validator_status;
  }
}
