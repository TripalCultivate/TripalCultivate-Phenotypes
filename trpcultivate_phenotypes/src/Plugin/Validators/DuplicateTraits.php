<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\GenusConfigured;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;

/**
 * Validate duplicate traits within a file
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "duplicate_traits",
 *   validator_name = @Translation("Duplicate Traits Validator"),
 *   input_types = {"data-row"},
 * )
 */
class DuplicateTraits extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   *   This validator requires the following validator traits:
   *   - GenusConfigured: Gets a string of the configured genus name
   *   - ColumnIndices => Gets an associative array with the following keys,
   *       which are column headers of required columns for the Traits Importer:
   *     - 'Trait Name': int, the index of the trait name column in $row_values
   *     - 'Method Short Name': int, the index of the method name column in $row_values
   *     - 'Unit': int, the index of the unit column in $row_values
   */
  use ColumnIndices;
  use GenusConfigured;

  /**
   * A nested array of already validated values forming the unique trait name +
   *   method name + unit combinations that have been encountered by this
   *   validator so far within the input file. More specifically,
   *   - TRAIT-NAME: array of methods associated with this trait.
   *     - METHOD-NAME: array of units associated with this trait-method combo
   *       - UNIT-NAME: 1 (indicates this full trait-method-unit combo exists)
   *
   *   NOTE: All capital words are intended to be replaced by the actual full
   *   name of the term.
   */
  protected $unique_traits = [];

  /**
   * An instance of the Genus Ontology service for use in the methods in this
   * trait.
   *
   * Services should be injected via depenency injection in your validator class
   * and then assigned to this variable in your constructor.
   *
   * @var TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Traits Service
   *
   * @var TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ChadoConnection $chado_connection, TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology, TripalCultivatePhenotypesTraitsService $service_traits) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->chado_connection = $chado_connection;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
    $this->service_PhenoTraits = $service_traits;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.traits')
    );
  }

  /**
   * Validate the values within the cells of this row.
   *
   * @param array $row_values
   *   The contents of the file's row where each value within a cell is
   *   stored as an array element.
   *
   * @return array
   *   An associative array with the following keys.
   *   - case: a developer focused string describing the case checked.
   *   - valid: TRUE if the trait is unique and FALSE if it already exists.
   *   - failedItems: an array of "items" that failed with the following keys, to
   *     be used in the message to the user. This is an empty array if the data row input was valid.
   *     - combo_provided: The combo of trait, method, and unit provided in the file.
   *       The keys used are the same name of the column header for the cell containing
   *       the desired value.
   *       - Trait Name: The trait name provided in the file
   *       - Method Short Name: The method name provided in the file
   *       - Unit: The unit provided in the file
   */
  public function validateRow($row_values) {

    // Grab our indices
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    // Typically checkIndices() doesn't take an associative array but
    // because it checks the values not the keys, it will work in this
    // case as well.
    $this->checkIndices($row_values, $indices);

    // These are the key names we expect in our indices array
    $trait_key = 'Trait Name';
    $method_key = 'Method Short Name';
    $unit_key = 'Unit';

    // Grab our trait, method and unit values from the $row_values array
    // using our configured $indices array
    // We need to ensure that each array key we expect in $indices
    // exists, otherwise throw an exception
    if (!isset($indices[$trait_key])) {
      throw new \Exception('The trait name (key: Trait Name) was not set by setIndices()');
    }
    if (!isset($indices[$method_key])) {
      throw new \Exception('The method name (key: Method Short Name) was not set by setIndices()');
    }
    if (!isset($indices[$unit_key])) {
      throw new \Exception('The unit (key: Unit) was not set by setIndices()');
    }

    $trait = $row_values[$indices[$trait_key]];
    $method = $row_values[$indices[$method_key]];
    $unit = $row_values[$indices[$unit_key]];

    // Set our flags for tracking database and input file duplicates
    $duplicate_in_file = FALSE;
    $duplicate_in_db = FALSE;

    // Now check for the presence of our array within our global array
    // ie. has this trait combination been seen in this input file before?
    if (!empty($this->unique_traits)) {
      if (isset($this->unique_traits[$trait][$method][$unit])) {
        // Then we've found a duplicate
        $duplicate_in_file = TRUE;
      }
    }

    // Check if our trait combo exists at the database level
    // NOTE: The trait service was configured to use this genus by
    // the GenusConfigured trait when the genus was set.
    // Grab our trait combo.
    $trait_combo = $this->service_PhenoTraits->getTraitMethodUnitCombo($trait, $method, $unit);
    if (!empty($trait_combo)) {
      // Duplicate found
      $duplicate_in_db = TRUE;
    }

    // Finally, add to the global array as a row we've now seen
    $this->unique_traits[$trait][$method][$unit] = 1;

    // Then set the status of the validation
    if($duplicate_in_file) {
      if ($duplicate_in_db) {
        // This row is a duplicate of another row AND in the database
        $validator_status = [
          'case' => 'A duplicate trait was found within both the input file and the database',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              $trait_key => $trait,
              $method_key => $method,
              $unit_key => $unit
            ]
          ]
        ];
      }
      else {
        $validator_status = [
          'case' => 'A duplicate trait was found within the input file',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              $trait_key => $trait,
              $method_key => $method,
              $unit_key => $unit
            ]
          ]
        ];
      }
    }
    else if ($duplicate_in_db) {
      $validator_status = [
        'case' => 'A duplicate trait was found in the database',
        'valid' => FALSE,
        'failedItems' => [
          'combo_provided' => [
            $trait_key => $trait,
            $method_key => $method,
            $unit_key => $unit
          ]
        ]
      ];
    }
    // If not seen before in the file or in the database, then set the validation to pass
    else {
      $validator_status = [
        'case' => 'Confirmed that the current trait being validated is unique',
        'valid' => TRUE,
        'failedItems' => []
      ];
    }
    return $validator_status;
  }

  /**
   * Getter for the $unique_traits array.
   *
   * @return $unique_traits
   *   The array of unique trait name + method name + unit combinations that
   *   have been encountered by this validator so far within the input file
   *   More specifically, the array is as follows with capitalized words replaced
   *   by the term name.
   *   - TRAIT-NAME: array of methods associated with this trait.
   *     - METHOD-NAME: array of units associated with this trait-method combo
   *       - UNIT-NAME: 1 (indicates this full trait-method-unit combo exists)
   */
  public function getUniqueTraits() {
    return $this->unique_traits;
  }
}
