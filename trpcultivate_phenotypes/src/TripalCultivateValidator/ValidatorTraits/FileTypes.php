<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting supported mime-types by an importer and
 * the particular mime-type of the input file. Getters are provided to retrieve
 * mime-types, supported file extensions and delimiters.
 */
trait FileTypes {

  /**
   * A mapping of file extensions and their supported mime-types
   *
   * More specifically, based on the supported file extensions of the
   * current importer, a list of valid mime-types for the extension(s) is looked
   * up in this mapping.
   *
   * @var array
   */
  public static array $extension_to_mime_mapping = [
    'tsv'  => ['text/tab-separated-values'],
    'csv'  => ['text/csv'],
    'txt'  => ['text/plain'],
  ];

  /**
   * Sets the mime-type of the current input file as well as that mime-type's
   * supported file delimiters.
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
   *  - mime_type string is empty
   *  - Unsupported mime_type (cannot resolve mime type using the type-mime mapping array).
   */
  public function setFileMimeType(string $mime_type) {

    // Mime-type must not be an empty string.
    if (empty($mime_type)) {
      throw new \Exception("The setFileMimeType() setter requires a string of the input file's mime-type and must not be empty.");
    }

    // Check if mime-type is in our mapping array
    if (!isset(self::$mime_to_delimiter_mapping[ $mime_type ])) {
      // Since this is checking a user-provided value, the error is going to be
      // logged and then checked by a validator so that the error can be passed
      // to the user in a friendly way.
      $this->logger->error("The setFileMimeType() setter requires a supported mime-type but '$mime_type' is unsupported.");
    }
    else {
      // Set the mime-type
      $this->context['file_mime_type'] = $mime_type;
    }
  }

  /**
   * Sets the supported mime-types for an importer based on the supported file
   * extensions.
   *
   * @param array $extensions
   *   An array of file extensions that are supported by this importer
   *
   * @return void
   *
   * @throws \Exception
   *  - extensions array is an empty array.
   *  - if a file extension is not in the $extension_to_mime_mapping array
   */
  public function setSupportedMimeTypes(array $extensions) {

    // Extensions array must have a element.
    if (empty($extensions)) {
      throw new \Exception("The setSupportedMimeTypes() setter requires an array of file extensions that are supported by the importer and must not be empty.");
    }

    $mime_types = [];
    $invalid_ext = [];

    foreach($extensions as $ext) {
      if (!isset(self::$extension_to_mime_mapping[ $ext ])) {
        array_push($invalid_ext, $ext);
        continue;
      }
      $mime_types = array_merge($mime_types, self::$extension_to_mime_mapping[$ext]);
    }

    if ($invalid_ext) {
      $invalid_ext = implode(', ', $invalid_ext);
      throw new \Exception('The setSupportedMimeTypes() setter does not recognize the following extensions: ' . $invalid_ext);
    }

    // Set the mime-types
    $this->context['supported_mime_types'] = $mime_types;

    // Set the supported file extensions
    $this->context['file_extensions'] = $extensions;
  }

  /**
   * Gets the supported file extensions of the current importer.
   *
   * @return array
   *   The file extensions set by the setSupportedMimeTypes() setter method.
   *
   * @throws \Exception
   *  - If the 'file_extensions' key does not exist in the context array
   *    (ie. the setSupportedMimeTypes() method has NOT been called).
   */
  public function getSupportedFileExtensions() {

    $context_key = 'file_extensions';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve supported file extensions as they have not been set by setSupportedMimeTypes() method.');
    }
  }

  /**
   * Gets the supported file mime-types of the current importer.
   *
   * @return array
   *   The file mime-types set by the setSupportedMimeTypes() setter method.
   *
   * @throws \Exception
   *  - If the 'supported_mime_types' key does not exist in the context array
   *    (ie. the setSupportedMimeTypes() method has NOT been called).
   */
  public function getSupportedMimeTypes() {

    $context_key = 'supported_mime_types';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve supported file mime-types as they have not been set by setSupportedMimeTypes() method.');
    }
  }

  /**
   * Gets the file mime-type of the input file.
   *
   * @return string
   *   The file mime-type set by the setFileMimeType() setter method.
   *
   * @throws \Exception
   *  - If the 'file_mime_type' key does not exist in the context array
   *    (ie. the setFileMimeType() method has NOT been called).
   */
  public function getFileMimeType() {

    $context_key = 'file_mime_type';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve the input file mime-type as it has not been set by setFileMimeType() method.');
    }
  }
}
