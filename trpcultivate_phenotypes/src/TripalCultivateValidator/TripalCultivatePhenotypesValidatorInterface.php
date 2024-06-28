<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator;

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
   * Returns the input types supported by this validator.
   * These are defined in the class annotation docblock.
   *
   * @return array
   *   The inputTypes supported by this validator.
   */
  public function getSupportedInputTypes();

  /**
   * Confirms whether the given inputType is supported by this validator.
   *
   * @param string $input_type
   *   The input type to check.
   * @return boolean
   *   True if the input type is supported and false otherwise.
   */
  public function checkInputTypeSupported(string $input_type);

  /**
   * Validates the metadata associated with an importer.
   *
   * This should never validate the file even though it will likely be passed in
   * with the other form values.
   *
   * @param array $form_values
   *  An array of values from the submitted form where each key maps to a form
   *  element and the value is what the user entered.
   *
   * @return array
   *  An array of information about the validity of the data passed in.
   *  The supported keys are:
   *  - 'case': a developer code describing the case triggered
   *      (i.e. no record in chado matching project name). If the data is
   *      is valid then this is not required but could be 'data verified'.
   *  - 'valid': a boolean indicating the data is valid (TRUE) or not (FALSE)
   *  - 'failedIems': an array of information to customize messages for the UI.
   *      For example, if this validator checks a specific set of form elements
   *      then this array should be keyed by the form element key and the value
   *      match that provided by the user input in form_values.
   *  The old style keys we are deprecating are:
   *  - title: the title of the validation (shown both when passes or fails).
   *  - details: string describing the failure to users with failed items embedded.
   *  - status: one of 'pass' or 'fail'
   */
  public function validateMetadata(array $form_values);

  /**
   * Validates the file associated with an importer.
   *
   * This should validate the file object (e.g. it exists, is readable) but
   * should not validate the contents in any way.
   *
   * @param array $filename
   *  The full path and filename with extension of the file to validate.
   *
   * @return array
   *  An array of information about the validity of the data passed in.
   *  The supported keys are:
   *  - 'case': a developer code describing the case triggered
   *      (i.e. no record in chado matching project name). If the data is
   *      is valid then this is not required but could be 'data verified'.
   *  - 'valid': a boolean indicating the data is valid (TRUE) or not (FALSE)
   *  - 'failedIems': an array of information to customize messages for the UI.
   *      For example, if this validator checks the permissions of the file then
   *      this array might contain the permissions the file actually had that
   *      did not match what was expected.
   *  The old style keys we are deprecating are:
   *  - title: the title of the validation (shown both when passes or fails).
   *  - details: string describing the failure to users with failed items embedded.
   *  - status: one of 'pass' or 'fail'
   */
  public function validateFile(string $filename, int $fid);

  /**
   * Validates rows within the data file submitted to an importer.
   *
   * @param array $row_values
   *  An array of values from a single row/line in the file where each element
   *  is a single column.
   * @param array $context
   *   @deprecated Remove in issue #91
   *   An associative array containing the needed context, which is dependant
   *   on the validator.
   *   For example, instead of validating each cell by default, a validator may
   *   need a list of indices which correspond to the columns in the row for
   *   which the validator should act on.
   *
   * @return array
   *  An array of information about the validity of the data passed in.
   *  The supported keys are:
   *  - 'case': a developer code describing the case triggered
   *      (i.e. no record in chado matching project name). If the data is
   *      is valid then this is not required but could be 'data verified'.
   *  - 'valid': a boolean indicating the data is valid (TRUE) or not (FALSE)
   *  - 'failedIems': an array of the items that failed validation. For example,
   *      if this validator validates a number of indicies are not empty then
   *      this will be an array of indices that were empty or if this validator
   *      checks that a number of indices have values in a specific list then
   *      this array would use the index as the key and the value the column
   *      actually had that was not in the list for each failed column.
   *  The old style keys we are deprecating are:
   *  - title: the title of the validation (shown both when passes or fails).
   *  - details: string describing the failure to users with failed items embedded.
   *  - status: one of 'pass' or 'fail'
   */
  public function validateRow(array $row_values, array $context);

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
   * Return the scope of the validator.
   *
   * @deprecated Remove in issue #91
   *
   * @return string.
   */
  public function getValidatorScope();

  /**
   * Load data file import assets Project title, Genus and Data File Id
   * as entered in the Importer form.
   *
   * @deprecated Remove in issue #91
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
   * Validate items in the phenotypic data upload assets.
   *
   * @deprecated Remove in issue #91
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validate();

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
