<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting a file types and file media type (MIME)
 * supported by the importer, and getter to retrieve the set value.
 */
trait FileTypes {

  /**
   * A mapping of supported file extensions and their supported mime-types
   *
   * More specifically, based on the file extension of an input file, a list
   * of valid mime-types for that extension is looked up in this mapping. In this
   * way, we can support multiple different mime-types for a single file
   * extension and get closer to the
   *
   * @var array
   */
  /*
  public static array $extension_to_mime_mapping = [
    'tsv'  => ['text/tab-separated-values'],
    'csv'  => ['text/csv'],
    'txt'  => ['text/plain'],
  ];
  */

  /**
   * A mapping of supported file mime-types and their supported delimiters.
   *
   * More specifically, the file is split based on the appropriate delimiter
   * for the mime-type passed in. For example, the mime-type
   * "text/tab-separated-values" maps to the tab (i.e. "\t") delimiter.
   *
   * By using this mapping approach we can actually support a number of different
   * file types with different delimiters for the same importer while keeping
   * the performance hit to a minimum. Especially as in many cases, this is a
   * one-to-one mapping.
   *
   * @var array
   */
  public static array $mime_to_delimiter_mapping = [
    'text/tab-separated-values' => ["\t"],
    'text/csv' => [','],
    'text/plain' => ["\t", ','],
  ];

  /**
   * Sets a file types with the file media type.
   *
   * @param string $mime_type
   *   A string that is the mime-type of the input file
   *
   *   HINT: You can get the mime-type of a file from the 'mime-type' property
   *   of a file object
   *
   * @return void
   *
   * @throws \Exception
   *  - Types array is an empty array.
   *  - Unsupported extension (cannot resolve mime type using the type-mime mapping array).
   */
  public function setFileTypes(array $mime_type) {

    // Extensions array must have a element.
    if (empty($mime_type)) {
      throw new \Exception('The FileTypes Trait requires a string of the input file\'s mime-type and must not be empty.');
    }

    if (!isset($this->$mime_to_delimiter_mapping[ $mime_type ])) {
      throw new \Exception('The FileTypes Trait requires a supported mime-type but ' . $mime_type . ' is unsupported.');
    }

    $file_delimiters = $this->$mime_to_delimiter_mapping[$mime_type];

    // Set the context array for file extensions
    //$this->context['file_extensions'] = $file_types;

    // Set the mime-types
    $this->context['mime_types'] = $mime_types;

    // Set the supported file delimiters
    $this->context['file_delimiter'] = $file_delimiters;
  }

  /**
   * Gets the supported file extensions.
   *
   * @return array
   *   The file extensions set by the setFileTypes() setter method.
   *
   * @throws \Exception
   *  - If the 'file_extensions' key does not exist in the context array
   *    (ie. the setFileTypes() method has NOT been called).
   */
  /*
  public function getSupportedFileExtensions() {

    $context_key = 'file_extensions';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve supported file extensions as they have not been set by setFileTypes() method.');
    }
  }
  */

  /**
   * Gets the supported file mime-types.
   *
   * @return array
   *   The file mime-types set by the setFileTypes() setter method.
   *
   * @throws \Exception
   *  - If the 'mime_types' key does not exist in the context array
   *    (ie. the setFileTypes() method has NOT been called).
   */
  public function getSupportedMimeTypes() {

    $context_key = 'mime_types';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve supported file mime-types as they have not been set by setFileTypes() method.');
    }
  }

  /**
   * Gets the list of delimiters supported by the file mime-type that
   * was provided to the setter
   *
   * NOTE: This method is static to allow for it to also be used by the static
   * method splitRowIntoColumns()
   *
   * @return array
   *   The list of delimiters that are supported by the file mime-type
   *
   * @throws \Exception
   *   - If the 'delimiter' key does not exist in the context array (ie. the
   *     setFileTypes() method has NOT been called)
   */
  public static function getDelimitersForMimeType() {

    $context_key = 'file_delimiter';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve supported file delimiters as they have not been set by setFileTypes() method.');
    }
  }
}
