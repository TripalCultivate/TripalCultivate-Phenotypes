<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tripal\Entity\TripalEntity;
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
class ExperimentPhenoComboService {

  /**
   * Experiment context.
   *
   * Operations to read/write will be restricted to this experiment context.
   * The value assigned is the resolved project_id of the experiment.
   *
   * @var int|null
   */
  protected int|null $experiment_context = NULL;

  /**
   * The table name that contains experiment pheno combos.
   *
   * @var string
   */
  public const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * PhenoCombo status flags alias to combo status flag table field mapping.
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
   * PhenoCombo alias to combo table field (FK ids) mapping.
   *
   * @var array
   */
  public const PHENO_COMBO_MAP = [
    'trait' => 'attr_id',
    'method' => 'observable_id',
    'unit' => 'unit_id',
  ];

  /**
   * ExperimentPhenoComboService service constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user session service.
   * @param \Drupal\Core\Database\Connection $drupaldb_connection
   *   Drupal database connection.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService $service_PhenoTerms
   *   TripalCultivate Phenotypes Terms service.
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
  }

  /**
   * Set the experiment context.
   *
   * This context is used to restrict all read/write operations so that only
   * records belonging to the specified experiment context can be accessed,
   * retrieved or modified.
   *
   * @param \Drupal\tripal\Entity\TripalEntity|int|string $experiment
   *   The experiment identifier, either:
   *   - Experiment entity (Tripal Entity) the experiment entity object.
   *   - An integer (int) value corresponding to the project_id number.
   *   - A string (string) value corresponding to the project name.
   *   Both int and string forms reference a field from the same Chado
   *   'projects' table.
   *
   * @throws Exception
   *   - If Phenotypes module hosted has not been configured with a genus.
   *   - If experiment is not configured with a genus in Phenotypes module.
   */
  public function setExperiment(TripalEntity|int|string $experiment): void {

    // The hosted phenotypes module has not been configured with a genus.
    if (empty($this->service_PhenoGenusOntology->getConfiguredGenusList())) {
      throw new \Exception(
        sprintf(
          'The Phenotypes module is not configured with a genus. Please navigate to %s to configure a genus.',
          Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()
        )
      );
    }

    $project_id = self::resolveExperimentToProjectId($experiment);

    // Experiment is not paired with a configured genus.
    if (empty($this->service_PhenoGenusProject->getGenusOfProject($project_id))) {
      $this->tripal_logger->error(
        $failed_error = sprintf(
          'Failed to set experiment context. The specified experiment entity/id/name: %s is not configured with a genus.',
          (($experiment instanceof TripalEntity) ? $experiment->label() : (string) $experiment)
        )
      );
      throw new \InvalidArgumentException($failed_error);
    }

    $this->experiment_context = $project_id;
  }

