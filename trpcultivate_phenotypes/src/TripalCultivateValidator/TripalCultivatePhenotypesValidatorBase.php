<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator;

use Drupal\Component\Plugin\PluginBase;
use Drupal\tripal\Services\TripalLogger;

abstract class TripalCultivatePhenotypesValidatorBase extends PluginBase implements TripalCultivatePhenotypesValidatorInterface {

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
   * An associative array containing the needed context, which is dependant
   * on the validator. For example, instead of validating each cell by default,
   * a validator may need a list of indices which correspond to the columns in
   * the row for which the validator should act on.
   *
   * Key-value pairs are set by the setter methods in ValidatorTraits.
   */
  protected array $context = [];

  /**
   * A mapping of supported file mime-types and their supported delimiters.
   *
   * More specifically, the file is split based on the appropriate delimiter
   * for the mime-type passed in. For example, the mime-type
   * "text/tab-separated-values" maps to the tab (i.e. "\t") delimiter.
   *
   * By using this mapping approach we can actually support a number of different
   * file types with different delimiters for the same importer while keeping
   * the performance hit to a minimum. Especially as in many cases, this is a
   * one-to-one mapping.
   *
   * @var array
   */
  public static array $mime_to_delimiter_mapping = [
    'text/tab-separated-values' => ["\t"],
    'text/csv' => [','],
    'text/plain' => ["\t", ','],
  ];


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
    return (array_key_exists('validator_scope', $this->pluginDefinition)) ? $this->pluginDefinition['validator_scope'] : NULL;
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
   * More specifically, the file is split based on the appropriate delimiter
   * for the mime type passed in. For example, the mime type text/tab-separated-values
   * maps to the tab (i.e. "\t") delimiter.
   *
   * By using this mapping approach we can actually support a number of different
   * file types with different delimiters for the same importer while keeping
   * the performance hit to a minimum. Especially as in many cases this is a
   * one-to-one mapping. If it is not a one-to-one mapping then we loop through
   * the options.
   *
   * @param string $row
   *   A line in the data file which has not yet been split into columns.
   * @param string $mime_type
   *   The mime type of the file currently being validated or imported (i.e. the
   *   mime type of the file this line is from).
   *
   * @return array
   *   An array containing the values extracted from the line after splitting it based
   *   on a delimiter value.
   */
  public static function splitRowIntoColumns(string $row, string $mime_type) {

    $mime_to_delimiter_mapping = self::$mime_to_delimiter_mapping;

    // Ensure that the mime type is in our delimiter mapping...
    if (!array_key_exists($mime_type, $mime_to_delimiter_mapping)) {
      throw new \Exception('The mime type "' . $mime_type . '" passed into splitRowIntoColumns() is not supported. We support the following mime types:' . implode(', ', array_keys($mime_to_delimiter_mapping)) . '.');
    }

    // Determine the delimiter we should use based on the mime type.
    $supported_delimiters = self::getFileDelimiters($mime_type);

    $delimiter = NULL;
    // If there is only one supported delimiter then we can simply split the row!
    if (sizeof($supported_delimiters) === 1) {
      $delimiter = end($supported_delimiters);
      $columns = str_getcsv($row, $delimiter);
    }
    // Otherwise we will have to try to determine which one is "right"?!?
    // Points to remember in the future:
    //  - We can't use the one that splits into the most columns as a text column
    // could include multiple commas which could overpower the overall number of
    // tabs in a tab-delimited plain text file.
    // - It would be good to confirm we are getting the same number of columns
    // for each line in a file but since this needs to be a static method we
    // would pass that information in.
    // - If we try to check for the same number of columns as expected, we have
    // to remember that researchers routinely add "Comments" columns to the end,
    // sometimes without a header.
    // - If going based on the number of columns in the header, the point above
    // still impacts this, plus this method is called when splitting the header
    // before any validators run!
    else {

      throw new \Exception("We don't currently support splitting mime types with multiple delimiter options as its not trivial to choose the correct one.");

      // $results = [];
      // $counts = [];
      // foreach ($supported_delimiters as $delimiter) {
      //   $results[$delimiter] = str_getcsv($row, $delimiter);
      //   $counts[$delimiter] = count($results[$delimiter]);
      // }

      // // Now lets choose the one with the most columns --shrugs-- not ideal
      // // but I'm not sure there is a better option. asort() is from smallest
      // // to largest preserving the keys so we want to choose the last element.
      // asort($counts);
      // $winning_delimiter = array_key_last($counts);
      // $columns = $results[ $winning_delimiter ];
      // $delimiter = $winning_delimiter;
    }

    // Now lets double check that we got some values...
    if (count($columns) == 1 && $columns[0] === $row) {
      // The delimiter failed to split the row and returned the original row.
      throw new \Exception('The data row or line provided could not be split into columns. The supported delimiter(s) are "' . implode('", "', $supported_delimiters) . '".');
    }

    // Sanitize values.
    foreach($columns as &$value) {
      if ($value) {
        $value = trim(str_replace(['"','\''], '', $value));
      }
    }

    return $columns;
  }

  /**
   * Gets the list of delimiters supported by the input file's mime-type that
   * was provided to the setter.
   *
   * NOTE: This method is static to allow for it to also be used by the static
   * method splitRowIntoColumns().
   *
   * @param string $mime_type
   *   A string that is the mime-type of the input file.
   *
   *   HINT: You can get the mime-type of a file from the 'mime-type' property
   *   of a file object.
   *
   * @return array
   *   The list of delimiters that are supported by the file mime-type.
   *
   * @throws \Exception
   *   - If mime_type does not exist as a key in the mime_to_delimiter_mapping
   *     array.
   */
  public static function getFileDelimiters(string $mime_type) {

    // Check if mime type is an empty string.
    if (empty($mime_type)) {
      throw new \Exception("The getFileDelimiters() getter requires a string of the input file's mime-type and must not be empty.");
    }

    // Grab the delimiters for this mime-type.
    if (array_key_exists($mime_type, self::$mime_to_delimiter_mapping)) {
      return self::$mime_to_delimiter_mapping[$mime_type];
    }
    else {
      throw new \Exception('Cannot retrieve file delimiters for the mime-type provided: ' . $mime_type);
    }
  }

  /**
   * The TripalLogger service used to report status and errors to both site users
   * and administrators through the server log.
   *
   * @var TripalLogger
   */
  public TripalLogger $logger;

  /**
   * Sets the TripalLogger instance for the importer using this validator.
   *
   * @param TripalLogger $logger
   *   The TripalLogger instance. In the case of validation done on the form
   *   the job will not be set but in the case of any validation done in the
   *   import run job, the job will be set.
   */
  public function setLogger(TripalLogger $logger) {
    $this->logger = $logger;
  }

  /**
   * Provides a configured TripalLogger instance for reporting things to
   * site maintainers.
   *
   * @return TripalLogger
   *   An instance of the Tripal logger.
   *
   * @throws \Exception
   *   If the $logger property has not been set by the setLogger() method.
   */
  public function getLogger() {
    if(!empty($this->logger)) {
      return $this->logger;
    }
    else {
      throw new \Exception('Cannot retrieve the Tripal Logger property as one has not been set for this validator using the setLogger() method.');
    }

  }
}
