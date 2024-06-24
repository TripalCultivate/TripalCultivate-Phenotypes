<?php

/**
 * @file
 * Contains \Drupal\trpcultivate_phenotypes\Interface\TripalCultivatePhenotypesValidatorInterface.
 *
 * @see Plugin manager in src\TripalCultivatePhenotypesValidatorManager.php
 */

namespace Drupal\trpcultivate_phenotypes;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for data validator plugin.
 */
interface TripalCultivatePhenotypesValidatorInterface extends PluginInspectionInterface {
  /**
   * Return the name of the validator.
   *
   * @return string.
   */
  public function getValidatorName();

  /**
   * Return the scope of the validator.
   *
   * @return string.
   */
  public function getValidatorScope();

  /**
   * Load data file import assets Project title, Genus and Data File Id
   * as entered in the Importer form.
   *
   * @param $project
   *   String, Project name/title - chado.project: name.
   * @param $genus
   *   String, Genus - chado.organism: genus.
   * @param $file_id
   *   Integer, Drupal file id number.
   * @param $headers
   *   Array, required column headers defined in the importer.
   * @param $skip
   *   Boolean, skip flag when set to true will skip the validation
   *   logic and set the validator as upcoming/todo.
   *   Default: false - execute validation process.
   *
   * @return void.
   */
  public function loadAssets($project, $genus, $file_id, $headers, $skip);

  /**
   * Validates a single row in a file that is provided to an importer.
   *
   * @param array $row_values
   *   The contents of the file's row where each value within a cell is
   *   stored as an array element
   * @param array $context
   *   An associative array containing the needed context, which is dependant
   *   on the validator.
   *   For example, instead of validating each cell by default, a validator may
   *   need a list of indices which correspond to the columns in the row for
   *   which the validator should act on.
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validateRow($row_values, $context);

  /**
   * Given an array of values (that represents a single row in an input file),
   * check that the list in $indices is within the range of keys in $row_values.
   *
   * @param array $row_values
   *   The contents of the file's row where each value within a cell is
   *   stored as an array element
   * @param array $indices
   *   A one dimensional array of indices which correspond to which indices in
   *   $row_values the validator instance should act on.
   *
   * @throws
   *   - An exception if $indices is an empty array
   *   - An exception if $indices has more values than $row_values
   *   - An exception if any of the indices in $indices is out of bounds
   *     of the keys for $row_values
   */
  public function checkIndices($row_values, $indices);

  /**
   * Traits, method and unit may be created/inserted through
   * the phenotypic data importer using the configuration allow new.
   * This method will fetch the value set for allow new configuration.
   *
   * @return boolean
   *   True, allow trait, method and unit detected in data importer to be created. False will trigger
   *   validation error and will not permit creation of terms.
   */
  public function getConfigAllowNew();
}
