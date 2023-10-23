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
   * 
   * @return void.
   */
  public function loadAssets($project, $genus, $file_id);

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
}