<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
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
 * - PhenoCombo refers to the combination of trait, method, unit
 *   and unit which uniquely identify a phenotypic measurement. These
 *   combinations are stored in Chado and managed by the Trait service.
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
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $current_user;

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $drupaldb_connection;

  /**
   * Phenotypes Traits service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits;

  /**
   * Phenotypes Genus-Project service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService
   */
  protected TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject;

  /**
   * Phenotypes Genus-Ontology service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * Tripal logger service.
   *
   * @var \Drupal\tripal\Services\TripalLogger
   */
  protected TripalLogger $tripal_logger;

  /**
   * Experiment context.
   *
   * @var int|null
   */
  protected int|null $experiment_context;

  /**
   * The table name that contains experiment pheno trait combos.
   *
   * @var string
   */
  public const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Status flags of a pheno trait combo.
   *
   * @var array
   */
  public const PHENO_COMBO_STATUS_FLAGS = [
    'archived' => 'is_archived',
    'required' => 'is_required',
    'collected' => 'was_collected',
    'shared' => 'was_shared',
  ];

  /**
   * Pheno combo key to table field (id) mapping.
   *
   * @var array
   */
  public const PHENO_COMBO_KEYS = [
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
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   TripalCultivate Phenotypes Genus-Project service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   TripalCultivate Phenotypes Genus-Ontology service.
   * @param \Drupal\tripal\Services\TripalLogger $tripal_logger
   *   Tripal logger service.
   */
  public function __construct(
    AccountInterface $current_user,
    Connection $drupaldb_connection,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalLogger $tripal_logger,
  ) {

    $this->drupaldb_connection = $drupaldb_connection;
    $this->service_PhenoTraits = $service_PhenoTraits;

    $this->service_PhenoGenusProject = $service_PhenoGenusProject;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;

    $this->tripal_logger = $tripal_logger;

    // Begin with no experiment context to enforce context setter method.
    $this->experiment_context = NULL;
  }

  /**
   * Set the experiment context.
   *
   * This context is used to restrict all read/write operations so that only
   * records belonging to the specified experiment context can be accessed,
   * retririeved or modified.
   *
   * @param int|string $experiment
   *   The experiment identifier, either:
   *   - An integer (int) value corresponding to the project_id number.
   *   - A string (string) value corresponding to the name (project nane).
   *   Both forms reference a field from the same Chado 'projects' table.
   *
   * @throws InvalidArgumentException
   *   - Not an integer or string value (data type) $experiment parameter.
   *   - Experiment does to correspond to an row in the Chado projects table.
   *   - Experiment is not configured with a genus.
   */
  public function setExperiment(int|string $experiment): void {

    if (!in_array(gettype($experiment, $valid_types = ['integer', 'string']))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received experiment identifier with invalid data type. Value must be of type [%s].',
          __METHOD__,
          implode(', ', $valid_types)
        )
      );
    }

    // Verify the existence of the experiment by fetching the project_id/name.
    $experiment_id = (is_int($experiment) && ChadoProjectAutocompleteController::getProjectName($experiment))
      ? $experiment : ChadoProjectAutocompleteController::getProjectId($experiment);

    if ($experiment_id === 0 || $experiment_id === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid experiment identifier in %s: received experiment identifier - %s that does not exist.',
          $experiment,
          __METHOD__
        )
      );
    }

    // If phenotypes module in the host site has no genus configured, or if the
    // experiment context has not been paired with a genus.
    if (empty($this->service_PhenoGenusOntology->getConfiguredGenusList()) ||
        empty($this->service_PhenoGenusProject->getGenusOfProject($experiment_id))) {

      $this->tripal_logger->error(
        $invalid_exp_error = sprintf(
          'Invalid experiment identifier in %s: received experiment identifier - %s that is not configured with a Genus.',
          $experiment,
          gettype($experiment),
          __METHOD__
        )
      );

      throw new \InvalidArgumentException($invalid_exp_error);
    }

    $this->experiment_context = $experiment_id;
  }

  /**
   * Assign a trait-method-unit combo to an experiment.
   *
   * @param array $trait_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   This MUST ALREADY EXIST. The following keys are expected:
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
   *   - 'is_archived': 0 (no, active combo).
   *   - 'is_required': 0 (no, optional combo).
   *   - 'is_shared': 0 (no, not used in Shared Module to start with).
   *   - 'is_collected': 0 (no, not used in Collect Module to start with).
   *
   * @return int
   *   The last inserted combo_id as a result of an insert query.
   *
   * @throws \InvalidArgumentException
   *   - Not an array value (data type) pass to both parameters.
   *     defined for each parameter in the parameter definition.
   *   - Not an integer or string value (data type) passed to either parameters.
   */
  public function assignPhenoComboToExperiment(array $trait_combo, array $combo_experiment_details): int {

    $this->ensureExperimentIsSet();

    if (!is_array($trait_combo) || !is_array($combo_experiment_details)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received one or more parameters with invalid data types. All parameters must be of type [array].',
          __METHOD__
        )
      );
    }

    $this->ensureTraitComboExists($trait_combo);

    // Verify label field is provided in combo exp. details.
    $label = trim($combo_experiment_details['label']) ?? '';
    if (empty($label) || !$this->labelIsUniqueInExperiment($label)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing or duplicate label error in %s: Combo label must exists, unique withing the experiment, and must not be an empty string.',
          __METHOD__
        )
      );
    }

    // Metadata fields.
    $field_metadata = [
      'project_id' => $this->experiment_context,
      'uid' => $this->current_user->id(),
      'label' => trim($combo_experiment_details['label']),
      'timestamp' => time(),
    ];

    // Combo fields. Combo ids have been verified at this point.
    $field_combo_ids = [];
    foreach (self::PHENO_COMBO_KEYS as $key => $field) {
      $field_combo_ids[$field] = $combo[$key];
    }

    // Status flags:
    $field_status_flags = [];
    foreach (array_values(self::PHENO_COMBO_STATUS_FLAGS) as $flag) {
      $field_status_flags[$flag] = isset($combo_experiment_details[$flag])
        ? (int) ((bool) $combo_experiment_details[$flag]) : 0;
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $combo_id = $this->drupaldb_connection
        ->insert(self::PHENO_COMBO_TABLE)
        ->fields($field_metadata + $field_combo_ids + $field_status_flags)
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
   *   - label (string) uniquely identifying this pheno combo in experiment.
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see assignTraitMethodUnitComboToExperiment()
   *
   * @return array
   *   An associative array describing the experiment trait-method-unit combo
   *   including the combo_id, project_id, attr_id, observable_id, unit_id,
   *   uid, label, is_archived, is_required, was_collected, was_shared, and
   *   timestamp from trpcultivate_phenocombo table. Furthermore, 'trait',
   *   'method' and 'unit' are the cvterm names referenced by the 'attr_id',
   *   'observable_id', and 'unit_id' respectively. An empty array if not found.
   *   @see trpcultivate_phenotypes_schema()
   *
   * @throws \InvalidArgumentException
   *   - Not an array or string value (data type) $combo parameter.
   */
  public function getExperimentPhenoCombo(array|string $combo): array {

    $this->ensureExperimentIsSet();

    if (!in_array(gettype($combo, $valid_types = ['array', 'string']))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received combo with invalid data type. Value must be of type [%s].',
          __METHOD__,
          implode(', ', $valid_types)
        )
      );
    }

    $combo_id = $this->resolvePhenoCombo($combo);
    if ($combo_id !== 0) {
      $pheno_combo = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
        ->fields('tbl')
        ->condition('tbl.combo_id', $combo_id, '=')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
    }

    return $pheno_combo ?? [];
  }

  /**
   * Get the fullset of pheno trait combos in an experiment.
   *
   * @param array|null $filter
   * @param array|null $options
   */
  public function getAllExperimentPhenoCombos(null|array $filter, null|array $otions): array {

  }

  /**
   * Set trait-method-unit combo status flags.
   *
   * @param array|int|string $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   The following formats are supported:
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see assignTraitMethodUnitComboToExperiment()
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment
   *     combo as returned when assigning / getting experiment trait-method-unit
   *     associations.
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   * @param array $status_flags
   *   The status array key-value pair where the value is 0 or 1
   *   (yes or no, respectively) and the keys are the following status flags.
   *   At least one key is required in the status flags array parameter.
   *   - is_archived: indicates trait combo is archived.
   *   - is_required: indicates trait combo is a required trait and must contain
   *      a value in the data file for this column.
   *   - was_shared: indicates trait combo was used in Phenotypes Share module.
   *   - was_collected: indicates trait measured in Phenotypes Collect module.
   *
   * @throws \InvalidArgumentException
   *   - Not an array or string value (data type) $combo parameter.
   *   - Not an array value (data type) $status_flags parameter.
   *   - $status_flags array does not contain at least 1 status flag to modify.
   */
  public function setExperimentPhenoComboStatus(array|int|string $combo, array $status_flags): void {

    $this->ensureExperimentIsSet();
    $combo_id = $this->resolvePhenoCombo($combo);

    if (!in_array(gettype($combo, $valid_types = ['array', 'integer', 'string']))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received combo with invalid data type. Value must be of type [%s].',
          __METHOD__,
          implode(', ', $valid_types)
        )
      );
    }

    if (!is_array($status_flags)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received status flags with invalid data type. Value must be of type [array].',
          __METHOD__
        )
      );
    }

    // At least one status flag is requested to be modified.
    if (!array_intersect(self::PHENO_COMBO_STATUS_FLAGS, array_keys($status_flags))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing status flag key in %s: received status flags without any flags. Value must have at least 1 status flag to modify.',
          __METHOD__
        )
      );
    }

    // Status flags:
    $field_status_flags = [];
    foreach ($status_flags as $key => $entry_value) {
      if (in_array($key, PHENO_COMBO_STATUS_FLAGS)) {
        $field_status_flags[$field] = (int) ((bool) $entry_value);
      }
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $this->drupaldb_connection->update(self::PHENO_COMBO_TABLE)
        ->fields($field_status_flags)
        ->condition('combo_id', $combo_id, '=')
        ->execute();
    }
    catch (\Exception $e) {
      $db_transaction->rollBack();

      $this->tripal_logger->error($e->getMessage());
      throw new Exception($e->getMessage());
    }
  }

  /**
   * Ensures that experiment context has been set.
   *
   * @throws Exception
   *   - Performing operation where experiment context is required but not set.
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
   * Ensure that the trait combo (array with trait, method and unit) exits.
   *
   * @param array $trait_combo
   *   The trait-method-unit combination.
   *   @see assignPhenoComboToExperiment()
   *
   * @throws InvalidArgumentException
   *   - The combo array is missing required key.
   *   - The combo array value is not the expected type of integer or string.
   *   - The combo did not match a trait-method-unit combo.
   */
  protected function ensureTraitComboExists(array $trait_combo): void {

    if (!is_array($trait_combo)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received trait combo with invalid data types. Value must be of type [array].',
          __METHOD__
        )
      );
    }

    // Ensure that array has the expected keys - trait, method and unit.
    if ($option_diff = array_diff(array_keys(self::PHENO_COMBO_KEYS), array_keys($combo))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing key error in %s: received trait combo with missing key(s). Value must have keys [%s]. You provided [%s]',
          __METHOD__,
          implode(', ', self::PHENO_COMBO_KEYS),
          implode(', ', $option_diff)
        )
      );
    }

    // Ensure that value for each combo key is either integer or string type.
    $unexpected_values = [];
    foreach (self::PHENO_COMBO_KEYS as $key => $_) {
      if (!is_int($combo[$key]) && !is_string($combo[$key])) {
        $unexpected_values[] = $key;
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

    // Verify that trait-method-unit returns a combo record.
    $exp_phenogenus = $this->service_PhenoGenusProject
      ->getGenusOfProject($this->experiment_context);

    $pheno_combo = 0;
    foreach ($exp_phenogenus as $genus) {
      try {
        $this->service_PhenoTraits->setTraitGenus($genus);
        $pheno_combo = $this->service_PhenoTraits
          ->getTraitMethodUnitCombo($combo['trait'], $combo['method'], $combo['unit']);

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
  }

  /**
   * Resolve pheno combo parameter values to pheno combo id.
   *
   * @param array|int|string $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   @see setExperimentPhenoComboStatus()
   *
   * @return int|null
   *   The combo unique identifier (combo_id). NULL if combo could not be resolved.
   *
   * @throws InvalidArgumentException
   *   - The combo array is not the expected data type of array, integer or
   *     string value.
   */
  protected function resolvePhenoCombo(array|int|string $combo): int|null {

    $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id']);

    switch (gettype($combo)) {

      case 'array':
        // Param combo is trait combo.
        $this->ensureTraitComboExists($combo);

        $query->condition('tbl.project_id', $this->experiment_context, '=');
        foreach (self::PHENO_COMBO_KEYS as $key => $field) {
          $query->condition('tbl.' . $field, $combo[$key], '=');
        }

        break;

      case 'string':
        // Param combo is label.
        $query->condition('tbl.label', trim($combo), '=');
        break;

      case 'integer':
        // Param combo is combo_id.
        $query->condition('tbl.combo_id', $combo, '=');
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

    return $combo_id ?: NULL;
  }

  /**
   * Verify label is unique within an experiment (case insensitive check).
   *
   * @param string $label
   *   A human-readable text label used as an alternative reference to the trait
   *   name. Labels must be unique within an experiment.
   *
   * @throws \InvalidArgumentException
   *   An exception is thrown if
   *    - label is an empty string.
   *    - experiment did not return a project record.
   *
   * @return bool
   *   TRUE if the label is UNIQUE within the experiment and FALSE, otherwise.
   */
  protected function labelIsUniqueInExperiment(string $label): bool {

    $label_exists = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id'])
      ->addExpression('LOWER(tbl.label)', 'label_lower')
      ->condition('label_lower', strtolower($label), '=')
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $label_exists ? FALSE : TRUE;
  }

}
