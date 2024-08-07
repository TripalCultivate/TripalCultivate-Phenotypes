<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator;

use Drupal\Component\Plugin\PluginBase;

abstract class TripalCultivatePhenotypesValidatorBase extends PluginBase implements TripalCultivatePhenotypesValidatorInterface {
  /**
   *   An associative array containing the needed context, which is dependant
   *   on the validator. For example, row level validators are passed the raw
   *   row from the file and thus need the importer to indicate how it should
   *   be split.
   *
   *   Row level validators require the following key(s):
   *   - delimiter => the delimiter to be passed to explode in order to break
   *     the raw row string into an array of columns.
   */
  public $context = [];

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
   * Returns the input types supported by this validator.
   * These are defined in the class annotation docblock.
   *
   * @return array
   *   The input types supported by this validator.
   */
  public function getSupportedInputTypes() {
    return $this->pluginDefinition['input_types'];
  }

  /**
   * Confirms whether the given inputType is supported by this validator.
   *
   * @param string $input_type
   *   The input type to check.
   * @return boolean
   *   True if the input type is supported and false otherwise.
   */
  public function checkInputTypeSupported(string $input_type) {
    $supported_types = $this->getSupportedInputTypes();

    if (in_array($input_type, $supported_types)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateMetadata(array $form_values) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateMetadata() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function validateFile(string $filename, int $fid) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateFile() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function validateRow(array $row_values) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateRow() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function validateRawRow(string $raw_row) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateRawRow() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   * @deprecated Remove in issue #91
   */
  public function validate() {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validate() from base class called for $plugin_name. This method is being deprecated and should be upgraded to validateMetadata(), validateFile() or validateRow().");
  }

  /**
   * Project name/title.
   * @deprecated Remove in issue #91
   */
  public $project;

  /**
   * Genus.
   * @deprecated Remove in issue #91
   */
  public $genus;

  /**
   * Drupal File ID Number.
   * @deprecated Remove in issue #91
   */
  public $file_id;

  /**
   * Required column headers as defined in the importer.
   * @deprecated Remove in issue #91
   */
  public $column_headers;

  /**
   * Skip flag, indicate validator not to execute validation logic and
   * set the validator as upcoming or todo.
   * @deprecated Remove in issue #91
   */
  public $skip;

  /**
   * Load phenotypic data upload assets to validated.
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
   * Get validator plugin validator_scope definition annotation value.
   *
   * @deprecated Remove in issue #91
   *
   * @return string
   *   The validator plugin scope annotation definition value.
   */
  public function getValidatorScope() {
    return $this->pluginDefinition['validator_scope'];
  }

  /**
   * {@inheritdoc}
   */
  public function checkIndices($row_values, $indices) {

    // Report if the indices array is empty
    if (!$indices) {
      throw new \Exception(
        t('An empty indices array was provided.')
      );
    }

    // Get the potential range by looking at $row_values
    $num_values = count($row_values);
    // Count our indices array
    $num_indices = count($indices);
    if($num_indices > $num_values) {
      throw new \Exception(
        t('Too many indices were provided (@indices) compared to the number of cells in the provided row (@values)', ['@indices' => $num_indices, '@values' => $num_values])
      );
    }

    // Pull out just the keys from $row_values and compare with $indices
    $row_keys = array_keys($row_values);
    $result = array_diff($indices, $row_keys);
    if($result) {
      $invalid_indices = implode(', ', $result);
      throw new \Exception(
        t('One or more of the indices provided (@invalid) is not valid when compared to the indices of the provided row', ['@invalid' => $invalid_indices])
      );
    }
  }

  /**
   * Traits, method and unit may be created/inserted through
   * the phenotypic data importer using the configuration allow new.
   * This method will fetch the value set for allow new configuration.
   *
   * @return boolean
   *   True, allow trait, method and unit detected in data importer to be created. False will trigger
   *   validation error and will not permit creation of terms.
   */
  public function getConfigAllowNew() {
    $allownew = \Drupal::config('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.ontology.allownew');

    return $allownew;
  }

  /**
   * Split or explode a data file line/row values into an array using a delimiter.
   * 
   * @param string $row
   *   A line in the data file.
   * 
   * @return array
   *   An array containing the values extracted from the line after splitting it based
   *   on a delimiter value.
   */
  public static function splitRowIntoColumns(string $row) {
    // Delimiter:

    // @TODO: use the delimiter getter.
    $delimiter = "\t";
    if (empty($delimiter)) {
      throw new \Exception('No delimiter provided.');
    }
    
    // Split the values.
    $values = explode($delimiter, $row);

    if (count($values) == 1 && $values[0] === $row) {
      // The delimiter failed to split the row and returned the original row.
      throw new \Exception('The data row or line provided could not be split using the delimiter (' . $delimiter . ').');  
    }

    // Sanitize values.
    foreach($values as &$value) {
      if ($value) {
        $value = trim(str_replace(['"','\''], '', $value)); 
      }
    }
    
    return $values;
  }
}
