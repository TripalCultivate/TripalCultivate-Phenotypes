<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides setters focused for setting required number of columns and strict
 * comparison flag used by the importer, and getter to retrieve the set values.
 */
trait ColumnCount {

  /**
   * Set a number of required columns.
   *
   * @param integer $number_of_columns
   *   An integer value greater than zero.
   * @param bool $strict
   *   This will indicate whether the value $number_of_columns is the minimum
   *   number of columns required in an input file's row, or if it is strictly the only
   *   acceptable number of columns.
   *   - FALSE (default) = minimum number of columns.
   *   - TRUE = the strict number of required columns.
   *
   * @return void
   *
   * @throws \Exception
   *  - The number of columns is less than or equals to 0.
   */
  public function setExpectedColumns(int $number_of_columns, bool $strict = FALSE) {

    $context_key = 'column_count';

    if ($number_of_columns <= 0) {
      throw new \Exception('setExpectedColumns() in validator requires an integer value greater than zero.');
    }

    $this->context[ $context_key ] = [
      'number_of_columns' => $number_of_columns,
      'strict'  => $strict
    ];
  }

  /**
   * Get the number of columns set.
   *
   * @return array
   *   The expected column number and strict flag validator configuration
   *   set by the setter method, keyed by:
   *   - number_of_columns: the number of expected column number.
   *   - strict: strict comparison flag.
   *
   * @throws \Exception
   *  - The column number was not configured by the setter method.
   */
  public function getExpectedColumns() {

    $context_key = 'column_count';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[ $context_key ];
    }
    else {
      throw new \Exception('Cannot retrieve the number of expected columns as one has not been set by setExpectedColumns().');
    }
  }
}
