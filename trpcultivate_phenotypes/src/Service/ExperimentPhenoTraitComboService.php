<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Experiment - Phenotypic Combo Service.
 *
 * This service handles all processes linking an experiment with an
 * existing Phenotypic Combo (i.e. Trait - Method - Unit Combo)
 * and setting status flags relating to that link.
 *
 * Definitions:
 * - PhenoCombo refers to the combination of trait, method, and unit which
 *   uniquely identify a phenotypic measurement. These combinations are stored
 *   in Chado and managed by the Trait service.
 *
 *   @see TripalCultivatePhenotypesTraitsService
 *
 * - Experiment refers to a Tripal Content type defined by the
 *   TripalCultivate base module which stores it's data as a record
 *   in the chado project table.
 *
 *   @see 'research_experiment' content type.
 */
class ExperimentPhenoTraitComboService {

  /**
   * Experiment context.
   *
   * @var int|null
   */
  private int|null $experiment_context;

  /**
   * The table name that contains experiment pheno trait combos.
   *
   * @var string
   */
  public const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Status flags to table field combo status mapping.
   *
   * @var array
   */
  public const PHENO_COMBO_STATUS_FLAG_MAP = [
    'archived' => 'is_archived',
    'required' => 'is_required',
    'collected' => 'was_collected',
    'shared' => 'was_shared',
  ];

  /**
   * Pheno combo alias to table field (id) mapping.
   *
   * @var array
   */
  public const TRAIT_COMBO_KEY_MAP = [
    'trait' => 'attr_id',
    'method' => 'observable_id',
    'unit' => 'unit_id',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user session service.
   * @param \Drupal\Core\Database\Connection $drupaldb_connection
   *   Drupal database connection.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService $service_PhenoTerms
   *   Drupal config factory service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   TripalCultivate Phenotypes Genus-Project service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   TripalCultivate Phenotypes Genus-Ontology service.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\tripal\Services\TripalLogger $tripal_logger
   *   Tripal logger service.
   */
  public function __construct(
    protected AccountInterface $current_user,
    protected Connection $drupaldb_connection,
    protected TripalCultivatePhenotypesTermsService $service_PhenoTerms,
    protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
    protected TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    protected ChadoConnection $chado_connection,
    protected TripalLogger $tripal_logger,
  ) {

    $this->current_user = $current_user;
    $this->drupaldb_connection = $drupaldb_connection;

    $this->service_PhenoTerms = $service_PhenoTerms;
    $this->service_PhenoTraits = $service_PhenoTraits;
    $this->service_PhenoGenusProject = $service_PhenoGenusProject;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;

    $this->chado_connection = $chado_connection;
    $this->tripal_logger = $tripal_logger;

    // Begin with no experiment context to enforce context setter method.
    $this->experiment_context = NULL;
  }

  /**
   * Set the experiment context.
   *
   * This context is used to restrict all read/write operations so that only
   * records belonging to the specified experiment context can be accessed,
   * retrieved or modified.
   *
   * @param int|string $experiment
   *   The experiment identifier, either:
   *   - An integer (int) value corresponding to the project_id number.
   *   - A string (string) value corresponding to the project name.
   *   Both forms reference a field from the same Chado 'projects' table.
   *
   * @throws InvalidArgumentException
   *   - Experiment does to correspond to an row in the Chado projects table.
   *   - Experiment is not configured with a genus.
   */
  public function setExperiment(int|string $experiment): void {

    // Verify the existence of the experiment by fetching the project_id/name.
    $experiment_id = (is_int($experiment) && ChadoProjectAutocompleteController::getProjectName($experiment))
      ? $experiment : ChadoProjectAutocompleteController::getProjectId($experiment);

    if ($experiment_id === 0 || $experiment_id === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid experiment identifier in %s: received experiment identifier - %s that does not exist.',
          __METHOD__,
          $experiment
        )
      );
    }

    // If phenotypes module in the host site has no genus configured, or if the
    // experiment context has not been paired with a pheno-configured genus.
    if (empty($this->service_PhenoGenusOntology->getConfiguredGenusList()) ||
        empty($this->service_PhenoGenusProject->getGenusOfProject($experiment_id))) {

      $this->tripal_logger->error(
        $invalid_exp_error = sprintf(
          'Invalid experiment identifier in %s: received experiment identifier - %s that is not configured with a Genus.',
          __METHOD__,
          $experiment,
          gettype($experiment),
        )
      );

      // Adding a log entry about the experiment attempted to contain combos.
      $this->tripal_logger->error($invalid_exp_error);
      throw new \InvalidArgumentException($invalid_exp_error);
    }

