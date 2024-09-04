<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused on configuring a validator that processes the columns
 * in a file row and checks the value in the column against a list of valid values
 */
trait ValidValues {

  /**
   * Sets a list of values that should be allowed for a cell being validated.
   *
   * Note: Often this setter is combined with the setIndices() setter which indicates
   * which columns should have one of these values.
   *
   * @param array $valid_values
   *   A one-dimensional array of values that are allowed within the cell(s) that
   *   are being validated in a file row
   * @return void
   *
   * @throws \Exception
   *  - If $valid_values array is empty
   *  - If $valid_values array contains values that are not of type string or integer
   */
  public function setValidValues(array $valid_values) {

    // Make sure we don't have an empty array
    if(count($valid_values) === 0) {
      throw new \Exception('The ValidValues Trait requires a non-empty array to set valid values.');
    }

    // Check if we have a multidimentsional array or array of objects
    foreach ($valid_values as $value) {
      if (!(is_string($value) || (is_int($value)))) {
        throw new \Exception('The ValidValues Trait requires a one-dimensional array with values that are of type integer or string only.');
      }
    }

    // Set the valid_values array
    $this->context['valid_values'] = $valid_values;
  }

  /**
   * Returns a list of allowed values for cell(s) being validated. Specifically, it
   * is expected that the cell must contain one of the values in this list.
   *
   * @return array
   *   A one-dimensional array containing valid values
   *
   * @throws \Exception
   *  - If the 'valid_values' key does not exist in the context array (ie. the
   *    'valid_values' array has NOT been set)
   */
  public function getValidValues() {

    if (array_key_exists('valid_values', $this->context)) {
      return $this->context['valid_values'];
    }
    else {
      throw new \Exception("Cannot retrieve an array of valid values as one has not been set by the setValidValues() method.");
    }
  }
}
