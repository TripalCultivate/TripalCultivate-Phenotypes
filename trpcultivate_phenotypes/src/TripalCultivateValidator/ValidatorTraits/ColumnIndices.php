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
   *   An array (can be one- or multi-dimensional) of integers or keys which
   *   correspond to columns in a row of delimited values that the validator
   *   instance should act on
   * @return void
   *
   * @throws \Exception
   *  - If $indices array is empty
   */
  public function setIndices(array $indices) {

    // Make sure we don't have an empty array
    if(count($indices) === 0) {
      throw new \Exception('The ColumnIndices Trait requires a non-empty array of indices.');
    }

    // The temptation is to use the checkIndices() method here, but it requires
    // an array $row_values which feels like additional overhead

    // Set the indices array
    $this->context['indices'] = $indices;
  }

  /**
   * Returns an array of integers or keys which correspond to columns in a row
   * of delimited values that the validator instance should act on.
   *
   * @return array
   *   A one- or multi-dimensional array containing integers as values
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
