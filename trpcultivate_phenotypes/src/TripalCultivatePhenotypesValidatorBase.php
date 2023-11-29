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
   * Required column headers as defined in the importer.
   */
  public $column_headers;

  /**
   * Skip flag, indicate validator not to execute validation logic and
   * set the validator as upcoming or todo.
   */
  public $skip;

  /**
   * Load phenotypic data upload assets to validated.
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
   */
  public function loadAssets($project, $genus, $file_id, $headers, $skip = 0) {
    // Prepare assets:

    // Project.
    $this->project = $project;
    // Genus.
    $this->genus = $genus;
    // File id.
    $this->file_id = $file_id;
    // Column Headers.
    $this->column_headers = $headers;
    // Skip.
    $this->skip = $skip;
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