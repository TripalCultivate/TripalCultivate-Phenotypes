<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting a delimiter used by the importer
 * to delimit data/values in the data file, and getter to retrieve the set value.
 */
trait DataFileDelimiter {

  /**
   * The key used by the setter method to create a delimiter element
   * in the context array, as well as the key used by the getter method
   * to reference and retrieve the delimiter element value.
   *
   * @var string
   */
  private string $context_key = 'delimiter';

  /**
   * Sets the data file delimiter.
   *
   * @param string $delimiter
   *   A character used to separate/delimit the values in a data file row or line.
   *
   * @return void
   *
   * @throws \Exception
   *  - An empty string value.
   */
  public function setDataFileDelimiter(string $delimiter) {

    // Delimiter character must not be an empty string.
    if($delimiter === '') {
      throw new \Exception('The DataFileDelimiter Trait requires a non-empty string as a data file delimiter.');
    }

    // Create an element in the context array keyed by the trait key
    // property, and set the value to the delimiter character provided.
    $this->context[ $this->context_key ] = $delimiter;
  }

  /**
   * Gets the data file delimiter.
   *
   * @return string
   *   The delimiter character set by the setter method.
   *
   * @throws \Exception
   *  - If the 'delimiter' key does not exist in the context array
   *    (ie. the delimiter element has NOT been set).
   */
  public function getDataFileDelimiter() {

    if (array_key_exists($this->context_key, $this->context)) {
      return $this->context[ $this->context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve delimiter from the context array as one has not been set by setDataFileDelimiter() method.');
    }
  }
}
