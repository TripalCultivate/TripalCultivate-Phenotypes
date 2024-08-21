<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting headers used by the importer, 
 * and getter to retrieve the set value.
 */
trait Headers {
  /**
   * The key used by the setter method to create a headers element 
   * in the context array, as well as the key used by the getter method 
   * to reference and retrieve the headers element value.
   *
   * @var string
   */ 
  private string $trait_key = 'headers';

  /**
   * Sets the headers.
   * 
   * @param $headers
   *   The key-value pair array of headers defined by the importer.
   *   Each item consists of a header name, text description and type (required, optional)
   *   keyed by name, description and type, respectively.
   * 
   *   This list of headers is expected to appear in the data file header row.
   *   NOTE: the set headers array is zero-based index and represents the order
   *         of the header as each appears in the headers definition in the importer.
   * 
   * @throws \Exception
   *  - An empty array header.
   * 
   * @return void
   */
  public function setHeaders(array $headers) {

    // Headers must have a value and at least a required header.
    if (empty($headers)) {
      throw new \Exception('The Headers Trait requires an array of headers and must not be empty.');  
    }

    // Expected types of the headers, each type will have a getter method.
    // There must be headers of type required in the headers. Additional type
    // is created keyed by: all - which will reference all the headers.
    
    // NOTE: all - is not a type value used to set the type key of a header.
    $types = ['required', 'optional'];

    $context_headers = [];
    
    // For each type, pull the headers using the type key for comparison.
    foreach($types as $type) {
      // Pull the subset of headers that the type matches the type being processed
      // and check the result if type is required.
      $headers_of_type = array_filter($headers, function($headers) use($type) { 
        return $headers['type'] == $type; 
      });

      // Required array check.
      if ($type == 'required' && empty($headers_of_type)) {
        // Throws an exception.
        throw new \Exception('The Headers Trait requires an array of headers of type required.');  
      }

      // Simplify the headers of a type to just the index and header name.
      foreach($headers_of_type as $key => $header) {
        $context_headers[ $key ] = $header['name'];
      }
      
      $this->context[ $this->trait_key ][ $type ] = $context_headers;
      unset($context_headers);
    }

    // Create an element in the context array that will reference
    // all the headers - keys (order) and header name. Key: all.
    $this->context[ $this->trait_key ]['all'] = array_column($headers, 'name');
  }
  
  /**
   * Get required headers.
   * 
   * @return array
   *   All headers of type required, keyed by the index (order) from
   *   the headers array and header name as the value.
   * 
   *   The key required in the context headers array set by the setter method.
   *   NOTE: the headers array is zero-based index.
   * 
   * @throws \Exception
   *  - If the 'headers' key does not exists in the context array
   *    (ie. the headers element has NOT been set).
   */
  public function getRequiredHeaders() {
    
    $type_key = 'required';

    if (array_key_exists($this->trait_key, $this->context)) {
      return $this->context[ $this->trait_key ][ $type_key ];
    }
    else {
      throw new \Exception('Cannot retrieve ' . $type_key . ' headers from the context array as one has not been set by setHeaders() method.');
    }
  }
  
  /**
   * Get optional headers.
   * 
   * @return array
   *   All headers of type optional, keyed by the index (order) from
   *   the headers array and header name as the value.
   * 
   *   The key optional in the context headers array set by the setter method.
   *   NOTE: the headers array is zero-based index.
   * 
   * @throws \Exception
   *  - If the 'headers' key does not exists in the context array
   *    (ie. the headers element has NOT been set).
   */
  public function getOptionalHeaders() {
    
    $type_key = 'optional';

    if (array_key_exists($this->trait_key, $this->context)) {
      return $this->context[ $this->trait_key ][ $type_key ];
    }
    else {
      throw new \Exception('Cannot retrieve ' . $type_key . ' headers from the context array as one has not been set by setHeaders() method.');
    }
  }
  
  /**
   * Get all headers.
   * 
   * @return array
   *   All headers of regardless of type, keyed by the index (order) from
   *   the headers array and header name as the value.
   * 
   *   The key all in the context headers array set by the setter method.
   *   NOTE: the headers array is zero-based index.
   * 
   * @throws \Exception
   *  - If the 'headers' key does not exists in the context array
   *    (ie. the headers element has NOT been set).
   */
  public function getAllHeaders() {
    
    $type_key = 'all';

    if (array_key_exists($this->trait_key, $this->context)) {
      return $this->context[ $this->trait_key ][ $type_key ];
    }
    else {
      throw new \Exception('Cannot retrieve ' . $type_key . ' headers from the context array as one has not been set by setHeaders() method.');
    }
  }
}
