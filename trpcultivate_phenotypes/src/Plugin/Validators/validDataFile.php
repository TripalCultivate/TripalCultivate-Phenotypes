<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Validate that project exits.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_valid_data_file",
 *   validator_name = @Translation("Valid Data File Validator"),
 *   input_types = {"file"}
 * )
 */
class validDataFile extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
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
   * Perform basic file-level validation of data file.
   * Checks include: 
   *   - Has Drupal File Id number assigned and can be loaded.
   *   - Valid file extension and file mime type configured by the file importer instance.
   *   - File exists and not empty.
   * 
   * @param string $filename
   *   The location (absolute path) of a file within the file system. 
   * @param integer $fid
   *   The unique identifier (fid) of a file managed by Drupal File System.    
   * 
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the file is valid or not.
   *     - failedItems: the failed filename/file id value provided. This will be empty if the file was valid.
   */
  public function validateFile($filename, $fid = NULL) {
    // Parameter check, verify that the file path is valid.
    if ($filename && !file_exists($filename)) {
      throw new \Exception(t('File path provided is not a valid path.'));
    }
    
    // Check the file id number is not 0 or negative values.
    if (!is_null($fid) && $fid <= 0) {
      throw new \Exception(t('Drupal File System Id Number cannot be 0 or negative values.'));
    }    

    // Validator response values for a valid file.
    $case = 'Data file is valid';
    $valid = TRUE;
    $failed_items = '';
    
    // File.
    $file = (is_null($fid)) ? $filename : $fid;
    // Load file object.
    $file_object = FILE::load($file);
    
    if (!$file_object) {
      // Cannot load file object.
      $case = 'Filename or file id failed to load a file object';
      $valid = FALSE;
      $failed_items = $file;
    }

    // Check that the file uri points to a file that exists in the file system.
    // Check that the extension and mime match the configured extensions in the importer definition.
    // File is not empty.

    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}