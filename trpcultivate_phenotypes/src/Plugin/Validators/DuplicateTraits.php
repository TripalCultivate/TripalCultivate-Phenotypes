<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\GenusConfigured;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;

/**
 * Validate duplicate traits within a file.
 */
#[TripalCultivateValidator(
   id: 'duplicate_traits',
   validator_name: new TranslatableMarkup("Duplicate Traits Validator"),
   input_types: ['data-row'],
 )]
class DuplicateTraits extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * Validator Traits required by this validator.
   *
   * - GenusConfigured: Gets a string of the configured genus name.
   * - ColumnIndices: Gets an associative array with the following keys,
   *   which are column headers of required columns for the Traits Importer:
   *   - 'Trait Name': int, the index of the trait name column in $row_values.
   *   - 'Method Short Name': int, the index of the method name column in
   *     $row_values.
   *   - 'Unit': int, the index of the unit column in $row_values.
   */
  use ColumnIndices;
  use GenusConfigured;

  /**
   * Keeps tracks of already validated trait + method + unit combinations.
   *
   * @var array
   * A nested array of already validated values forming the unique trait name +
   *   method name + unit combinations that have been encountered by this
   *   validator so far within the input file. More specifically,
   *   - TRAIT-NAME: array of methods associated with this trait.
   *     - METHOD-NAME: array of units associated with this trait-method combo.
   *       - UNIT-NAME: 1 (indicates this full trait-method-unit combo exists).
   *
   *   NOTE: All capital words are intended to be replaced by the actual full
   *   name of the term.
   */
  protected $unique_traits = [];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * An instance of the Genus Ontology service.
   *
   * Services should be injected via dependency injection in your validator
   * class and then assigned to this variable in your constructor.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * An instance of the Traits Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits;

  /**
   * Constructs an instance of the Duplicate Traits validator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   The genus ontology service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   The traits service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->chado_connection = $chado_connection;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
    $this->service_PhenoTraits = $service_PhenoTraits;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
   *   An array of values from a single row/line in the file where each value
   *   is a single column.
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the trait is unique and FALSE if it already exists.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the data row input was valid.
   *     - 'combo_provided': The combination of trait, method, and unit provided
   *       in the file. The keys used are the same name of the column header for
   *       the cell containing the desired value.
   *       - 'Trait Name': The trait name provided in the file
   *       - 'Method Short Name': The method name provided in the file
   *       - 'Unit': The unit provided in the file
   *
   * @throws \Exception
   *   - If getIndices() returns an array that is missing any of the following
   *     keys (ie. they were not set by setIndices() in the importer):
   *     'Trait Name', 'Method Short Name', 'Unit'.
   */
  public function validateRow($row_values) {

    // Grab our indices.
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    // Typically checkIndices() doesn't take an associative array but
    // because it checks the values not the keys, it will work in this
    // case as well.
    $this->checkIndices($row_values, $indices);

    // These are the key names we expect in our indices array.
    $trait_key = 'Trait Name';
    $method_key = 'Method Short Name';
    $unit_key = 'Unit';

    // Grab our trait, method and unit values from the $row_values array
    // using our configured $indices array.
    // We need to ensure that each array key we expect in $indices
    // exists, otherwise throw an exception.
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

    // Set our flags for tracking database and input file duplicates.
    $duplicate_in_file = FALSE;
    $duplicate_in_db = FALSE;

    // Now check for the presence of our array within our global array.
    // ie. Has this trait combination been seen in this input file before?
    if (!empty($this->unique_traits)) {
      if (isset($this->unique_traits[$trait][$method][$unit])) {
        $duplicate_in_file = TRUE;
      }
    }

    // Check if our trait combo exists at the database level.
    // NOTE: The trait service was configured to use this genus by
    // the GenusConfigured trait when the genus was set.
    $trait_combo = $this->service_PhenoTraits->getTraitMethodUnitCombo($trait, $method, $unit);
    if (!empty($trait_combo)) {
      $duplicate_in_db = TRUE;
    }

    // Finally, add to the global array as a row we've now seen.
    $this->unique_traits[$trait][$method][$unit] = 1;

    // Now set the status of validation.
    if ($duplicate_in_file) {
      if ($duplicate_in_db) {
        // This row is a duplicate of another row AND in the database.
        $validator_status = [
          'case' => 'A duplicate trait was found within both the input file and the database',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              $trait_key => $trait,
              $method_key => $method,
              $unit_key => $unit,
            ],
          ],
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
              $unit_key => $unit,
            ],
          ],
        ];
      }
    }
    elseif ($duplicate_in_db) {
      $validator_status = [
        'case' => 'A duplicate trait was found in the database',
        'valid' => FALSE,
        'failedItems' => [
          'combo_provided' => [
            $trait_key => $trait,
            $method_key => $method,
            $unit_key => $unit,
          ],
        ],
      ];
    }
    // If a combo was not duplicated in the file or in the database, set
    // validation to pass.
    else {
      $validator_status = [
        'case' => 'Confirmed that the current trait being validated is unique',
        'valid' => TRUE,
        'failedItems' => [],
      ];
    }
    return $validator_status;
  }

  /**
   * Getter for the $unique_traits array.
   *
   * @return array
   *   The array of unique trait name + method name + unit combinations that
   *   have been encountered by this validator so far within the input file.
   *   More specifically, the array is as follows with capitalized words
   *   replaced by the term name.
   *   - TRAIT-NAME: array of methods associated with this trait.
   *     - METHOD-NAME: array of units associated with this trait-method combo
   *       - UNIT-NAME: 1 (indicates this full trait-method-unit combo exists)
   */
  public function getUniqueTraits() {
    return $this->unique_traits;
  }

}
