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
   * to reference and retrieve the headers element values. 
   * 
   * Each value is keyed by the types defined by type property.
   *
   * @var string
   */ 
  private string $context_key = 'headers';
  
  /**
   * Expected types of the headers, each type will have a getter method.
   * A general-purpose getter getHeaders() can be used to get headers of 
   * a specific one header type or combination of header types.
   * 
   * @var array
   */
  private array $types = [
    'required', // Header must have a value.
    'optional', // Header may contain a value.
  ];

  /**
   * Sets the headers.
   * 
   * @param $headers
   *   A list of header definitions defined by the importer (usually in a protected
   *   variable at the top of the importer class). Each item in this list is an associative
   *   array defining the specific header and the headers must be listed in order!
   *   Each header item must consist of a header name (key: 'name'), and type (key: 'type',
   *   supported values: 'required', 'optional').
   * 
   *   This list of headers is expected to appear in the data file header row.
   *   
   *   NOTE: the set headers array is zero-based index and represents the order
   *         of the header as each item appears in the headers array parameter
   *         and is unaltered by setters and getters.
   * 
   * @throws \Exception
   *  - An empty array header.
   * 
   * @return void
   */
  public function setHeaders(array $headers) {

    // Headers must have a value.
    if (empty($headers)) {
      throw new \Exception('The Headers Trait requires an array of headers and must not be empty.');  
    }
    
    // Required keys that each header element must possess and 
    // cannot be set to an empty value. The 'type' key's value must be
    // one of the 'type' values defined by the types property. 
    $required_header_keys = [
      'name', // Name of the header.
      'type'  // Type of the header (ie. required or optional).
    ];
    
    // For each header, check that the required keys exist and contain a value.
    $context_headers = [];

    foreach($headers as $index => $header) {
      // Header element key and value check.
      foreach($required_header_keys as $key) {
        // Key is not set.
        if (!isset($header[ $key ])) {
          throw new \Exception('Headers Trait requires the header key: ' . $key . ' when defining headers.');
        }
        
        // Key is set but value is empty.
        if (empty(trim($header[ $key ]))) {
          throw new \Exception('Headers Trait requires the header key: ' . $key . ' to be have a value.');
        }

        // Type value is one of valid types.
        if ($key == 'type' && !in_array($header[ $key ], $this->types)) {
          $str_types = implode(', ', $this->types);
          throw new \Exception('Headers Trait requires the header key: ' . $key . ' value to be one of [' . $str_types . '].');
        }
      }

      // With the header type already verified to be one of the valid types, 
      // push the header into the designated type temporary context array.
      $context_headers[ $header['type'] ][ $index ] = $header['name'];
    }
    
    // Set each header type context array.
    foreach($this->types as $type) {  
      $this->context[ $this->context_key ][ $type ] = $context_headers[ $type ] ?? [];
    }
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

    if (array_key_exists($this->context_key, $this->context)) {
      return $this->context[ $this->context_key ][ $type_key ];
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

    if (array_key_exists($this->context_key, $this->context)) {
      return $this->context[ $this->context_key ][ $type_key ];
    }
    else {
      throw new \Exception('Cannot retrieve ' . $type_key . ' headers from the context array as one has not been set by setHeaders() method.');
    }
  }

  /**
   * Get headers of type(s).
   * 
   * @param array $types
   *   A list of header types to get. 
   *   Default to required and optional header types.
   * 
   * @return array
   *   A single list of headers matching a type defined by the types parameter.
   *   For example, if the types parameter includes required and optional the the 
   *   resulting array will container all headers in order of index assuming there
   *   are only required and optional types supported.
   *
   *   The returned array is keyed by the index (column order) from
   *   the headers array and header name as the value.
   *   NOTE: the headers array is zero-based index.
   * 
   * @throws \Exception
   *  - If the 'headers' key does not exists in the context array
   *    (ie. the headers element has NOT been set).
   *  - If an unrecognized header type is requested in the types parameter.
   */
  public function getHeaders(array $types = ['required', 'optional']) {

    $valid_types = $this->types;
    
    // Pull out any unrecognized types by compairing the types paramter
    // to the types property listing valid types.
    $invalid_types = array_filter($types, function($type) use($valid_types) { 
      return !in_array($type, $valid_types); 
    });
    
    // If any such unrecognized types are detected, throw an exception.
    if (!empty($invalid_types)) {
      $str_invalid_types = implode(', ', $invalid_types);
      $str_valid_types = implode(', ', $valid_types);
      throw new \Exception('Cannot retrieve invalid header types: ' . $str_invalid_types . '. Use one of valid types: [' . $str_valid_types . ']');
    }

    if (array_key_exists($this->context_key, $this->context)) {
      // At this point, types requested are valid.   
      $headers = [];

      foreach($types as $type) {
        foreach($this->context[ $this->context_key ][ $type ] as $index => $header) {
          $headers[ $index ] = $header;
        }
      }
      
      // All headers of type matching all types requested into 
      // one array key by the index and header name as the value.
      return $headers;
    }
    else {
      throw new \Exception('Cannot retrieve headers from the context array as one has not been set by setHeaders() method.');
    } 
  }
}
