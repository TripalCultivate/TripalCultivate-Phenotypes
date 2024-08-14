<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting a delimiter used by the importer
 * to delimit data/values in the data file and getter to retrieve the set value.
 */
trait DataFileDelimiter {
  /**
   * The key used to reference the delimiter set or get from the
   * context property in the parent class.
   *
   * @var string
   */
  private string $trait_key = 'delimiter';
  
  /**
   * Sets the delimiter.
   *
   * @param string $delimiter
   *   A value used to separate/delimit the values in a data file row or line. 
   * 
   * @throws \Exception
   *   Invalid delimiter provided such as empty string, false and 0 values.
   * 
   * @return void
   */
  public function setDelimiter(string $delimiter) {
    // Delimiter must have a value.
    if (empty($delimiter)) {
      throw new \Exception('Invalid delimiter: Cannot use ' . $delimiter . ' as data file delimiter.');
    }
    
    $this->context[ $this->trait_key ] = $delimiter;
  }

  /**
   * Returns the delimiter set by the importer.
   *
   * @return string
   *   The delimiter value set by the importer.
   * 
   * @throws \Exception
   *   If the 'delimiter' key does not exist in the context array (ie. the delimiter
   *   array has NOT been set).
   */
  public function getDelimiter() {
    // The trait key element delimiter should exists in the context property.
    if (!array_key_exists($this->trait_key, $this->context)) {
      throw new \Exception("Cannot retrieve delimiter set by the importer.");
    }

    return $this->context[ $this->trait_key ];
  }
}