  /**
   * Assign a PhenoCombo to an experiment.
   *
   * @param array $pheno_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   This MUST ALREADY EXISTS in Chado. The following keys are expected:
   *   - 'trait' (integer|string): a string value is the trait name, whereas an
   *     integer value is the trait id (a value to phenotype.attr_id).
   *   - 'method' (integer|string): a string value is the method name, whereas
   *     an integer value is the method id (a value to phenotype.observable_id).
   *   - 'unit' (integer|string): a string value is the trait unit, whereas an
   *     integer value is the unit id (a value to phenotype.unit_id).
   * @param array $experiment_pheno_combo_details
   *   An associative array describing the label and status flags to assign to
   *   to this PhenoCombo within an experiment.
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
   *   - 'was_shared': 0 (no, not used in Shared Module to start with).
   *   - 'was_collected': 0 (no, not used in Collect Module to start with).
   *
   * @return int
   *   The inserted PhenoCombo combo_id.
   *
   * @throws \InvalidArgumentException
   *   - If exp. combo details is missing the required label key.
   *   - If the PhenoCombo already exists within the experiment.
   */
  public function assignPhenoComboToExperiment(array $pheno_combo, array $experiment_pheno_combo_details): int {

    $this->ensureExperimentIsSet();

    if (!isset($experiment_pheno_combo_details['label'])) {
      throw new \InvalidArgumentException(
        'Failed to assign PhenoCombo. The key \'label\' must exist in the \'$experiment_pheno_combo_details\' parameter.'
      );
    }

    $sanitized_pheno_combo = $this->sanitizePhenoCombo($pheno_combo);
    $sanitized_experiment_pheno_combo_details = $this->sanitizePhenoComboDetails($experiment_pheno_combo_details);

    // Metadata fields.
    $field_metadata = [
      'project_id' => $this->experiment_context,
      'uid' => $this->current_user->id(),
      'label' => $sanitized_experiment_pheno_combo_details['label'],
      'timestamp' => time(),
    ];

    // Status flag fields: fill in missing status flag and set to default to 0,
    // if not provided in the details array.
    $field_status_flags = [];
    foreach (self::PHENO_COMBO_STATUS_FLAG_MAP as $field) {
      $field_status_flags[$field] = $sanitized_experiment_pheno_combo_details[$field] ?? 0;
    }

    // Experiment PhenoCombo fields.
    $field_pheno_combo_ids = [];
    $pheno_combo_count_query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl');

    foreach (self::PHENO_COMBO_MAP as $alias => $field) {
      $field_combo_ids[$field] = $sanitized_pheno_combo[$alias];
      $pheno_combo_count_query->condition('tbl.' . $field, $field_pheno_combo_ids[$field], '=');
    }

    // Ensure experiment pheno combo, independent of the label, is unique within
    // an experiment context.
    $count_query_result = $pheno_combo_count_query
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($count_query_result) {
      throw new \InvalidArgumentException(
        'Failed to assign PhenoCombo. The PhenoCombo is already in use in the experiment.'
      );
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $combo_id = $this->drupaldb_connection
        ->insert(self::PHENO_COMBO_TABLE)
        ->fields($field_metadata + $field_status_flags + $field_pheno_combo_ids)
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
   * Get a single specific PhenoCombo in an experiment.
   *
   * @param array|int|string $combo
   *   The experiment PhenoCombo previously assigned to the experiment.
   *   - pheno_combo (array) indicating the trait-method-unit combo.
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment.
   *   - label (string) uniquely identifying this pheno combo in experiment.
   *     @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::assignPhenoComboToExperiment()
   *
   * @return array
   *   An associative array describing the experiment PhenoCombo including the
   *   combo_id, project_id, attr_id, observable_id, unit_id, uid, label,
   *   is_archived, is_required, was_collected, was_shared, and timestamp
   *   from trpcultivate_phenocombo table. Furthermore, 'trait', 'method' and
   *   'unit' are the cvterm names referenced by the 'attr_id', 'observable_id',
   *   and 'unit_id' respectively.
   *   For example, a trait cvterm (id:123, name: 'plant height') and
   *   attr_id = 123 then trait = 'plant height'. An empty array if not found.
   *   @see trpcultivate_phenotypes_schema()
   *
   * @throws InvalidArgumentException
   *   - If experiment context has 0 experiment PhenoCombos.
   */
  public function getExperimentPhenoCombo(array|int|string $combo): array {

    $this->ensureExperimentIsSet();

    if (!self::experimentHasPhenoCombo($this->experiment_context)) {
      throw new \InvalidArgumentException('Experiment context has no experiment PhenoCombos.');
    }

    $combo_id = $this->resolvePhenoCombo($combo);

    $pheno_combo = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.combo_id', $combo_id, '=')
      ->execute()
      ->fetchAssoc();

    return $pheno_combo === FALSE ? [] : $pheno_combo;
  }

  /**
   * Get all PhenoCombos of an experiment.
   *
   * @param string|null $genus
   *   The genus to further filter the experiment PhenoCombo results.
   * @param array $options
   *   Options to customize the query result by restricting the fields returned.
   *   The follolwing options are supported:
   *     - format: one of 'full' (default), 'component', or 'header' depending
   *       on the format of the return value desired. See the return value
   *       below for more details.
   *
   * @return array
   *   All PhenoCombos associated to an experiment (plus genus) in an array
   *   keyed by the combo label text or an empty array if no experiment
   *   PhenoCombos are found. Each item in the return value defines a single
   *   trait according to the format chosen in 'options'. Specifically,
   *
   *   $options = ['format' => 'full' | 'component' | 'header']
   *
   *   - full:
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::getExperimentPhenoCombo()
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
   * @throws \InvalidArgumentException
   *   - If experiment context has 0 experiment PhenoCombos.
   *   - If a genus is not a configured genus of the experiment.
   *   - If not a valid options key provided in $options parameter.
   *   - If not a valid trait format value provided in the $options parameter.
   */
  public function getAllExperimentPhenoCombos(string|null $genus = NULL, array $options = []):array {

    $this->ensureExperimentIsSet();

    if (!self::experimentHasPhenoCombo($this->experiment_context)) {
      throw new \InvalidArgumentException('Experiment context has no experiment PhenoCombos.');
    }

    if ($genus !== NULL && $genus !== '') {
      $pheno_configgenus = $this->service_PhenoGenusProject->getGenusOfProject($this->experiment_context);
      if (!in_array($genus, $pheno_configgenus)) {
        throw new \InvalidArgumentException(
          'Failed to get experiment pheno combos. The genus provided is not a configured genus of the experiment.'
        );
      }
    }

    $valid_option_keys = ['format'];
    if (!$options !== [] && $option_diff = array_diff(array_keys($options), $valid_option_keys)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to get experiment pheno combos. Unsupported options key provided [%s]. Only keys [%s] are allowed.',
          implode(', ', $option_diff),
          implode(', ', $valid_option_keys)
        )
      );
    }

    // If format is not specified, default to full, otherwise ensure that it can
    // only be full, component or header.
    $valid_option_format_values = ['component', 'header', 'full'];
    $use_format = strtolower($options['format'] ?? array_last($valid_option_format_values));

    if (!in_array($use_format, $valid_option_format_values)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to get experiment pheno combos. The option format value provided %s is not a valid format. Only values [%s] are allowed.',
          $use_format,
          implode(', ', $combo_format_keys),
        )
      );
    }

    $query = $this->chado_connection->select(self::PHENO_COMBO_TABLE, 'combo')
      ->fields('combo');

    foreach (self::PHENO_COMBO_MAP as $alias => $field) {
      $query->leftJoin('1:cvterm', $alias, "combo.$field = $alias.cvterm_id");

      // Resolve the attr_id, observable_id and unit_id to the cvterm record as
      // as single JSON line.
      $query->addExpression("row_to_json($alias)", $alias);

      // Component and full require additional trait metadata - unit type.
      if ($use_format != 'header' && $alias == 'unit') {
        $type_id = $this->service_PhenoTerms->getTermId($type = 'unit_type');
        $query->leftJoin('1:cvtermprop', $type, "$alias.cvterm_id = $type.cvterm_id AND $type.type_id = $type_id");
        $query->addField($type, 'value', $type);
      }
    }

    if ($genus) {
      $genus_config = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);
      $query->condition('trait.cv_id', $genus_config['trait'], '=');
    }

    $exp_phenocombo = $query
      ->condition('combo.project_id', $this->experiment_context, '=')
      ->orderBy('trait.name', 'ASC')
      ->execute()
      ->fetchAllAssoc('combo_id');

    // In the final pheno combo array, each formatted item is keyed by label.
    $formatted_combos = [];

    foreach ($exp_phenocombo as $combo_details) {
      $label = $combo_details->label;

      // Prepare vars $trait, $method and $unit containing cvterm records.
      foreach (self::PHENO_COMBO_MAP as $alias => $_) {
        ${$alias} = json_decode($combo_details->{$alias});
      }

      switch ($use_format) {

        case 'component':
          $formatted_combos[$label] = [
            'combo_id' => $combo_details->combo_id,
            'name' => $trait->name,
            'definition' => $trait->definition,
            'multiselect_method' => FALSE,
            'method_unit_combo' => [
              'method_shortname' => $method->name,
              'unit' => $unit->name,
              'type' => $combo_details->unit_type,
              'collection_method' => $method->definition,
            ],
          ];

          break;

        case 'header':
          $formatted_combos[$label] = [
            'combo_id' => $combo_details->combo_id,
            'name' => $trait->name,
            'description' => $trait->definition,
            'type' => $combo_details->is_required == 1 ? 'Required' : 'Optional',
          ];

          break;

        case 'full':
          $formatted_combos[$label] = $combo_details;

          // Expand trait, method, and unit keys to full cvterm records.
          foreach (self::PHENO_COMBO_MAP as $alias => $_) {
            $formatted_combos[$label]->{$alias} = ${$alias};
          }

          break;
      }
    }

    return $formatted_combos;
  }

  /**
   * Set experiment PhenoCombo status flags.
   *
   * @param array|int|string $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   The following formats are supported:
   *   - pheno_combo (array) indicating the trait-method-unit combo.
   *     @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::assignPhenoComboToExperiment()
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment
   *     combo as returned when assigning / getting experiment trait-method-unit
   *     associations.
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   * @param array $status_flags
   *   The status array key-value pair where the value is 0 or 1
   *   (yes or no, respectively) and the keys are the following status flags.
   *   - is_archived: indicates experiment PhenoCombo is archived.
   *   - is_required: indicates experiment PhenoCombo is a required trait and
   *     must contain a value in the data file for this column.
   *   - was_shared: indicates experiment PhenoCombo was used in
   *     Phenotypes Share module.
   *   - was_collected: indicates experiment PhenoCombo was used in
   *     Phenotypes Collect module.
   */
  public function setExperimentPhenoComboStatus(array|int|string $combo, array $status_flags): void {

    $this->ensureExperimentIsSet();

    $combo_id = $this->resolvePhenoCombo($combo);
    $sanitized_status_flags = $this->sanitizePhenoComboDetails($status_flags);

    // Nothing to set if not one status was provided.
    if ($sanitized_status_flags === []) {
      return;
    }

    // Missing status flags are not filled in.
    $field_status_flags = [];
    foreach ($sanitized_status_flags as $status_flag => $flag_value) {
      if (in_array($status_flag, self::PHENO_COMBO_STATUS_FLAG_MAP)) {
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
   * Remove a PhenoCombo from an experiment.
   *
   * NOTE: this does not delete the trait, method, and unit cvterm records.
   *
   * @param array|int|string $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::setExperimentPhenoComboStatus()
   *
   * @throws InvalidArgumentException
   *   - If the PhenoCombo has associated phenotypic records.
   */
  public function removePhenoComboFromExperiment(array|int|string $combo): void {

    $this->ensureExperimentIsSet();

    $combo_id = $this->resolvePhenoCombo($combo);

    // Ensure that this exp. PhenoCombo has no Phenotypes associated to it in
    // Chado phenotype table.
    $combo = $this->getExperimentPhenoCombo($combo_id);

    $query = $this->chado_connection->select('1:phenotype', 'tbl');
    if ($this->chado_connection->schema()->fieldExists('phenotype', 'project_id')) {
      $query->condition('tbl.project_id', $this->experiment_context, '=');
    }

    $combo_phenotypes_count = $query
      ->condition('tbl.attr_id', $combo['attr_id'], '=')
      ->condition('tbl.observable_id', $combo['observable_id'], '=')
      ->condition('tbl.assay_id', $combo['unit_id'], '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($combo_phenotypes_count) {
      throw new \InvalidArgumentException(
        'Failed to remove experiment PhenoCombo. The PhenoCombo has associated phenotypic records.'
      );
    }

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
   * Sanitize PhenoCombo array.
   *
   * @param array $pheno_combo
   *   An associative array describing the TRAIT-METHOD-UNIT combination.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::assignPhenoComboToExperiment()
   *
   * @return array
   *   A validated, sanitized, and normalized PhenoCombo key where any or all
   *   names are converted to their cvterm ids, and the final PhenoCombo uses
   *   cvterm id number (NOT THE CVTERM RECORD).
   *   ie. [trait => TRAIT ID, method => METHOD ID, unit => UNIT ID].
   *
   * @throws InvalidArgumentException
   *   - If host site with Phenotypes module has no genus configured.
   *   - If PhenoCombo is missing any of the keys - [trait, method, unit].
   *   - If a value of a combo key is neither integer nor a string.
   */
  public function sanitizePhenoCombo(array $pheno_combo): array {

    // Phenotypes module hosted has no genus configured.
    $exp_phenogenus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    if ($exp_phenogenus === []) {
      throw new \Exception(
        sprintf(
          'The Phenotypes module is not configured with a genus. Please navigate to %s to configure a genus.',
          Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()
        )
      );
    }

    // PhenoCombo is missing an alias.
    $pheno_combo_alias = array_keys(self::PHENO_COMBO_MAP);
    if (array_diff($pheno_combo_alias, $input_keys = array_keys($pheno_combo))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to sanitize PhenoCombo. PhenoCombo must have keys [%s]. You provided [%s].',
          implode(', ', $pheno_combo_alias),
          implode(', ', $input_keys)
        )
      );
    }

    // Create a sanitized PhenoCombo array and detect any unexpected data type.
    $sanitized_pheno_combo = [];
    $unexpected_values = [];

    foreach ($pheno_combo_alias as $alias) {
      if (!is_int($pheno_combo[$alias]) && !is_string($pheno_combo[$alias])) {
        array_push($unexpected_values, $alias);
      }

      $sanitized_pheno_combo[$alias] = $pheno_combo[$alias];
    }

    if (count($unexpected_values) > 0) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to sanitize PhenoCombo. PhenoCombo has invalid data type in key(s) - [%s]. Use integer or string value.',
          implode(', ', $unexpected_values)
        )
      );
    }

    // Verify PhenoCombo exists.
    $pheno_combo = NULL;

    foreach ($pheno_combo_alias as $alias) {
      ${$alias} = $sanitized_pheno_combo[$alias];
    }

    // Find the combo in each config-genus.
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
        'Failed to sanitize PhenoCombo. The PhenoCombo does not exist.'
      );
    }

    // Return resolved cvterm id, regardless of the PhenoCombo value provided.
    foreach ($pheno_combo_alias as $alias) {
      $sanitized_pheno_combo[$alias] = $pheno_combo[$alias]->cvterm_id;
    }

    return $sanitized_pheno_combo;
  }

  /**
   * Validates and sanitizes experiment PhenoCombo details.
   *
   * @param array $experiment_pheno_combo_details
   *   An associative array containing label and combo status flags. Any of the
   *   following optional keys may be provided and will be validated/sanitized:
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   *   - is_archived: indicates experiment PhenoCombo is archived.
   *   - is_required: indicates experiment PhenoCombo is a required trait and must contain
   *      a value in the data file for this column.
   *   - was_shared: indicates experiment PhenoCombo was used in
   *     Phenotypes Share module.
   *   - was_collected: indicates experiment PhenoCombo was used in
   *     Phenotypes Collect module.
   *
   * @return array
   *   A validated and sanitized experiment PhenoCombo details where status flag
   *   values are guaranteed 0 or 1 value and label trimmed and is a unique
   *   label within experiment context.
   *
   * @throws InvalidArgumentException
   *   - If label is an empty string and is not unique label in the experiment.
   *   - If any of the status flag has unexpected or non-integer value.
   */
  public function sanitizePhenoComboDetails(array $experiment_pheno_combo_details): array {

    if ($experiment_pheno_combo_details === []) {
      return [];
    }

    $sanitized_experiment_pheno_combo_details = [];

    // If label is provided, ensure it is not an empty string and is a unique
    // entry within the experiment context.
    $field_label = 'label';

    if (isset($experiment_pheno_combo_details[$field_label])) {
      $label = trim($experiment_pheno_combo_details[$field_label] ?? '');

      if (empty($label) || (!empty($label) && !$this->labelIsUniqueInExperiment($label))) {
        throw new \InvalidArgumentException(
          sprintf(
            'Failed to sanitize experiment PhenoCombo \'%s\' detail. %s provided must be a unique entry within the experiment and not an empty string.',
            $field_label,
            ucfirst($field_label)
          )
        );
      }

      $sanitized_experiment_pheno_combo_details[$field_label] = $label;
    }

    // Sanitize only the status flags provided. No fill in of missing flags.
    $unexpected_values = [];
    foreach (self::PHENO_COMBO_STATUS_FLAG_MAP as $field) {
      if (isset($experiment_pheno_combo_details[$field])) {
        $status_flag_val = $experiment_pheno_combo_details[$field];

        if ($status_flag_val != 0 && $status_flag_val != 1) {
          array_push($unexpected_values, $field);
          continue;
        }

        $sanitized_experiment_pheno_combo_details[$field] = $status_flag_val;
      }
    }

    if ($unexpected_values != []) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to sanitize experiment PhenoCombo details. The following status flag(s) contains invalid value in key(s) [%s].',
          implode(', ', $unexpected_values)
        )
      );
    }

    return $sanitized_experiment_pheno_combo_details;
  }

  /**
   * Resolve PhenoCombo combo parameter values to experiment pheno combo id.
   *
   * @param array|int|string $combo
   *   The trait-method-unit pheno combo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::getExperimentPhenoCombo()
   *
   * @return int
   *   The combo unique identifier id (combo_id).
   *
   * @throws InvalidArgumentException
   *   - If combo as an integer combo id and number equal or less than 0.
   *   - If combo as a string label and value is an empty string.
   *   - Unsupported combo input type.
   *   - If pheno_combo, combo id or label failed to resolved to a combo id.
   */
  protected function resolvePhenoCombo(array|int|string $combo): int {

    $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id']);

    if (is_array($combo)) {
      $pheno_combo = $this->sanitizePhenoCombo($combo);
      foreach (self::PHENO_COMBO_MAP as $alias => $field) {
        $query->condition('tbl.' . $field, $pheno_combo[$alias], '=');
      }

      $query->condition('tbl.project_id', $this->experiment_context, '=');
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

      $query
        ->condition('tbl.label', $label, '=')
        ->condition('tbl.project_id', $this->experiment_context, '=');
    }
    else {
      throw new \InvalidArgumentException('Unsupported combo input type');
    }

    $combo_id = $query->range(0, 1)->execute()->fetchField();
    if ($combo_id === FALSE) {
      throw new \InvalidArgumentException(
        'Failed to resolve combo. The pheno combo, combo id, or combo label does not exist.'
      );
    }

    return (int) $combo_id;
  }

  /**
   * Resolve experiment to project_id.
   *
   * @param \Drupal\tripal\Entity\TripalEntity|int|string $experiment
   *   The experiment identifier.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::setExperiment()
   *
   * @return int
   *   The project id (Chado.project: project_id) of the experiment.
   *
   * @throws InvalidArgumentException
   *   - If experiment could not resolve to an existing project_id.
   */
  protected static function resolveExperimentToProjectId(TripalEntity|int|string $experiment): int {

    $project_id = 0;

    if ($experiment instanceof TripalEntity) {
      $project_id = $experiment->getBackendRecordId('chado_storage');
    }
    elseif (is_numeric($experiment)) {
      $project_id = ChadoProjectAutocompleteController::getProjectName((int) $experiment)
        ? $experiment : $project_id;
    }
    elseif (is_string($experiment)) {
      $project_id = ChadoProjectAutocompleteController::getProjectId($experiment);
    }

    if (is_null($project_id) || $project_id === 0 || $project_id === '') {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to set experiment context. The specified experiment entity/id/name: %s does not exist.',
          (($experiment instanceof TripalEntity) ? $experiment->label() : (string) $experiment)
        )
      );
    }

    return (int) $project_id;
  }

  /**
   * Verify label is unique within an experiment (case insensitive check).
   *
   * @param string $label
   *   A human-readable text label used as an alternative reference to the trait
   *   name. Labels must be unique within an experiment.
   *
   * @return bool
   *   TRUE if the label is UNIQUE within the experiment and FALSE, otherwise.
   */
  protected function labelIsUniqueInExperiment(string $label): bool {

    $label = trim($label);

    $match_label = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->where('LOWER(tbl.label) = :lowercase_label', [':lowercase_label' => strtolower($label)])
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $match_label ? FALSE : TRUE;
  }

  /**
   * Verify that an experiment has experiment PhenoCombo(s) records.
   *
   * @param \Drupal\tripal\Entity\TripalEntity|int|string $experiment
   *   The experiment identifier.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::setExperiment()
   *
   * @return bool
   *   TRUE if experiment has PhenoCombo(s). FALSE, otherwise.
   */
  public static function experimentHasPhenoCombo(TripalEntity|int|string $experiment): bool {

    $project_id = self::resolveExperimentToProjectId($experiment);

    $has_phenocombo = \Drupal::database()->select(self::PHENO_COMBO_TABLE, 'tbl')
      ->condition('tbl.project_id', $project_id, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $has_phenocombo ? TRUE : FALSE;
  }

}
