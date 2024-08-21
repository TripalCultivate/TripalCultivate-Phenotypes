<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused on configuring a validator to look at particular
 * column within a row of a provided file
 */
trait ColumnIndices {

  /**
   * Sets the array of indices for which the validator to do its checks on.
   *
   * @param array $indices
   *   An array where each value is the key of the column the validator instance should
   *   act on. It must be either an integer or string. For more detail, see the
   *   definition of indices in the docblock above.
   * @return void
   *
   * @throws \Exception
   *  - If $indices array is empty
   *  - If $indices array is multi-dimensional or contains objects
   */
  public function setIndices(array $indices) {

    // Make sure we don't have an empty array
    if(count($indices) === 0) {
      throw new \Exception('The ColumnIndices Trait requires a non-empty array of indices.');
    }

    // Check if we have a multidimentsional array or array of objects
    foreach ($indices as $index) {
      if (is_array($index) || is_object($index)) {
        throw new \Exception('The ColumnIndices Trait requires a one-dimensional array only.');
      }
    }

    // Set the indices array
    $this->context['indices'] = $indices;
  }

  /**
   * Returns an array of integers or keys which correspond to columns in a row
   * of delimited values that the validator instance should act on.
   *
   * @return array
   *   A one-dimensional array containing column indices. If the array to be validated
   *   is a list then these will be sequential integers and if it's an associative
   *   array they will be the string keys set by the developer.
   *
   * @throws \Exception
   *  - If the 'indices' key does not exist in the context array (ie. the indices
   *    array has NOT been set)
   */
  public function getIndices() {

    if (array_key_exists('indices', $this->context)) {
      return $this->context['indices'];
    }
    else {
      throw new \Exception("Cannot retrieve an array of indices as one has not been set by the setIndices() method.");
    }
  }
}
