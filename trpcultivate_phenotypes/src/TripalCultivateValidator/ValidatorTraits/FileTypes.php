<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting a file extension and file media type (MIME) 
 * supported by the importer and a getter to retrieve the set value.
 */
trait FileTypes {
  /**
   * The key used to reference the file types set or get from the
   * context property in the parent class.
   * 
   * @var string
   */
  private string $trait_key = 'file_types';

  /**
   * Sets a file type/file media type.
   *
   * @param array $types
   *   An array of file extensions the importer expect of the data file.
   * 
   * @throws \Exception
   *   types array is an empty array or string.
   * 
   * @return void
   */
  public function setFileTypes(array $types) {
    // Types must have a value.
    if (empty($types)) {
      throw new \Exception('No file type provided.');
    }
    
    // File extension and file media type (mime type).
    $file_type_to_mime = [
      'tsv'  => 'text/tab-separated-values',
      'csv'  => 'text/csv',
      'txt'  => 'text/plain',

      // Other file types here, ensure file types
      // support delimiter.
    ];

    // Resolve the types to the correct mime type.
    $file_types = [];
    $unresolved = [];

    foreach($types as $type) {
      if (!isset($file_type_to_mime[ $type ])) {
        array_push($unresolved, $type);
        continue;
      }
      
      $file_types[ $type ] = $file_type_to_mime[ $type ];
    }
    
    // Types could not be resolve.
    if ($unresolved) {
      $unresolved = implode(', ', $unresolved);
      throw new \Exception('Could not resolve file media type (MIME type) of the following file extensions: ' . $unresolved);
    }

    $this->context[ $this->trait_key ] = $file_types; 
  }

  /**
   * Returns the file types set.
   *
   * @return array
   *   The file types set that include the file extension (type) and the corresponding 
   *   file media type (mime), keyed by file type.
   * 
   * @throws \Exception
   *   If the 'file_type' key does not exist in the context array (ie. the file_type
   *   array has NOT been set).
   */
  public function getFileTypes() {
    // The trait key element file_type should exists in the context property.
    if (!array_key_exists($this->trait_key, $this->context)) {
      throw new \Exception("Cannot retrieve file types set by the importer.");
    }

    return $this->context[ $this->trait_key ];
  }
}
