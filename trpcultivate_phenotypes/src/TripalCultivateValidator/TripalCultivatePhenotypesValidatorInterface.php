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
   *  - 'failedIems': an array of the items that failed validation. For example,
   *      if this validator validates a number of indicies are not empty then
   *      this will be an array of indices that were empty.
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
   *  - 'failedIems': an array of the items that failed validation. For example,
   *      if this validator validates a number of indicies are not empty then
   *      this will be an array of indices that were empty.
   *  The old style keys we are deprecating are:
   *  - title: the title of the validation (shown both when passes or fails).
   *  - details: string describing the failure to users with failed items embedded.
   *  - status: one of 'pass' or 'fail'
   */
  public function validateFile(string $filename, int $fid);

  /**
   * Validates rows within the data file submitted to an importer.
   *
   * @param array $row
   *  An array of values from a single row/line in the file where each element
   *  is a single column.
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
   *      this will be an array of indices that were empty.
   *  The old style keys we are deprecating are:
   *  - title: the title of the validation (shown both when passes or fails).
   *  - details: string describing the failure to users with failed items embedded.
   *  - status: one of 'pass' or 'fail'
   */
  public function validateRow(array $form_values);

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
