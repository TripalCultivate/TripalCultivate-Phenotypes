<?php

/**
 * @file
 * Contains Drupal\TripalCultivatePhenotypes\TripalCultivatePhenotypesValidatorBase.
 */

namespace Drupal\trpcultivate_phenotypes;

use Drupal\Component\Plugin\PluginBase;

abstract class TripalCultivatePhenotypesValidatorBase extends PluginBase implements TripalCultivatePhenotypesValidatorInterface {
  /**
   * Project name/title.
   */
  public $project;

  /**
   * Genus.
   */
  public $genus;

  /**
   * Drupal File ID Number.
   */
  public $file_id;

  /**
   * Load phenotypic data upload assets to validated.
   * 
   * @param $project
   *   String, Project name/title - chado.project: name.
   * @param $genus
   *   String, Genus - chado.organism: genus.
   * @param $file_id
   *   Integer, Drupal file id number.
   */
  public function loadAssets($project, $genus, $file_id) {
    // Prepare assets, query db, or load file.
    $this->project = $project;
    $this->genus = $genus;
    $this->file_id = $file_id;
  }

  /**
   * Get validator plugin validator_name definition annotation value.
   * 
   * @return string
   *   The validator plugin name annotation definition value.
   */
  public function getValidatorName() {
    return $this->pluginDefinition['validator_name'];
  }

  /**
   * Get validator plugin validator_scope definition annotation value.
   * 
   * @return string
   *   The validator plugin scope annotation definition value.
   */
  public function getValidatorScope() {
    return $this->pluginDefinition['validator_scope'];
  }
}