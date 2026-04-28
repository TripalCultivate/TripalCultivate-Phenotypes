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

    // Verify the existence of the experiment by fetching the project_id/name.
    $experiment_id = (is_int($experiment) && ChadoProjectAutocompleteController::getProjectName($experiment))
      ? $experiment : ChadoProjectAutocompleteController::getProjectId($experiment);

    if ($experiment_id === 0 || $experiment_id === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid experiment identifier in %s: received experiment identifier - %s, that does not exist.',
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
          'Invalid experiment identifier in %s: received experiment identifier - %s, that is not configured with a Genus.',
          __METHOD__,
          $experiment
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
    $sanitized_status_flags = $this->sanitizeStatusFlags($combo_experiment_details, $require_flag = FALSE);

    // Verify label field is provided in combo exp. details.
    $label = trim($combo_experiment_details['label'] ?? '');
    if (empty($label) || !$this->labelIsUniqueInExperiment($label)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing or duplicate label error in %s: combo label must exists, unique with in the experiment, and must not be an empty string.',
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

    // Status flags: fill in missing status flag and set to default to 0, if not
    // provided in the details array.
    $field_status_flags = [];
    foreach (array_values(self::PHENO_COMBO_STATUS_FLAG_MAP) as $field) {
      $field_status_flags[$field] = $sanitized_status_flags[$field] ?? 0;
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
        ->execute();
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
   *   The trait-method-unit combo previously assigned to the experiment.
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
   */
  public function getExperimentPhenoCombo(array|string $combo): array {

    $this->ensureExperimentIsSet();
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
   * Get trait-method-unit combinations of an experiment.
   *
   * @param string|null $genus
   *   The genus to further filter the traits combo results.
   * @param array $options
   *   Options to customize the query result by restricting the fields returned.
   *   The follolwing options are supported:
   *     - format: one of 'full' (default), 'component', or 'header' depending
   *       on the format of the return value desired. See the return value
   *       below for more details.
   *
   * @return array
   *   All traits associated to an experiment (plus genus) in an array keyed by
   *   the combo label text or an empty array if no trait combos are found. Each
   *   item in the return value defines a single trait according to the format
   *   chosen in 'options'. Specifically,
   *
   *   - full: includes combo_id, project_id, attr_id, observable_id, unit_id,
   *   uid, label, is_archived, is_required, was_collected, was_shared, and
   *   timestamp from trpcultivate_phenocombo table. Furthermore, 'trait',
   *   'method' and 'unit' are the cvterm names referenced by the 'attr_id',
   *   'observable_id', and 'unit_id' respectively.
   *   @see trpcultivate_phenotypes_schema()
   *
   *   - component: the component format is suitable of use with trait_combo
   *   component as value to the key #props.
   *   @see component/trait_combo
   *
   *   - header: the format used for data loader headers. This format includes
   *   combo_id, name (trait cvterm.name), description (trait cvterm.definition)
   *   and type(required or optional).
   *   @see TripalCultivatePhenoShareImporter::$headers
   *
   * @throws \Exception
   *   - If the genus provided is not configred in Phenotypes module.
   *   - If options is provided and does not contain supported keys.
   */
  public function getAllExperimentPhenoCombos(string|null $genus = NULL, array $options = []):array {

    $this->ensureExperimentIsSet();

    if (is_string($genus) && $genus !== '') {
      $genus_config = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);
      if (!$genus_config) {
        throw new \InvalidArgumentException(
          ' Genus not configured error in ' . __METHOD__ . ': genus is not configured in Phenotypes module.'
        );
      }
    }

    $valid_option_keys = ['format'];

    if (!options !== [] && $option_diff = array_diff(array_keys($options), $valid_option_keys)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing key(s) error in %s: method accepts options keys [%s]. You provided - [%s].',
          __METHOD__,
          implode(', ', $valid_option_keys),
          implode(', ', $option_diff)
        )
      );
    }

    // Define the key/field name of each format. Default format to - full, if
    // not specified.
    $option_format = strtolower($options['format'] ?? 'full');

    $expcombo_table_fields = ($option_format == 'full') ? $this->drupaldb_connection
      ->select('information_schema.columns', 'info')
      ->fields('info', ['column_name'])
      ->condition('info.table_name', self::PHENO_COMBO_TABLE, '=')
      ->execute()
      ->fetchCol() : [];

    $combo_format = [
      'full' => $expcombo_table_fields,
      'header' => ['combo_id', 'name', 'description', 'type'],
      'component' => ['combo_id', 'name', 'definition'],
    ];

    // Alias-field mapping array - maps actual table columns to alternative
    // names or alias used in $combo_format definitions array.
    $alias_field_mapping = [
      'description' => 'definition',
      'name' => 'label',
      'type' => 'is_required',
    ];

    if (!in_array($option_format, $combo_format_keys = array_keys($combo_format))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing key error in %s: method accepts format values [%s]. You provided - [%s]',
          __METHOD__,
          implode(', ', $combo_format_keys),
          $option_format
        )
      );
    }

    // Retrieve the trait-method-unit combos of the experiment. Each combo is
    // keyed by the unique label.
    $query = $this->chado_connection->select(self::PHENO_COMBO_TABLE, 'combo');
    $query->fields('combo');

    switch ($option_format) {

      case 'header':
        $query->leftJoin('1:cvterm', 'trait', 'combo.attr_id = trait.cvterm_id');

        break;

      case 'full':
      case 'component':
        foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
          $query->leftJoin('1:cvterm', "$alias", "combo.$field = $alias.cvterm_id");
          $query->fields("$alias");
          $query->addExpression("row_to_json($alias)", "$alias");
        }

        $unit_type = 'unit_type';
        $type_id = $this->service_PhenoTerms->getTermId($unit_type);
        $query->leftJoin('1:cvtermprop', "$unit_type", "unit.cvterm_id = $unit_type.cvterm_id AND $unit_type = $type_id");
        $query->addField("$unit_type", 'value', "$unit_type");

        break;
    }

    if ($genus) {
      $query->condition('trait.cv_id', $genus_config['trait'], '=');
    }

    $exp_phenocombo = $query
      ->condition('combo.project_id', $this->experiment_context, '=')
      ->orderBy('trait.name', 'ASC')
      ->execute()
      ->fetchAllAssoc('label');

    if ($exp_phenocombo === FALSE) {
      return [];
    }

    $pheno_combos = [];

    foreach ($exp_phenocombo as $label => $combo_details) {
      // Resolve combo format items with combo detail value.
      $resolved_values = [];

      // Alias to field names.
      foreach ($combo_format[$option_format] as $detail_item) {
        $resolved_values[$detail_item] = $combo_details->{$alias_field_mapping[$detail_item] ?? $detail_item};
      }

      switch ($option_format) {

        case 'full':
          // Append resolved trait combo - trait, method, and unit ids.
          foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
            $resolved_values[$alias] = json_decode($combo_details->${$alias})->${$field};
          }

          break;

        case 'header':
          // Translate 0 and 1 in required field to human-readable value.
          $resolved_values['type'] = $resolved_values['type'] == 1 ? 'required' : 'optional';

          break;

        case 'component':
          // Compose the component.
          $resolved_values['multiselect_method'] = FALSE;

          $method = json_decode($combo_details->method);
          $resolved_values['method_unit_combo'][] = [
            'method_shortname' => $method->name,
            'unit' => json_decode($combo_details->unit)->name,
            'type' => $combo_details->${$unit_type},
            'collection_method' => $method->definition,
          ];

          foreach (self::PHENO_COMBO_STATUS_FLAG_MAP as $alias => $field) {
            $resolved_values['status'][$alias] = $combo_details->${$field};
          }

          break;
      }

      $pheno_combo[$label] = $resolved_values;
    }

    return $pheno_combos;
  }

  /**
   * Set trait-method-unit combo status flags.
   *
   * @param array|int|string $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   The following formats are supported:
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see assignPhenoComboToExperiment()
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
    $combo_id = $this->resolvePhenoCombo($sanitized_combo);
    $sanitized_status_flags = $this->sanitizeStatusFlags($status_flags, $require_flag = TRUE);

    // Status flags: missing status flags are not filled in.
    $field_status_flags = [];
    foreach ($sanitized_status_flags as $status_flag => $flag_value) {
      if (in_array($status_flag, self::PHENO_COMBO_STATUS_FLAG_MAP, TRUE)) {
        $field_status_flags[$status_flag] = $flag_value;
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
   * Remove a trait-method-unit combo from an experiment.
   *
   * NOTE: this does not delete the trait, method, and unit records.
   *
   * @param array|int|string $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::setExperimentPhenoComboStatus()
   */
  public function removePhenoComboFromExperiment(array|int|string $combo): void {

    $this->ensureExperimentIsSet();
    $combo_id = $this->resolvePhenoCombo($sanitized_combo);

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $this->drupaldb_connection->delete(self::PHENO_COMBO_TABLE)
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
   * Determine if an experiment has PhenoCombo.
   *
   * @param int|string $experiment
   *   The experiment identifier, either:
   *   - An integer (int) value corresponding to the project_id number.
   *   - A string (string) value corresponding to the project name.
   *   Both forms reference a field from the same Chado 'projects' table.
   *
   * @return bool
   *   TRUE if experiment has pheno combo record count of at least 1, otherwise
   *   has no record.
   */
  public static function experimentHasCombo($experiment): bool {

    $experiment_id = (is_int($experiment) && ChadoProjectAutocompleteController::getProjectName($experiment))
      ? $experiment : ChadoProjectAutocompleteController::getProjectId($experiment);

    if ($experiment_id === 0 || $experiment_id === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid experiment in %s: received experiment - %s that does not exist.',
          __METHOD__,
          $experiment
        )
      );
    }

    $combo_count_query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->condition('tbl.project_id', $experiment_id, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $combo_count_query ? TRUE : FALSE;
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
   *
   * @throws InvalidArgumentException
   *   - If trait combo is missing any of the keys - [trait, method, unit].
   *   - If a value of a combo key is not an integer nor a string.
   */
  public function sanitizeTraitCombo(array $trait_combo): array {

    $sanitized_trait_combo = [];
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

    $unexpected_values = [];

    foreach ($trait_combo_keys as $alias) {
      $trait_combo_val = $trait_combo[$alias];

      if (!is_int($trait_combo_val) && !is_string($trait_combo_val)) {
        array_push($unexpected_values, $alias);
      }

      $sanitized_trait_combo[$alias] = $trait_combo_val;
    }

    if (count($unexpected_values) > 0) {
      throw new \InvalidArgumentException(
        sprintf(
          'Type error in %s: received trait combo with invalid data type for key(s) - [%s]. Use only integer or string value.',
          __METHOD__,
          implode(', ', $unexpected_values)
        )
      );
    }

    // Make trait combo-key values to always be in cvterm_id form.
    // Genus context is derived from genus the experiment is configured with.
    $exp_phenogenus = $this->service_PhenoGenusProject
      ->getGenusOfProject($this->experiment_context);

    foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $_) {
      ${$alias} = $sanitized_trait_combo[$alias];
    }

    $pheno_combo = NULL;
    foreach ($exp_phenogenus as $genus) {
      $this->service_PhenoTraits->setTraitGenus($genus);

      try {
        $pheno_combo = $this->service_PhenoTraits
          ->getTraitMethodUnitCombo($trait, $method, $unit);

        if ($pheno_combo !== NULL) {
          break;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    if (is_null($pheno_combo)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid trait combo error in %s: received trait combo that does not match a trait-method-unit record.',
          __METHOD__
        )
      );
    }

    foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $_) {
      $sanitized_trait_combo[$alias] = $pheno_combo[$alias]->cvterm_id;
    }

    return $sanitized_trait_combo;
  }

  /**
   * Sanitize combo array.
   *
   * @param array $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoTraitComboService::getExperimentPhenoCombo()
   *
   * @return array|int|string
   *   A validated, sanitized, and normalized combo where array is resolved to
   *   cvterm ids, string is trimmed, and combo id is valid and greater than 0.
   *
   * @throws InvalidArgumentException
   *   - If an integer combo that is not a number greater than zero.
   *   - If a string that is an empty string.
   *   - If combo is not the expected type.
   */
  public function sanitizeCombo(array|int|string $combo): array|int|string {

    if (is_array($combo)) {
      $sanitized_combo = $this->sanitizeTraitCombo($combo);
    }
    elseif (is_int($combo)) {
      $combo_id = $combo;

      if ($combo_id <= 0) {
        throw new \InvalidArgumentException(
          'Invalid combo id in ' . __METHOD__ . ': received an invalid combo id value. Must be integer greater than 0.'
        );
      }

      $sanitized_combo = $combo_id;
    }
    elseif (is_string($combo)) {
      $label = trim($combo);

      if ($label === '') {
        throw new \InvalidArgumentException(
          'Invalid label in ' . __METHOD__ . ': received an invalid label value. Must be a string and not empty.'
        );
      }

      $sanitized_combo = $label;
    }
    else {
      throw new \InvalidArgumentException(
        'The pheno combo array provided to ' . __METHOD__ . ', can only be array, string or integer value.'
      );
    }

    return $sanitized_combo;
  }

  /**
   * Sanitize combo status flags have the expected 0 or 1 value and must exist.
   *
   * @param array $combo_status
   *   An array of combo status flags to validate.
   * @param bool $require_flag
   *   A flag to indicate if the combo status array must at least have one flag.
   *   Default to FALSE - not one status flags must exists.
   *
   * @return array
   *   A validated, sanitized, and normalized status flags array.
   *
   * @throws InvalidArgumentException
   *   - If enfore required is TRUE and not one status flag exists with a value.
   *   - If any one or more status flags with unexpected value.
   */
  public function sanitizeStatusFlags($combo_status, bool $require_flag = FALSE): array {

    $sanitized_combo_status = [];
    $unexpected_values = [];

    foreach (array_values(self::PHENO_COMBO_STATUS_FLAG_MAP) as $field) {
      if (isset($combo_status[$field])) {
        $status_flag_val = $combo_status[$field];

        if ($status_flag_val != 0 && $status_flag_val != 1) {
          array_push($unexpected_values, $field);
        }

        $sanitized_combo_status[$field] = $status_flag_val;
      }
    }

    if ($require_flag === TRUE && $sanitized_combo_status === []) {
      throw new \InvalidArgumentException(
        'Missing status flags error in ' . __METHOD__ . ': at least one combo status flag is required.'
      );
    }

    if ($unexpected_values != []) {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid value error in %s: combo status has a unexpected value. Replace value to 0 or 1 in - [%s]',
          __METHOD__,
          implode(', ', $unexpected_values)
        )
      );
    }

    return $sanitized_combo_status;
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
   *   - If combo is not the expected type.
   *   - If combo could not resolve to a combo_id.
   */
  protected function resolvePhenoCombo(array|int|string $combo): int {

    $sanitized_combo = $this->sanitizeCombo($combo);

    $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id']);

    if (is_array($sanitized_combo)) {
      $trait_combo = $sanitized_combo;

      $query->condition('tbl.project_id', $this->experiment_context, '=');
      foreach (self::TRAIT_COMBO_KEY_MAP as $alias => $field) {
        $query->condition('tbl.' . $field, $trait_combo[$alias], '=');
      }
    }
    elseif (is_int($sanitized_combo)) {
      $combo_id = $sanitized_combo;
      $query->condition('tbl.combo_id', $combo_id, '=');
    }
    elseif (is_string($sanitized_combo)) {
      $label = $sanitized_combo;
      $query->condition('tbl.label', $label, '=');
    }
    else {
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
