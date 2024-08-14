<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting the metadata column headers used by the importer
 * to list the required headers in the data file and getter to retrieve the set value.
 */
trait HeaderMetadata {
  /**
   * The key used to reference the delimiter set or get from the
   * context property in the parent class.
   *
   * @var string
   */ 
  private string $trait_key = 'header';

  /**
   * The header type metadata key to differentiate
   * this set of header from other types ie. phenotypes headers.
   * 
   * @var string
   */
  private string $type_key = 'metadata';
  
  /**
   * Sets the header.
   *
   * @param array $headers
   *   The associative array the importer defines that contains the header name as the key
   *   and the header description text as the value. This list of headers will be 
   *   the required headers the importer expects in the data file (header row).
   *   
   *   see: $header property defined by the importer instance. 
   * 
   * @throws \Exception
   *   An empty array and an non-string header value.
   * 
   * @return void
   */
  public function setHeaderMetadata(array $headers) {
    // Header must have a value.
    if (empty($headers)) {
      throw new \Exception('The headers provided does not contain key-value pair values.');
    }
    
    $keys = array_keys($headers);
    $headers = array_map('trim', $keys);
    
    // Make sure tha no numeric value as header.
    $invalid_header = [];

    foreach($headers as $header) {
      if (is_numeric($header)) {
        array_push($invalid_header, $header);
      }
    }

    if ($invalid_header) {
      $str_invalid_header = implode(', ', $invalid_header);
      throw new \Exception('The headers provided contain integer data type value as header: ' . $str_invalid_header);
    }
    
    $this->context[ $this->trait_key ][ $this->type_key ] = $headers;
  }

  /**
   * Returns the headers.
   *
   * @return array
   *   All the headers excluding the text description.
   * 
   * @throws \Exception
   *   If the 'header/metadata' key does not exist in the context array (ie. the header/metadata
   *   array has NOT been set).
   */
  public function getHeaderMetadata() {
    // The trait key element header should exists in the context property.
    if (!isset($this->context[ $this->trait_key ][ $this->type_key ])) {
      throw new \Exception('Cannot retrieve header metadata set by the importer.');
    }

    return $this->context[ $this->trait_key ][ $this->type_key ];
  }
}
