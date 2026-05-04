<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Experiment - Phenotypic Combo Service.
 *
 * This service handles all processes linking an experiment with an existing
 * Phenotypic Combo (i.e. Trait - Method - Unit Combo) and setting status flags
 * relating to that link.
 *
 * Definitions:
 * - PhenoCombo refers to the combination of trait, method, and unit which
 *   uniquely identify a phenotypic measurement. These combinations are stored
 *   in Chado and managed by the Trait service.
 *
 *   @see Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
 *
 * - Experiment refers to a Tripal Content type defined by the TripalCultivate
 *   base module which stores its data as a record in the Chado project table.
 *
 *   @see 'research_experiment' content type in TripalCultivate/config/install/
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
   *   - If experiment does not resolve to an existing project_id/name.
   *   - If experiment is not configured with a genus in Phenotypes module.
   */
  public function setExperiment(int|string $experiment): void {

    // Verify the existence of the experiment.
    if (is_numeric($experiment)) {
      $experiment_id = ChadoProjectAutocompleteController::getProjectName((int) $experiment) ? $experiment : 0;
    }

    if (is_string($experiment)) {
      $experiment_id = ChadoProjectAutocompleteController::getProjectId($experiment);
    }

    if ($experiment_id === 0 || $experiment_id === '') {
      throw new \InvalidArgumentException(
        'Failed to set experiment context. Experiment name/id: ' . $experiment . ' does not exist.'
      );
    }

    // Verify phenotypes module hosted and experiment have configured genus.
    if (empty($this->service_PhenoGenusOntology->getConfiguredGenusList()) &&
        empty($this->service_PhenoGenusProject->getGenusOfProject($experiment_id))) {

      $this->tripal_logger->error(
        $failed_error = 'Failed to set experiment context. Experiment name/id: ' . $experiment . ' is not configured with a genus.'
      );

      throw new \InvalidArgumentException($failed_error);
    }

    $this->experiment_context = $experiment_id;
  }

  /**
   * Assign a trait-method-unit combo to an experiment.
   *
   * @param array $trait_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   This MUST ALREADY EXISTS in Chado. The following keys are expected:
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
   *   The inserted combo_id.
   *
   * @throws \InvalidArgumentException
   *   - If combo details is missing a label key or if label is an empty string.
   *   - If the trait combo already exists with in the experiment.
   */
  public function assignPhenoComboToExperiment(array $trait_combo, array $combo_experiment_details): int {

    $this->ensureExperimentIsSet();
    $sanitized_trait_combo = $this->sanitizeTraitCombo($trait_combo);
    $sanitized_combo_details = $this->sanitizePhenoComboDetails($combo_experiment_details);

    // Metadata fields.
    $field_metadata = [
      'project_id' => $this->experiment_context,
      'uid' => $this->current_user->id(),
      'label' => $sanitized_combo_details['label'],
      'timestamp' => time(),
    ];

    // Status flags: fill in missing status flag and set to default to 0, if not
    // provided in the details array.
    $field_status_flags = [];
    foreach (array_values(self::PHENO_COMBO_STATUS_FLAG_MAP) as $field) {
      $field_status_flags[$field] = $sanitized_combo_details[$field] ?? 0;
    }

    // Trait combo fields.
    $field_combo_ids = [];
    $combo_count_query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl');

    foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
      $field_combo_ids[$field] = $sanitized_trait_combo[$alias];
      $combo_count_query->condition('tbl.' . $field, $field_combo_ids[$field], '=');
    }

    // Ensure trait combo, independent of the label, is unique in an experiment.
    $count_query_result = $combo_count_query
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($count_query_result) {
      throw new \InvalidArgumentException(
        'Failed to assign trait combo. The trait combo is already in use in the experiment.'
      );
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $combo_id = $this->drupaldb_connection
        ->insert(self::PHENO_COMBO_TABLE)
        ->fields($field_metadata + $field_status_flags + $field_combo_ids)
        ->execute();
    }
    catch (\Exception $e) {
      $db_transaction->rollBack();

      $this->tripal_logger->error($e->getMessage());
      throw new \Exception($e->getMessage());
    }

    return (int) $combo_id;
  }

  /**
   * Get a single specific pheno trait combo in an experiment.
   *
   * @param array|int|string|null $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment.
   *   - label (string) uniquely identifying this pheno combo in experiment.
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::assignPhenoComboToExperiment()
   *   - null (default) will return a single pheno combo in an experiment. Use
   *     this value to inspect if an experiment has phenotypes (1 pheno combo
   *     row would suffice to confirm a phenotype).
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
   */
  public function getExperimentPhenoCombo(array|int|string|null $combo = NULL): array {

    $this->ensureExperimentIsSet();

    $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl');

    if (is_null($combo)) {
      $pheno_combo = $query
        ->condition('tbl.project_id', $this->experiment_context, '=')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      return $pheno_combo === FALSE ? [] : $pheno_combo;
    }

    $combo_id = $this->resolvePhenoCombo($combo);
    $pheno_combo = $query->fields('tbl')
      ->condition('tbl.combo_id', $combo_id, '=')
      ->execute()
      ->fetchAssoc();

    return $pheno_combo === FALSE ? [] : $pheno_combo;
  }

  /**
   * Ensures that experiment context has been set.
   *
   * @throws Exception
   *   - If performing combo operation where experiment context is required but
   *     is not set prior to use.
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
   * Sanitize trait combo array.
   *
   * @param array $trait_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::assignPhenoComboToExperiment()
   *
   * @return array
   *   A validated, sanitized, and normalized trait combo key where any or all
   *   names are converted to their cvterm ids, and the final trait combo uses
   *   cvterm id number (NOT THE CVTERM RECORD).
   *   ie. [trait => TRAIT ID, method => METHOD ID, unit => UNIT ID].
   *
   * @throws InvalidArgumentException
   *   - If host site with Phenotypes module has no genus configured.
   *   - If trait combo is missing any of the keys - [trait, method, unit].
   *   - If a value of a combo key is not an integer nor a string.
   */
  public function sanitizeTraitCombo(array $trait_combo): array {

    // Phenotypes module hosted has no genus configured.
    $exp_phenogenus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    if ($exp_phenogenus === []) {
      throw new \InvalidArgumentException(
        'Failed to sanitize trait combo. The Phenotypes module is not configured with genus.'
      );
    }

    // Trait combo is missing an alias.
    $trait_combo_alias = array_keys(self::TRAIT_COMBO_KEY_MAP);
    if (array_diff($trait_combo_alias, $input_keys = array_keys($trait_combo))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to sanitize trait combo. Trait combo must have keys [%s]. You provided [%s].',
          implode(', ', $trait_combo_alias),
          implode(', ', $input_keys)
        )
      );
    }

    // Create a sanitized trait combo array and detect any unexpected data type.
    $sanitized_trait_combo = [];
    $unexpected_values = [];

    foreach ($trait_combo_alias as $alias) {
      if (!is_int($trait_combo[$alias]) && !is_string($trait_combo[$alias])) {
        array_push($unexpected_values, $alias);
      }

      $sanitized_trait_combo[$alias] = $trait_combo[$alias];
    }

    if (count($unexpected_values) > 0) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to sanitize trait combo. Trait combo has invalid data type in key(s) - [%s]. Use integer or string value.',
          implode(', ', $unexpected_values)
        )
      );
    }

    // Verify trait combo exists.
    $pheno_combo = NULL;

    foreach ($trait_combo_alias as $alias) {
      ${$alias} = $sanitized_trait_combo[$alias];
    }

    foreach ($exp_phenogenus as $genus) {
      $this->service_PhenoTraits->setTraitGenus($genus);

      try {
        if (($pheno_combo = $this->service_PhenoTraits->getTraitMethodUnitCombo($trait, $method, $unit)) !== NULL) {
          break;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    if (is_null($pheno_combo)) {
      throw new \InvalidArgumentException(
        'Failed to sanitize trait combo. The trait combo does not exist.'
      );
    }

    foreach ($trait_combo_alias as $alias) {
      $sanitized_trait_combo[$alias] = $pheno_combo[$alias]->cvterm_id;
    }

    return $sanitized_trait_combo;
  }

  /**
   * Sanitize pheno combo details array.
   *
   * @param array $details
   *   An array of combo status flags and combo label to validate. The following
   *   keys are supported:
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   *   - is_archived: indicates trait combo is archived.
   *   - is_required: indicates trait combo is a required trait and must contain
   *      a value in the data file for this column.
   *   - was_shared: indicates trait combo was used in Phenotypes Share module.
   *   - was_collected: indicates trait measured in Phenotypes Collect module.
   *
   * @return array
   *   A validated, sanitized, and normalized pheno combo details where status
   *   flag values are guaranteed 0 or 1 and label trimmed and is unique value
   *   within experiment context.
   *
   * @throws InvalidArgumentException
   */
  public function sanitizePhenoComboDetails(array $details): array {

    if ($details === []) {
      return [];
    }

    $label = trim($details['label'] ?? '');
    if (empty($label) || !$this->labelIsUniqueInExperiment($label)) {
      throw new \InvalidArgumentException(
        'Failed to sanitize pheno-combo label detail. Label must exists, unique with in the experiment, and must not be an empty string.'
      );
    }

    $sanitized_combo_detais = [];
    $sanitized_combo_detais['label'] = $label;

    $unexpected_values = [];
    foreach (self::PHENO_COMBO_STATUS_FLAG_MAP as $field) {
      if (isset($details[$field])) {
        $status_flag_val = $details[$field];

        if ($status_flag_val != 0 && $status_flag_val != 1) {
          array_push($unexpected_values, $field);
        }

        $sanitized_combo_detais[$field] = $status_flag_val;
      }
    }

    if ($unexpected_values != []) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to sanitize pheno-combo details. The following status flag(s) contains invalid value in key(s) [%s].',
          implode(', ', $unexpected_values)
        )
      );
    }

    return $sanitized_combo_detais;
  }

  /**
   * Resolve pheno combo parameter values to experiment pheno combo id.
   *
   * @param array|int|string $combo
   *   The trait-method-unit pheno combo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::getExperimentPhenoCombo()
   *
   * @return int
   *   The combo unique identifier id (combo_id).
   *
   * @throws InvalidArgumentException
   *   - If pheno combo as integer combo id and number equal or less than 0.
   *   - If pheno combo as string label and value is an empty string.
   *   - If trait combo, combo id or label failed to resolved to a combo id.
   */
  protected function resolvePhenoCombo(array|int|string $combo): int {

    $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id']);

    if (is_array($combo)) {
      $trait_combo = $this->sanitizeTraitCombo($combo);

      $this->ensureExperimentIsSet();

      $query->condition('tbl.project_id', $this->experiment_context, '=');
      foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
        $query->condition('tbl.' . $field, $trait_combo[$alias], '=');
      }
    }
    elseif (is_numeric($combo)) {
      $combo_id = $combo;

      if ($combo_id <= 0) {
        throw new \InvalidArgumentException(
          'Failed to resolve combo id. The combo id must be a number greater than 0.'
        );
      }

      $query->condition('tbl.combo_id', $combo_id, '=');
    }
    elseif (is_string($combo)) {
      $label = trim($combo);

      if ($label === '') {
        throw new \InvalidArgumentException(
          'Failed to resolve combo label. The label must be a string value and not empty.'
        );
      }

      $query->condition('tbl.label', $label, '=');
    }

    $combo_id = $query->range(0, 1)->execute()->fetchField();
    if ($combo_id === FALSE) {
      throw new \InvalidArgumentException(
        'Failed to resolve combo. The trait combo, combo id, or combo label does not exist.' . $query
      );
    }

    return (int) $combo_id;
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

    $label_count = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->where('LOWER(tbl.label) = :lowercase_label', [':lowercase_label' => strtolower($label)])
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $label_count ? FALSE : TRUE;
  }

}