    $this->experiment_context = $experiment_id;
  }

  /**
   * Assign a trait-method-unit combo to an experiment.
   *
   * @param array $trait_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   This MUST ALREADY EXISTS. The following keys are expected:
   *   - 'trait' (integer|string): a string value is the trait name, whereas an
   *     integer value is the trait id (a value to phenotype.attr_id).
   *   - 'method' (integer|string): a string value is the method name, whereas
   *     an integer value is the method id (a value to phenotype.observable_id).
   *   - 'unit' (integer|string): a string value is the trait unit, whereas an
   *     integer value is the unit id (a value to phenotype.unit_id).
   * @param array $combo_experiment_details
   *   An associative array describing the label and status flags to assign to
   *   to this trait combo within an experiment.
   *   The following keys are supported:
   *   - 'label' (REQUIRED): a human-readable text label used as an alternative
   *     reference to the trait name. Labels must be unique within experiment.
   *   - 'is_archived': 1 if archived, 0 otherwise.
   *   - 'is_required': 1 if required, 0 otherwise.
   *   - 'was_shared': 1 if used in Phenotypes Share module, 0 otherwise.
   *   - 'was_collected': 1 if measured in Phenotypes Collect module, 0 if not.
   *
   *   The default values of each status flags at assignment and if a flag(s) is
   *   not provided.
   *   - 'is_archived': 0 (no, is an active combo).
   *   - 'is_required': 0 (no, is an optional combo).
   *   - 'is_shared': 0 (no, not used in Shared Module to start with).
   *   - 'is_collected': 0 (no, not used in Collect Module to start with).
   *
   * @return int
   *   The last inserted combo_id as a return value of an insert query.
   *
   * @throws \InvalidArgumentException
   *   - Not an array value (data type) pass to both parameters.
   *     defined for each parameter in the parameter definition.
   *   - Not an integer or string value (data type) passed to either parameters.
   *   - Trait combo already exists with in the experiment.
   */
  public function assignPhenoComboToExperiment(array $trait_combo, array $combo_experiment_details): int {

    $this->ensureExperimentIsSet();
    $this->validateTraitCombo($trait_combo);

    // Verify label field is provided in combo exp. details.
    $label = isset($combo_experiment_details['label']) ? trim($combo_experiment_details['label']) : '';
    if (empty($label) || !$this->labelIsUniqueInExperiment($label)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing or duplicate label error in %s: combo label must exists, unique withing the experiment, and must not be an empty string.',
          __METHOD__
        )
      );
    }

    // Metadata fields.
    $field_metadata = [
      'project_id' => $this->experiment_context,
      'uid' => $this->current_user->id(),
      'label' => $label,
      'timestamp' => time(),
    ];

    // Status flags:
    $field_status_flags = [];
    foreach (array_values(self::PHENO_COMBO_STATUS_FLAG_MAP) as $field) {
      $field_status_flags[$field] = isset($combo_experiment_details[$field])
        ? (int) ((bool) $combo_experiment_details[$field]) : 0;
    }

    // Trait combo fields.
    $field_combo_ids = [];
    $resolved_trait_combo = $this->resolveTraitCombo($trait_combo);
    $combo_count_query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl');

    foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
      $field_combo_ids[$field] = $resolved_trait_combo[$alias];
      $combo_count_query->condition('tbl.' . $field, $resolved_trait_combo[$alias], '=');
    }

    // Ensure trait combo, independent of the label, is unique in an experiment.
    $combo_count_query
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($combo_count_query) {
      throw new \InvalidArgumentException(
        sprintf(
          'Duplicate trait combo error in %s: trait combo already exists in the experiment.',
          __METHOD__
        )
      );
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $combo_id = $this->drupaldb_connection
        ->insert(self::PHENO_COMBO_TABLE)
        ->fields($field_metadata + $field_status_flags + $field_combo_ids)
        ->execute() ?: 0;
    }
    catch (\Exception $e) {
      $db_transaction->rollBack();

      $this->tripal_logger->error($e->getMessage());
      throw new \Exception($e->getMessage());
    }

    return $combo_id;
  }

  /**
   * Get a single specific pheno trait combo in an experiment.
   *
   * @param array|string $combo
   *   The combo to be retrieved, the following are supported:
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment.
   *   - label (string) uniquely identifying this pheno combo in experiment.
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::assignPhenoComboToExperiment()
   *
   * @return array
   *   An associative array (keyed by combo label) describing the experiment
   *   trait-method-unit combo including the combo_id, project_id, attr_id,
   *   observable_id, unit_id, uid, label, is_archived, is_required,
   *   was_collected, was_shared, and timestamp from trpcultivate_phenocombo
   *   table. Furthermore, 'trait', 'method' and 'unit' are the cvterm names
   *   referenced by the 'attr_id', 'observable_id', and 'unit_id' respectively.
   *   An empty array if not found.
   *   @see trpcultivate_phenotypes_schema()
   *
   * @throws \InvalidArgumentException
   *   - Not an array or string value (data type) $combo parameter.
   */
  public function getExperimentPhenoCombo(array|string $combo): array {

    $this->ensureExperimentIsSet();
    $this->validateCombo($combo);

    $combo_id = $this->resolvePhenoCombo($combo);
    if ($combo_id !== 0) {
      $pheno_combo = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
        ->fields('tbl')
        ->condition('tbl.combo_id', $combo_id, '=')
        ->execute()
        ->fetchAssoc('label');
    }

    return $pheno_combo ?? [];
  }

  /**
   * Ensures that experiment context has been set.
   *
   * @throws Exception
   *   - Operation where experiment context is required but not set prior.
   */
  protected function ensureExperimentIsSet(): void {

    if ($this->experiment_context === NULL || $this->experiment_context === 0) {
      throw new \Exception(
        sprintf(
          'Experiment context not set error in %s: experiment context must be set before performing operations.
           Use setExperiment(EXPERIMENT ID or NAME) to set an experiment context.',
          get_class($this)
        )
      );
    }
  }

  /**
   * Validate trait combo array.
   *
   * @param array $trait_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::assignPhenoComboToExperiment()
   *
   * @throws InvalidArgumentException
   *   - Not array.
   *   - An array but is missing required key(s) - trait, method, or unit.
   *   - Value for each key is neither integer or string value.
   */
  private function validateTraitCombo(array $trait_combo): void {

    if (!is_array($trait_combo)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received trait combo with invalid data types. Value must be of type [array].',
          __METHOD__
        )
      );
    }

    // Ensure that array has the expected keys - trait, method and unit.
    $trait_combo_keys = array_keys(self::TRAIT_COMBO_KEY_MAP);
    if (array_diff($trait_combo_keys, $input_keys = array_keys($trait_combo))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing key error in %s: received trait combo with missing key(s). Value must have keys [%s]. You provided [%s]',
          __METHOD__,
          implode(', ', $trait_combo_keys),
          implode(', ', $input_keys)
        )
      );
    }

    // Ensure that value for each combo key is either integer or string type.
    $unexpected_values = [];
    foreach ($trait_combo_keys as $alias) {
      if (!is_int($trait_combo[$alias]) && !is_string($trait_combo[$alias])) {
        $unexpected_values[] = $alias;
      }
    }

    if (count($unexpected_values) > 0) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received trait combo with invalid data types for keys - [%s]. Use only integer or string value.',
          __METHOD__,
          implode(', ', $unexpected_values)
        )
      );
    }
  }

  /**
   * Validate pheno combo array.
   *
   * @param array $combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::getExperimentPhenoCombo()
   *
   * @throws InvalidArgumentException
   *   - Not array.
   *   - An array but is missing required key(s) - trait, method, or unit.
   *   - Value for each key is neither integer or string value.
   */
  private function validateCombo(array|int|string $combo):void {

    switch (gettype($combo)) {

      case 'array':
        // Argument combo is trait combo.
        $trait_combo = $combo;
        $this->validateTraitCombo($trait_combo);

        break;

      case 'integer':
        // Argument combo is combo_id.
        $combo_id = $combo;

        if ($combo_id <= 0) {
          throw new \InvalidArgumentException(
            'Invalid combo id in ' . __METHOD__ . ': received an invalid combo id value. Must be integer greater than 0.'
          );
        }

        break;

      case 'string':
        // Argument combo is label.
        $label = trim($combo);

        if ($label === '') {
          throw new \InvalidArgumentException(
            'Invalid label in ' . __METHOD__ . ': received an invalid label value. Must be a string and not empty.'
          );
        }

        break;

      default:
        throw new \InvalidArgumentException(
          'The pheno combo array provided to ' . __METHOD__ . ', can only be array, string or integer value.'
        );
    }
  }

  /**
   * Resolve trait combo keys trait, method, and unit to cvterm ids.
   *
   * This resolves the trait combo without explicitly setting a genus context.
   * Genus context is derived from the genus the experiment is configured with.
   *
   * @param array $trait_combo
   *   The trait-method-unit combination.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::assignPhenoComboToExperiment()
   *
   * @return array
   *   An associative array where the keys are trait, method, and unit and the
   *   values are the cvterm id (NOT THE CVTERM RECORD) for each key.
   *
   * @throws InvalidArgumentException
   *   - The trait combo array is missing required key.
   *   - Trait combo array value(s) is not of type integer or string.
   *   - Trait combo did not match a trait-method-unit combo. Does not exist.
   */
  protected function resolveTraitCombo(array $trait_combo): array {

    // Verify that trait-method-unit returns a combo record.
    $exp_phenogenus = $this->service_PhenoGenusProject
      ->getGenusOfProject($this->experiment_context);

    // In each genus of project, search the trait combo.
    $pheno_combo = 0;
    foreach ($exp_phenogenus as $genus) {
      try {
        $this->service_PhenoTraits->setTraitGenus($genus);
        $pheno_combo = $this->service_PhenoTraits
          ->getTraitMethodUnitCombo($trait_combo['trait'], $trait_combo['method'], $trait_combo['unit']);

        if ($pheno_combo !== NULL) {
          break;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    if ($pheno_combo === 0) {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid trait combo error in %s: received trait combo that does not match a trait-method-unit record.',
          __METHOD__
        )
      );
    }

    $resolved_trait_combo = [];
    foreach (array_keys(self::TRAIT_COMBO_KEY_MAP) as $alias) {
      $resolved_trait_combo[$alias] = $pheno_combo[$alias]->cvterm_id;
    }

    return $resolved_trait_combo;
  }

  /**
   * Resolve pheno combo parameter values to pheno combo id.
   *
   * @param array|int|string $combo
   *   The trait-method-unit pheno combo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::getExperimentPhenoCombo()
   *
   * @return int
   *   The combo unique identifier id (combo_id).
   *
   * @throws InvalidArgumentException
   *   - Parameter data type provided is not an array, integer nor string.
   *   - Could not resolve to a combo_id.
   */
  protected function resolvePhenoCombo(array|int|string $combo): int {

    $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id']);

    switch (gettype($combo)) {

      case 'array':
        // Param combo is trait combo.
        $trait_combo = $combo;
        $resolved_trait_combo = $this->resolveTraitCombo($trait_combo);

        $query->condition('tbl.project_id', $this->experiment_context, '=');
        foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
          $query->condition('tbl.' . $field, $resolved_trait_combo[$alias], '=');
        }

        break;

      case 'integer':
        // Param combo is combo_id.
        $query->condition('tbl.combo_id', $combo, '=');
        break;

      case 'string':
        // Param combo is label.
        $query->condition('tbl.label', trim($combo), '=');
        break;

      default:
        throw new \InvalidArgumentException(
          sprintf(
            'The pheno combo array provided to %s, can only be array, string or integer value.',
            __METHOD__
          )
        );
    }

    $combo_id = $query
      ->range(0, 1)->execute()->fetchField();

    if ($combo_id === FALSE) {
      throw new \InvalidArgumentException(
        'Invalid pheno combo error in ' . __METHOD__ . ': pheno combo could not be resolved.'
      );
    }

    return $combo_id;
  }

  /**
   * Verify label is unique within an experiment (case insensitive check).
   *
   * @param string $label
   *   A human-readable text label used as an alternative reference to the trait
   *   name. Labels must be unique within an experiment.
   *
   * @throws \InvalidArgumentException
   *   - Label is an empty string.
   *
   * @return bool
   *   TRUE if the label is UNIQUE within the experiment and FALSE, otherwise.
   */
  protected function labelIsUniqueInExperiment(string $label): bool {

    $label = trim($label);

    if ($label === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid label error in %s: received a label that is an empty string. Value must be a string and not empty.',
          __METHOD__
        )
      );
    }

    $label_count = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->where('LOWER(tbl.label) = :lowercase_label', [':lowercase_label' => strtolower($label)])
      ->countQuery()
      ->execute()
      ->fetchField();

    return $label_count ? FALSE : TRUE;
  }

}
