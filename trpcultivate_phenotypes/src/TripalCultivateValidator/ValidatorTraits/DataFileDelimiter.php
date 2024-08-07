<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting a delimiter used by the importer
 * to delimit data/values in the data file.
 */
trait DataFileDelimiter {

  /**
   * Sets the delimiter.
   *
   * @param string $delimiter
   *   A value used to separate/delimit the values in a data file row or line. 
   * 
   * @return void
   */
  public function setDelimiter(string $delimiter) {
    // @TODO: verify that delimiter has a value.
    $delimiter = "\t";
    $this->context['delimiter'] = $delimiter;
  }

  /**
   * Returns the delimiter set by the importer.
   *
   * @return string
   *   The delimiter value set by the importer.
   * 
   * @throws \Exception
   *  - If the 'delimiter' key does not exist in the context array (ie. the delimiter
   *    array has NOT been set).
   */
  public function getDelimiter() {

    if (array_key_exists('delimiter', $this->context)) {
      return $this->context['delimiter'];
    }
    else {
      throw new \Exception("Cannot retrieve delimiter set by the importer.");
    }
  }
}
