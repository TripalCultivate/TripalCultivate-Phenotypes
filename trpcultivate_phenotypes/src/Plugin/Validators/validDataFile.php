<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * File system service.
   */
  protected $service_EntityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager) {
    
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  
    // Entity type manager service.
    $this->service_EntityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
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
    // Parameter check, verify that the file path is valid and exits in the file system (no directories).
    if ($filename && !is_file($filename)) {
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
    if (is_numeric($file)) {
      // Load file object by file id number.
      $file_object = File::load($fid);
    }
    else {
      // Find the file entity by uri and load file object by using the resulting
      // file id number that matched.
      $file_ids = $this->service_EntityTypeManager
        ->getStorage('file')
        ->loadByProperties(['uri' => $file]);
      
      $file_id = reset($file_ids);

      $file_object = 0;
      if (!empty($file_id)) {
        $file_object = File::load($file_id->get('fid')->value);
      }
    }
    
    if (!$file_object) {
      // Cannot load file object.
      $case = 'Filename or file id failed to load a file object';
      $valid = FALSE;
      $failed_items = $file;
    }
    else {
      // Check if the file uri exits and is readable.
      $file_uri = $file_object->getFileUri();

      // Check that the file is not empty by inspecting the file size.
      if (filesize($file_uri) > 0) {
        // Check that the file is readable and can be opened.
        if (is_readable($file_uri)) {
          // Check that the file type matches the file types the importer is 
          // configured to accept.
          $file_mime = $file_object->getMimeType();

          // @TODO: Fetch the file_types plugin annotation value of the importer.
          // NOTE: the importer uses file extension - create a helper method that will
          // resolve an extension to mime type. ie. txt - text/plain.
          $importer_file_types = ['text/tab-separated-values', 'text/plain'];
          
          if (!in_array($file_mime, ['text/tab-separated-values', 'text/plain'])) {
            $case = 'The file uploaded is not prescribed file type';
            $valid = FALSE;
            $failed_items = $file;
          }
        }
        else {
          // Cannot read or open file.
          $case = 'The file uploaded cannot be opened';
          $valid = FALSE;
          $failed_items = $file;
        }

        clearstatcache();
      }
      else {
        // The file has no contents and is empty.
        $case = 'The file uploaded has no data and is an empty file';
        $valid = FALSE;
        $failed_items = $file;
      }
    }
    
    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}