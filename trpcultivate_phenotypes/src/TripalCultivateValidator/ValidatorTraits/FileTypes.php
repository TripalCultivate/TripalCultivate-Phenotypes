<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting a file types and file media type (MIME) 
 * supported by the importer, and getter to retrieve the set value.
 */
trait FileTypes {
  /**
   * The key used by the setter method to create a file types element 
   * in the context array, as well as the key used by the getter method 
   * to reference and retrieve the file types element value.
   * 
   * @var string
   */
  private string $trait_key = 'file_types';

  /**
   * Sets a file types with the file media type.
   *
   * @param array $extensions
   *   An array of file extensions (file types) the importer expects the file extension of the data file.
   * 
   * @return void
   * 
   * @throws \Exception
   *  - Types array is an empty array.
   *  - Unsupported extension (cannot resolve mime type using the type-mime mapping array).
   */
  public function setFileTypes(array $extensions) {
    
    // Extensions array must have a element.
    if (empty($extensions)) {
      throw new \Exception('The File Types Trait requires an array of file extensions and must not be empty.');
    }
    
    // File extension and file media type (mime type).
    $file_type_to_mime = [
      'tsv'  => 'text/tab-separated-values',
      'csv'  => 'text/csv',
      'txt'  => 'text/plain',

      // Add any additional valid file types here, 
      // while ensuring file types support use of a delimiter.
    ];

    // Resolve the types to the correct mime type.
    $file_types = [];
    $unresolved = [];

    foreach($extensions as $type) {
      if (!isset($file_type_to_mime[ $type ])) {
        array_push($unresolved, $type);
        continue;
      }
      
      $file_types[ $type ] = $file_type_to_mime[ $type ];
    }
    
    // Types could not be resolve.
    if ($unresolved) {
      $unresolved = implode(', ', $unresolved);
      throw new \Exception('The File Types Trait could not to resolve the mime type of the extensions: ' . $unresolved);
    }

    $this->context[ $this->trait_key ] = $file_types;
  }

  /**
   * Gets the file types.
   *
   * @return array
   *   The file types set by the setter method. The types include the file extension (type) and 
   *   the corresponding file media type (mime), keyed by file extension.
   * 
   * @throws \Exception
   *  - If the 'file_types' key does not exist in the context array
   *    (ie. the file_types array has NOT been set).
   */
  public function getFileTypes() {

    if (array_key_exists($this->trait_key, $this->context)) {
      return $this->context[ $this->trait_key ];
    }
    else {
      throw new \Exception('Cannot retrieve file types from the context array as one has not been set by setFileTypes() method.');
    }
  }
}