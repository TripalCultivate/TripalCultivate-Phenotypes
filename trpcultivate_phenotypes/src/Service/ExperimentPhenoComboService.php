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
   * The set value of this property is the resolved project_id of the experiment
   * identifier provided.
   *
   * @var int|null
   */
  protected int|null $experiment_context = NULL;

  /**
   * The table name that contains experiment PhenoCombos.
   *
   * @var string
   */
  public const PHENOCOMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * PhenoCombo status flag alias mapped to the status flag table field.
   *
   * @see trpcultivate_phenotypes_schema()
   *
   * @var array
   */
  public const PHENOCOMBO_STATUS_FLAG_FIELD_MAP = [
    'archived' => 'is_archived',
    'required' => 'is_required',
    'collected' => 'was_collected',
    'shared' => 'was_shared',
  ];

  /**
   * PhenoCombo item alias mapped to FK id table field.
   *
   * @see trpcultivate_phenotypes_schema()
   *
   * @var array
   */
  public const PHENOCOMBO_FIELD_MAP = [
    'trait' => 'attr_id',
    'method' => 'observable_id',
    'unit' => 'unit_id',
  ];

  /**
   * ExperimentPhenoComboService service class constructor.
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
   * @throws \Exception
   *   - If Phenotypes module hosted has not been configured with a genus.
   *   - If experiment is not configured with a genus in Phenotypes module.
   */
  public function setExperiment(TripalEntity|int|string $experiment): void {

    if (empty($this->service_PhenoGenusOntology->getConfiguredGenusList())) {
      throw new \Exception(
        sprintf(
          'The Phenotypes module is not configured with a genus. Please navigate to %s to configure a genus.',
          Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()
        )
      );
    }

    $project_id = self::resolveExperimentToProjectId($experiment);

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
   *   This MUST ALREADY EXIST in Chado. The following keys are expected:
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
   *   - If experiment PhenoCombo details is missing the required 'label' key.
   *   - If the PhenoCombo already exists within the experiment.
   *   - If failed to insert a PhenoCombo (database error).
   */
  public function assignPhenoComboToExperiment(array $pheno_combo, array $experiment_pheno_combo_details): int {

    $this->ensureExperimentIsSet();

    if (!isset($experiment_pheno_combo_details['label'])) {
      throw new \InvalidArgumentException(
        'Missing label key error. The key \'label\' must exist in the \'$experiment_pheno_combo_details\' parameter.'
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

    // Status flag fields: populate any missing flags and set the value to
    // default value of 0 (no).
    $field_status_flags = [];
    foreach (self::PHENOCOMBO_STATUS_FLAG_FIELD_MAP as $field) {
      $field_status_flags[$field] = $sanitized_experiment_pheno_combo_details[$field] ?? 0;
    }

    // PhenoCombo fields.
    $field_pheno_combo = [];

    // Query to ensure experiment PhenoCombo, independent of its label, is
    // unique within an experiment context.
    $pheno_combo_count_query = $this->drupaldb_connection->select(self::PHENOCOMBO_TABLE, 'tbl');
    foreach (self::PHENOCOMBO_FIELD_MAP as $alias => $field) {
      $pheno_combo_count_query
        ->condition('tbl.' . $field, $field_pheno_combo[$field] = $sanitized_pheno_combo[$alias], '=');
    }

    if ($pheno_combo_count_query
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField()) {

      throw new \InvalidArgumentException(
        'Duplicate PhenoCombo error. The PhenoCombo is already in use in the experiment.'
      );
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $combo_id = $this->drupaldb_connection
        ->insert(self::PHENOCOMBO_TABLE)
        ->fields($field_metadata + $field_status_flags + $field_pheno_combo)
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
   * Get a single specific PhenoCombo in an experiment context.
   *
   * @param array|int|string $combo
   *   The experiment PhenoCombo previously assigned to the experiment.
   *   The following formats are supported:
   *   - pheno_combo (array) indicating the trait-method-unit combinations.
   *     @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::assignPhenoComboToExperiment()
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment
   *     combo as returned when assigning / getting experiment trait-method-unit
   *     associations.
   *   - label (string) uniquely identifying this pheno combo in the
   *     experiment indicated.
   *
   * @return \stdClass|null
   *   An associative array describing the experiment PhenoCombo including the
   *   combo_id, project_id, attr_id, observable_id, unit_id, uid, label,
   *   is_archived, is_required, was_collected, was_shared, and timestamp
   *   from trpcultivate_phenocombo table. Furthermore, 'trait', 'method' and
   *   'unit' are the cvterm names referenced by the 'attr_id', 'observable_id',
   *   and 'unit_id' respectively.
   *   For example, a trait cvterm (id:123, name: 'plant height') and
   *   attr_id = 123 then trait = 'plant height'. An empty array if not found.
   *   @see trpcultivate_phenotypes_schema()
   */
  public function getExperimentPhenoCombo(array|int|string $combo): \stdClass|null {

    $this->ensureExperimentIsSet();

    $combo_id = $this->resolvePhenoCombo($combo);

    $pheno_combo = $this->drupaldb_connection->select(self::PHENOCOMBO_TABLE, 'tbl')
      ->fields('tbl')
      ->condition('tbl.combo_id', $combo_id, '=')
      ->execute()
      ->fetchObject();

    return $pheno_combo === FALSE ? NULL : $pheno_combo;
  }

  /**
   * Get all experiment PhenoCombos of an experiment context.
   *
   * @param string|null $genus
   *   The genus to further filter the experiment PhenoCombo results based on
   *   genus-configuration cvterm.cv_id values for trait, method, and unit.
   * @param array $options
   *   Options to customize the query result by restricting the fields returned.
   *   The following options are supported:
   *   - 'format': one of 'full' (default), 'component', or 'header' depending
   *     on the format of the return value desired. See the return value
   *     below for more details.
   *
   * @return array|null
   *   All experiment PhenoCombos associated to an experiment (plus genus) in an
   *   associative array keyed by the combo label text. Each item in the return
   *   value defines a single PhenoCombo according to the format chosen in
   *   'options'. Specifically,
   *
   *   $options = ['format' => 'component' | 'header' | 'full' (default)]
   *
   *   - component: the component format is suitable of use with trait_combo
   *   component as value to the key #props.
   *   @see component/trait_combo
   *
   *   - header: the format used for data loader headers. This format includes
   *   combo_id, name (trait cvterm.name), description (trait cvterm.definition)
   *   and type (Required or Optional).
   *   @see TripalCultivatePhenoTraitImporter::$headers
   *
   *   - full (DEFAULT):
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::getExperimentPhenoCombo()
   *     The return value of this method.
   *
   *   Return value format will default to 'full' if not specified in the
   *   '$options' parameter.
   *
   * @throws \InvalidArgumentException
   *   - If a genus is not a configured genus of the experiment.
   *   - If an invalid valid options key is provided to the $options parameter.
   *   - If an invalid value is provided for 'format' in the $options parameter.
   */
  public function getAllExperimentPhenoCombos(string|null $genus = NULL, array $options = []):array|null {

    $this->ensureExperimentIsSet();

    if ($genus !== NULL && $genus !== '') {
      if (!in_array($genus, $this->service_PhenoGenusProject->getGenusOfProject($this->experiment_context))) {
        throw new \InvalidArgumentException(
          'Failed to get experiment PhenoCombos. The genus provided is not a configured genus of the experiment.'
        );
      }
    }

    // Acceptable options key and values.
    $valid_options = [
      'format' => ['component', 'header', 'full'],
    ];

    $valid_options_keys = array_keys($valid_options);
    $valid_options_format_values = array_values($valid_options['format']);

    if ($options !== [] && $options_diff = array_diff(array_keys($options), $valid_options_keys)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to get experiment PhenoCombos. Unsupported options key provided [%s]. Only keys [%s] are allowed.',
          implode(', ', $options_diff),
          implode(', ', $valid_options_keys)
        )
      );
    }

    // If format is not specified, default to full, otherwise ensure that it can
    // only be component, header, or full.
    $use_format = strtolower($options['format'] ?? end($valid_options_format_values));

    if (!in_array($use_format, $valid_options_format_values)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Failed to get experiment PhenoCombos. The options format value provided %s is not a valid format. Only values [%s] are allowed.',
          $use_format,
          implode(', ', $valid_options_format_values),
        )
      );
    }

    $query = $this->chado_connection->select(self::PHENOCOMBO_TABLE, 'combo')
      ->fields('combo');

    // Resolve the attr_id, observable_id and unit_id to the cvterm record as
    // as single JSON line.
    foreach (self::PHENOCOMBO_FIELD_MAP as $alias => $field) {
      $query->leftJoin('1:cvterm', $alias, "combo.$field = $alias.cvterm_id");
      $query->addExpression("row_to_json($alias)", $alias);

      // Component and full require additional trait metadata - unit type.
      if ($use_format != 'header' && $alias == 'unit') {
        $type_id = $this->service_PhenoTerms->getTermId($type = 'unit_type');
        $query->leftJoin('1:cvtermprop', $type, "$alias.cvterm_id = $type.cvterm_id AND $type.type_id = $type_id");
        $query->addField($type, 'value', $type);
      }
    }

    if ($genus) {
      $query
        ->condition('trait.cv_id', $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus)['trait'], '=');
    }

    $exp_phenocombo = $query
      ->condition('combo.project_id', $this->experiment_context, '=')
      ->orderBy('trait.name', 'ASC')
      ->execute()
      ->fetchAllAssoc('combo_id');

    // In the final PhenoCombos array, each formatted item is keyed by label.
    $formatted_pheno_combos = [];

    foreach ($exp_phenocombo as $combo_details) {
      $label = $combo_details->label;

      // Prepare vars $trait, $method and $unit containing cvterm records.
      foreach (self::PHENOCOMBO_FIELD_MAP as $alias => $_) {
        ${$alias} = json_decode($combo_details->{$alias});
      }

      switch ($use_format) {

        case 'component':
          $formatted_pheno_combos[$label] = [
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
          $formatted_pheno_combos[$label] = [
            'combo_id' => $combo_details->combo_id,
            'name' => $trait->name,
            'description' => $trait->definition,
            'type' => $combo_details->is_required == 1 ? 'Required' : 'Optional',
          ];

          break;

        case 'full':
          $formatted_pheno_combos[$label] = $combo_details;

          // Expand trait, method, and unit keys to full cvterm records.
          foreach (self::PHENOCOMBO_FIELD_MAP as $alias => $_) {
            $formatted_pheno_combos[$label]->{$alias} = ${$alias};
          }

          break;
      }
    }

    return $formatted_pheno_combos ?: NULL;
  }

  /**
   * Set PhenoCombo status flags.
   *
   * @param array|int|string $combo
   *   The experiment PhenoCombo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::getExperimentPhenoCombo()
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
   *
   * @throws \Exception
   *   - If failed to update a PhenoCombo status flag (database error).
   */
  public function setExperimentPhenoComboStatusFlags(array|int|string $combo, array $status_flags): void {

    $this->ensureExperimentIsSet();

    $combo_id = $this->resolvePhenoCombo($combo);
    $sanitized_status_flags = $this->sanitizePhenoComboDetails($status_flags);

    // Nothing to set if not one status was provided.
    if ($sanitized_status_flags === []) {
      return;
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $this->drupaldb_connection->update(self::PHENOCOMBO_TABLE)
        ->fields($sanitized_status_flags)
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
   *   The experiment PhenoCombo previously assigned to the experiment.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::getExperimentPhenoCombo()
   *
   * @throws InvalidArgumentException
   *   - If the PhenoCombo has associated phenotypic records.
   *   - If failed to remove a PhenoCombo (database error).
   */
  public function removePhenoComboFromExperiment(array|int|string $combo): void {

    $this->ensureExperimentIsSet();

    $combo_id = $this->resolvePhenoCombo($combo);

    // Ensure that this exp. PhenoCombo has no Phenotypes associated to it in
    // Chado phenotype table.
    $pheno_combo = $this->getExperimentPhenoCombo($combo_id);

    $query = $this->chado_connection->select('1:phenotype', 'tbl');
    if ($this->chado_connection->schema()->fieldExists('phenotype', 'project_id')) {
      $query->condition('tbl.project_id', $this->experiment_context, '=');
    }

    $combo_phenotypes_count = $query
      ->condition('tbl.attr_id', $pheno_combo->attr_id, '=')
      ->condition('tbl.observable_id', $pheno_combo->observable_id, '=')
      ->condition('tbl.assay_id', $pheno_combo->unit_id, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($combo_phenotypes_count) {
      throw new \InvalidArgumentException(
        'Failed to remove PhenoCombo from this experiment. The PhenoCombo has associated phenotypic records.'
      );
    }

    $db_transaction = $this->drupaldb_connection->startTransaction();
    try {
      $this->drupaldb_connection->delete(self::PHENOCOMBO_TABLE)
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
   *   A validated, sanitized, and normalized PhenoCombo where any or all
   *   names are converted to their cvterm ids, and the final PhenoCombo uses
   *   cvterm_id number (NOT THE CVTERM RECORD).
   *   ie. [trait => TRAIT ID, method => METHOD ID, unit => UNIT ID].
   *
   * @throws InvalidArgumentException
   *   - If host site with Phenotypes module has no genus configured.
   *   - If PhenoCombo is missing any of the key(s) trait, method, and unit.
   */
  public function sanitizePhenoCombo(array $pheno_combo): array {

    // Check if the Phenotypes module has genus configured.
    $config_genus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    if ($config_genus === []) {
      throw new \Exception(
        sprintf(
          'The Phenotypes module is not configured with a genus. Please navigate to %s to configure a genus.',
          Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()
        )
      );
    }

    // Check if PhenoCombo is missing an alias.
    $pheno_combo_alias = array_keys(self::PHENOCOMBO_FIELD_MAP);
    if (array_diff($pheno_combo_alias, $input_keys = array_keys($pheno_combo))) {
      throw new \InvalidArgumentException(
        sprintf(
          'Missing PhenoCombo item key error. PhenoCombo must have keys [%s]. You provided [%s].',
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
          'Unexpected PhenoCombo item value type error. PhenoCombo has invalid data type in key(s) - [%s]. Use integer or string value.',
          implode(', ', $unexpected_values)
        )
      );
    }

    // Verify trait, method and unit exist.
    $pheno_combo = NULL;

    foreach ($pheno_combo_alias as $alias) {
      ${$alias} = $sanitized_pheno_combo[$alias];
    }

    // Look for this combination in each configured genus.
    foreach ($config_genus as $genus) {
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
        'Missing PhenoCombo error. The trait-method-unit combinations does not exist.'
      );
    }

    // Return resolved cvterm id, regardless of the PhenoCombo value provided.
    foreach ($pheno_combo_alias as $alias) {
      $sanitized_pheno_combo[$alias] = $pheno_combo[$alias]->cvterm_id;
    }

    return $sanitized_pheno_combo;
  }

  /**
   * Sanitize experiment PhenoCombo details.
   *
   * @param array $experiment_pheno_combo_details
   *   An associative array containing label and combo status flags. Any of the
   *   following optional keys may be provided and will be validated:
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   *   - Status Flags
   *     @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::setExperimentPhenoComboStatus()
   *
   * @return array
   *   A validated and sanitized experiment PhenoCombo details where status flag
   *   values are guaranteed 0 or 1 value and label trimmed and is a unique
   *   label within experiment context.
   *
   * @throws \InvalidArgumentException
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
            'Invalid PhenoCombo label error. %s provided must be a unique entry within the experiment and not an empty string.',
            ucfirst($field_label)
          )
        );
      }

      $sanitized_experiment_pheno_combo_details[$field_label] = $label;
    }

    // Sanitize only the status flags provided. No fill in of missing flags.
    $unexpected_values = [];
    foreach (self::PHENOCOMBO_STATUS_FLAG_FIELD_MAP as $field) {
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
          'Unexpected PhenoCombo status flag value type error. Status flag(s) contains invalid value in status flags(s) [%s].',
          implode(', ', $unexpected_values)
        )
      );
    }

    return $sanitized_experiment_pheno_combo_details;
  }

  /**
   * Resolve combo parameter values to experiment PhenoCombo combo_id.
   *
   * @param array|int|string $combo
   *   The experiment PhenoCombo previously assigned to the experiment.
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

    $query = $this->drupaldb_connection->select(self::PHENOCOMBO_TABLE, 'tbl')
      ->fields('tbl', ['combo_id']);

    if (is_array($combo)) {
      $pheno_combo = $this->sanitizePhenoCombo($combo);

      $query->condition('tbl.project_id', $this->experiment_context, '=');
      foreach (self::PHENOCOMBO_FIELD_MAP as $alias => $field) {
        $query->condition('tbl.' . $field, $pheno_combo[$alias], '=');
      }
    }
    elseif (is_numeric($combo)) {
      $combo_id = $combo;

      if ($combo_id <= 0) {
        throw new \InvalidArgumentException(
          'Invalid combo_id error. The combo id must be a number greater than 0.'
        );
      }

      $query->condition('tbl.combo_id', $combo_id, '=');
    }
    elseif (is_string($combo)) {
      $label = trim($combo);

      if ($label === '') {
        throw new \InvalidArgumentException(
          'Invalid label error. The label must be a string value and not empty.'
        );
      }

      $query
        ->condition('tbl.project_id', $this->experiment_context, '=')
        ->condition('tbl.label', $label, '=');
    }
    else {
      throw new \InvalidArgumentException('Unsupported combo input type');
    }

    $combo_id = $query->range(0, 1)->execute()->fetchField();
    if ($combo_id === FALSE) {
      throw new \InvalidArgumentException(
        'Missing PhenoCombo error. The PhenoCombo, combo id, or combo label does not exist.'
      );
    }

    return (int) $combo_id;
  }

  /**
   * Resolve experiment context to project_id.
   *
   * @param \Drupal\tripal\Entity\TripalEntity|int|string $experiment
   *   The experiment identifier.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::setExperiment()
   *
   * @return int
   *   The project id (Chado.project: project_id) of the experiment context.
   *
   * @throws InvalidArgumentException
   *   - If experiment context could not resolve to an existing project_id.
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
          'Missing experiment error. The specified experiment entity/id/name: %s does not exist.',
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

    $match_label = $this->drupaldb_connection->select(self::PHENOCOMBO_TABLE, 'tbl')
      ->where('LOWER(tbl.label) = :lowercase_label', [':lowercase_label' => strtolower($label)])
      ->condition('tbl.project_id', $this->experiment_context, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $match_label ? FALSE : TRUE;
  }

  /**
   * Verify that an experiment has experiment PhenoCombo records.
   *
   * @param \Drupal\tripal\Entity\TripalEntity|int|string $experiment
   *   The experiment identifier.
   *   @see Drupal\trpcultivate_phenotypes\Service\ExperimentPhenoComboService::setExperiment()
   *
   * @return bool
   *   TRUE if experiment has PhenoCombo records. FALSE, otherwise.
   */
  public static function experimentHasPhenoCombo(TripalEntity|int|string $experiment): bool {

    $project_id = self::resolveExperimentToProjectId($experiment);

    $has_phenocombo = \Drupal::database()->select(self::PHENOCOMBO_TABLE, 'tbl')
      ->condition('tbl.project_id', $project_id, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $has_phenocombo ? TRUE : FALSE;
  }

}
