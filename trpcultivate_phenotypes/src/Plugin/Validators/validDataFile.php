<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\FileTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;

/**
 * Validate data file.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "valid_data_file",
 *   validator_name = @Translation("Valid Data File Validator"),
 *   input_types = {"file"}
 * )
 */
class ValidDataFile extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  
  /**
   * This validator requires the following validator traits:
   * - FileTypes: Gets an array of all supported MIME types the importer is configured to process.
   */
  use FileTypes;
  
  /**
   * Entity Type Manager service.
   * 
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $service_EntityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  
    // Set the Entity type manager service.
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
   * Perform validation of data file.
   * Checks include:
   *  - Parameter filename or file id is valid.
   *  - Has Drupal File Id number assigned and can be loaded.
   *  - Valid file extension and file mime type configured by the file importer instance.
   *  - File exists and not empty.
   *  - File can be opened.
   * 
   * @param string $filename
   *   The location of a file within the file system. 
   * @param integer $fid
   *   The unique identifier (fid) of a file that is managed by Drupal File System.    
   * 
   * @return array
   *   An associative array with the following keys.
   *    - case: a developer focused string describing the case checked.
   *    - valid: either TRUE or FALSE depending on if the file is valid or not.
   *    - failedItems: an array of "items" that failed to be used in the message to the user. This is an empty array if the file input was valid.
   */
  public function validateFile($filename, $fid = NULL) {
    // Validator response values for a valid file.
    $case = 'Data file is valid';
    $valid = TRUE;
    $failed_items = [];

    // Parameter check, verify that the filename/file path is valid.
    if (empty($filename) && is_null($fid)) {
      return [
        'case' => 'Filename is empty',
        'valid' => FALSE,
        'failedItems' => ['filename' => $filename]
      ];
    }
    
    // Parameter check, verify the file id number is not 0 or negative values.
    if (!is_null($fid) && $fid <= 0) {
      return [
        'case' => 'Invalid file id number',
        'valid' => FALSE,
        'failedItems' => ['file_id' => $fid]
      ];
    } 

    // File input.
    $file_input = (is_null($fid)) ? $filename : $fid;
    
    // Load file object.
    if (is_numeric($file_input)) {
      // The file input is integer value, the file id number.
      // Load the file object by fid number.
      $file_id = $fid;
      $file_object = File::load($file_id);

      $input_key = 'file_id';
    }
    else {
      // The file input is a string value, a path to the file.
      // Locate the file entity by uri and load the file object using
      // the returned file id number that matched.
      $file_entities = $this->service_EntityTypeManager
        ->getStorage('file')
        ->loadByProperties(['uri' => $filename]);
      
      $file_entity = reset($file_entities);
      $file_object = 0;
      
      if ($file_entity) {
        $file_id = $file_entity->get('fid')->value;
        $file_object = File::load($file_id);
      }

      $input_key = 'filename';
    }
    

    // Validate data file:

    if ($file_object) {
      // File object has loaded successfully.
      
      // Check that the file is not blank by inspecting the file size
      // to see if it is greater than 0.
      $file_size = $file_object->getSize();
      if ($file_size > 0) {
      
        // Check that both the file extension and file MIME type
        // are supported by the importer.
        $file_mime_type = $file_object->getMimeType();
        $file_filename  = $file_object->getFileName();
        $file_extension = pathinfo($file_filename, PATHINFO_EXTENSION);

        $supported_file_extensions = $this->getSupportedFileExtensions();
        $supported_mime_types = $this->getSupportedMimeTypes();
        if (in_array($file_mime_type, $supported_mime_types) && in_array($file_extension, $supported_file_extensions)) {
          
          // Check that the file can be opened.
          $file_uri  = $file_object->getFileUri();
          $file_handle = @fopen($file_uri, 'r');
          if (!$file_handle) {
            $case = 'The file cannot be opened';
            $valid = FALSE;
            $failed_items = [$input_key => $file_input];
          }

          fclose($file_handle);
        }
        else {
          // The file is not one of the MIME types supported by the importer.
          $case = 'The file is not the prescribed file type';
          $valid = FALSE;
          $failed_items = [$input_key => $file_input]; 
        }
      }
      else {
        // The file size is 0 or less indicating that it is an empty file.
        $case = 'The file has no data and is an empty file';
        $valid = FALSE;
        $failed_items = [$input_key => $file_input]; 
      }
    }
    else {
      // The file failed to load a file object.
      $case = 'Filename or file id failed to load a file object';
      $valid = FALSE;
      $failed_items = [$input_key => $file_input]; 
    }

    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}