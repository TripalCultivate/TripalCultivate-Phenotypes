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
   * Validate items in the phenotypic data upload assets.
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
