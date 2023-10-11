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
}