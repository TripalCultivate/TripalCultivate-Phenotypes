  <?php

  /**
   * Users.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $current_user;

  /**
   * The table name that contains experiment combos.
   *
   * @var string
   */
  private const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Status flags of a trait-method-unit combo.
   *
   * @var array
   */
  public const PHENO_COMBO_STATUS = [
    'archived' => 'is_archived',
    'required' => 'is_required',
    'collected' => 'was_collected',
    'shared' => 'was_shared',
  ];

  /**
   * Get trait-method-unit combinations of an experiment.
   *
   * @param int|string $experiment
   *   The experiment id (int), a unique identifier or experiment name (string),
   *   used to filter traits and return only traits associated with the
   *   specified experiment id or name.
   * @param null|string $genus
   *   (Optional) The genus to further filter the traits combo results.
   *   Default to null value.
   * @param array $options
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
   *   - A genus not configured to be used in phenotypes module.
   *   - Experiment provided does not reference an existing experiment.
   *   - Not a valid trait format or value requested in the $options parameter.
   */
  public function getExperimentTraitMethodUnitCombos(int|string $experiment, ?string $genus = NULL, array $options = []) {

    $experiment_id = (is_int($experiment) && $experiment > 0) || (is_string($experiment) && ctype_digit($experiment))
      ? (int) $experiment : ChadoProjectAutocompleteController::getProjectId($experiment);

    if (!ChadoProjectAutocompleteController::getProjectName($experiment_id)) {
      throw new \Exception('Experiment ID is required and must reference an existing experiment.');
    }

    if ($genus) {
      $genus_config = $this->service_PhenoGenusOntology
        ->getGenusOntologyConfigValues($genus);

      if (!$genus_config) {
        throw new \Exception('The genus is not configured to contain phenotypic traits.');
      }
    }

    $valid_option_keys = ['format'];
    if ($option_diff = array_diff(array_keys($options), $valid_option_keys)) {
      throw new \Exception('The ' . __METHOD__ . ' method accepts options keys [' . impoode(', ', $valid_option_keys) . ']. You provided ' . implode(', ', $option_diff));
    }

    // Define the key/field name of each format. Default format to - full, if
    // not specified.
    $option_format = strtolower($options['format'] ?? 'full');
    $expcombo_table = 'trpcultivate_phenocombo';

    // Format full is table trpcultivate_phenocombo fields: combo_id,
    // project_id, attr_id, observable_id, unit_id, uid, label, is_archived,
    // is_required, was_collected, was_shared, and timestamp.
    $expcombo_table_fields = ($option_format == 'full') ? $this->chado_connection
      ->select('information_schema.columns', 'info')
      ->fields('info', ['column_name'])
      ->condition('info.table_name', $expcombo_table, '=')
      ->execute()
      ->fetchCol() : [];

    $combo_format = [
      'full' => $expcombo_table_fields,
      'header' => [
        'combo_id',
        'name',
        'description',
        'type',
      ],
      'component' => [
        'combo_id',
        'name',
        'definition',
      ],
    ];

    // Alias-field mapping array - maps actual table columns to alternative
    // names or alias used in $combo_format definitions array.
    $alias_field_mapping = [
      'description' => 'definition',
      'name' => 'label',
    ];

    if (!in_array($option_format, $combo_format_keys = array_keys($combo_format))) {
      throw new \Exception('The ' . __METHOD__ . ' method accepts format values [' . impoode(', ', $combo_format_keys) . ']. You provided ' . $combo_format);
    }

    // Retrieve the trait-method-unit combos of the experiment. Each combo is
    // keyed by the unique label.
    $query = $this->chado_connection->select($expcombo_table, 'combo');
    $query->leftJoin('1:cvterm', 'trait', 'combo.attr_id = trait.cvterm_id');

    $query->fields('combo');
    $query->fields('trait', ['definition']);
    // Use an expression to format the 'type' based on `combo.is_required`.
    $query->addExpression("CASE WHEN combo.is_required = 1 THEN 'required' ELSE 'optional' END", "type");

    $query->condition('combo.project_id', $experiment_id, '=');
    if ($genus) {
      $query->condition('trait.cv_id', $genus_config['trait'], '=');
    }

    $exp_phenocombo = $query->orderBy('trait.name', 'ASC')
      ->execute()
      ->fetchAllAssoc('label');

    $combos = [];
    if (!$exp_phenocombo) {
      return $combos;
    }

    foreach ($exp_phenocombo as $label => $combo) {
      // Append table values defined by the format array structure.
      $field_values = [];

      foreach ($combo_format[$option_format] as $format_keys) {
        // Resolve to actual table field for aliases, otherwise use field as is.
        $field_values[$format_keys] = $combo->{$alias_field_mapping[$format_keys] ?? $format_keys};
      }

      // Append other format-specific values.
      if ($option_format != 'header') {
        $trait_combo = [];

        // Get trait, method, unit of a combo.
        foreach (['trait' => 'attr_id', 'method' => 'observable_id', 'unit' => 'unit_id'] as $field_alias => $field_name) {
          $trait_combo[$field_alias] = $this->cvterm_buddy
            ->getCvterm(['cvterm.cvterm_id' => $combo->{$field_name}])[0]
            ->getValues();
        }
      }

      switch ($option_format) {

        case 'full':
          // Resolve attr_id, observable_id, unit_id into full table record.
          foreach ($trait_combo as $field_alias => $field_value) {
            $field_values[$field_alias] = $field_value;
          }

          break;

        case 'component':
          // Setup required key-value pairs of the component.
          $data_type = $this->chado_connection->select('1:cvtermprop', 'prop')
            ->fields('prop', ['value'])
            ->condition('prop.cvterm_id', $combo->unit_id, '=')
            ->condition('prop.type_id', $this->terms['unit_type'], '=')
            ->execute()
            ->fetchField();

          $field_values['multiselect_method'] = FALSE;
          $field_values['method_unit_combo'][] = [
            'method_shortname' => $trait_combo['method']['cvterm.name'],
            'unit' => $trait_combo['unit']['cvterm.name'],
            'type' => $data_type,
            'collection_method' => $trait_combo['method']['cvterm.definition'],
          ];
          $field_values['status'] = [
            'archived' => $combo->is_archived,
            'required' => $combo->is_required,
            'collected' => $combo->was_collected,
            'shared' => $combo->was_shared,
          ];

          break;
      }

      $combos[trim($label)] = $field_values;
    }

    return $combos;
  }

  /**
   * Get single specific trait-method-unit combo of an experiment.
   *
   * @param int|string $experiment
   *   The experiment to retrieve the trait-method-unit combination for.
   *   The following are supported:
   *   - An integer project_id
   *   - A string experiment name (corresponding to 'name' column in Chado
   *    'project' table).
   * @param string|array $combo
   *   The combo to be retrieved, the following are supported:
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see assignTraitMethodUnitComboToExperiment()
   *
   * @return array
   *   An associative array describing the experiment trait-method-unit combo
   *   including the combo_id, project_id, attr_id, observable_id, unit_id,
   *   uid, label, is_archived, is_required, was_collected, was_shared, and
   *   timestamp from trpcultivate_phenocombo table. Furthermore, 'trait',
   *   'method' and 'unit' are the cvterm names referenced by the 'attr_id',
   *   'observable_id', and 'unit_id' respectively.
   *   @see trpcultivate_phenotypes_schema()
   */
  public function getExperimentTraitMethodUnitCombo(int|string $experiment, string|array $combo): array {

    // Will handle invalid experiment value.
    $experiment_id = $this->getExperimentId($experiment);

    $query = $this->chado_connection
      ->select(self::PHENO_COMBO_TABLE, 'cb')
      ->fields('cb');

    if (is_array($combo)) {
      // By trait-method-unit combo.
      ['trait' => $attr_id, 'method' => $observable_id, 'unit' => $unit_id] = $this->getTraitMethodUnitCombo(
        (int) $combo['trait'], (int) $combo['method'], (int) $combo['unit']
      );

      $query
        ->condition('cb.attr_id', $attr_id->cvterm_id, '=')
        ->condition('cb.observable_id', $observable_id->cvterm_id, '=')
        ->condition('cb.unit_id', $unit_id->cvterm_id, '=');
    }
    else {
      // By label.
      $label = trim($combo);

      $query
        ->condition('cb.label', $label, '=');
    }

    $combo = $query
      ->condition('cb.project_id', $experiment_id, '=')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $combo ?: [];
  }

  /**
   * Assign a trait-method-unit combo to an experiment.
   *
   * @param array $trait_combo
   *   An associative array describing the trait-method-unit combination
   *   to assign to this experiment. This MUST ALREADY EXIST.
   *   Required keys are:
   *   - trait (int|string): A string value is the trait name, whereas an
   *     integer value is the trait id (phenotype.attr_id).
   *   - method (int|string): A string value is the trait method short name,
   *     whereas an integer value is the method id (phenotype.observable_id).
   *   - unit (int|string): A string value is the method unit name, whereas
   *     an integer value is the unit id (phenotype.unit_id).
   * @param array $combo_experiment_details
   *   An associative array describing the label and status flags to assign
   *   to this combo within this experiment. The following keys are expected:
   *   - label: a human-readable text label used as an alternative reference to
   *     the trait name. Labels must be unique within an experiment.
   *   - is_archived: 1 if archived, 0 otherwise.
   *   - is_required: 1 if required, 0 otherwise.
   *   - was_shared: 1 if used in Phenotypes Share module, 0 otherwise.
   *   - was_collected: 1 if measured in Phenotypes Collect module, 0 otherwise.
   * @param int|string $experiment
   *   The experiment to assign this trait-method-unit combination to.
   *   The following are supported:
   *   - An integer project_id
   *   - A string experiment name (corresponding to 'name' column in Chado
   *    'project' table).
   *
   * @throws \InvalidArgumentException
   *   An exception is thrown if
   *    - $trait_combo and $combo_experiment_details do not contain expected
   *    keys defined by the parameter.
   *   - $trait_combo did not return trait, method and unit values.
   *    - $experiment did not return a project record.
   *
   * @return int
   *   The combo id number inserted.
   */
  public function assignTraitMethodUnitComboToExperiment(array $trait_combo, array $combo_experiment_details, int|string $experiment): int {

    // Will handle invalid experiment value.
    $experiment_id = $this->getExperimentId($experiment);

    $combo_keys = [
      'trait_combo' => ['trait', 'method', 'unit'],
      'combo_experiment_details' => [
        'label',
        self::PHENO_COMBO_STATUS['archived'],
        self::PHENO_COMBO_STATUS['required'],
        self::PHENO_COMBO_STATUS['collected'],
        self::PHENO_COMBO_STATUS['shared'],
      ],
    ];

    // Verify that for both trait combo and combo details parameter contain
    // required combo keys.
    foreach ($combo_keys as $param => $valid_keys) {
      if ($option_diff = array_diff($valid_keys, array_keys($$param))) {
        throw new \InvalidArgumentException(
          sprintf(
            'The %s method accepts keys for parameter %s [ %s ]. You are missing keys [ %s ].',
            __METHOD__,
            $param,
            implode(', ', $valid_keys),
            implode(', ', $option_diff)
          )
        );
      }
    }

    // Ensure that label is unique within the experiment.
    if (!$this->labelIsUniqueInExperiment($combo_experiment_details['label'], $experiment_id)) {
      throw new \InvalidArgumentException('The label ' . $combo_experiment_details['label'] . ' is already in used within the experiment.');
    }

    // Combo has trait, method and unit.
    ['trait' => $attr_id, 'method' => $observable_id, 'unit' => $unit_id] = $this->getTraitMethodUnitCombo(
      (int) $trait_combo['trait'], (int) $trait_combo['method'], (int) $trait_combo['unit']
    );

    if (!$attr_id || !$observable_id || !$unit_id) {
      throw new \InvalidArgumentException('The trait combo failed to return a trait-method-unit values.');
    }

    // Make sure that combo status flag is either 0 or 1 and if neither or not
    // provided will default to 0.
    foreach ($combo_keys['combo_experiment_details'] as $key) {
      if ($key != 'label') {
        $combo_experiment_details[$key] = (int) ((bool) $combo_experiment_details[$key]);
      }
    }

    $transaction = $this->chado_connection->startTransaction();

    $fields_metadata = [
      'project_id' => $experiment_id,
      'attr_id' => $attr_id->cvterm_id,
      'observable_id' => $observable_id->cvterm_id,
      'unit_id' => $unit_id->cvterm_id,
      'label' => $combo_experiment_details['label'],
      'uid' => $this->current_user->id(),
      'timestamp' => time(),
    ];

    $fields_status_flags = [];
    foreach (self::PHENO_COMBO_STATUS as $combo_status) {
      $fields_status_flags[$combo_status] = $combo_experiment_details[$combo_status];
    }

    try {
      $assigned_combo = $this->chado_connection
        ->insert('0:' . self::PHENO_COMBO_TABLE)
        ->fields($fields_metadata + $fields_status_flags)
        ->execute();
    }
    catch (Exception $e) {
      $transaction->rollback();
      throw new \Exception($e);
    }

    return $assigned_combo;
  }

  /**
   * Set trait-method-unit combo status flags.
   *
   * @param mixed $combo
   *   The trait-method-unit combo previously assigned to the experiment.
   *   The following formats are supported:
   *   - combo_id (int) uniquely identifying this trait-method-unit-experiment
   *     combo as returned when assigning / getting experiment trait-method-unit
   *     associations.
   *   - label (string) uniquely identifying this trait-method-unit combo in
   *     the experiment indicated.
   *   - trait combo (array) indicating the trait-method-unit combo.
   *     @see assignTraitMethodUnitComboToExperiment()
   * @param array $status_flags
   *   The status array key-value pair where the value is 0 or 1
   *   (yes or no, respectively) and the keys are the following status flags.
   *   - is_archived: indicates trait combo is archived.
   *   - is_required: indicates trait combo is a required trait and must contain
   *      a value in the data file for this column.
   *   - was_shared: indicates trait combo was used in Phenotypes Share module.
   *   - was_collected: indicates trait measured in Phenotypes Collect module.
   * @param int|string|null $experiment
   *   The experiment the trait-method-unit combination belongs to.
   *   The following are supported:
   *   - An integer project_id
   *   - A string experiment name (corresponding to 'name' column in Chado
   *    'project' table).
   */
  public function setExperimentTraitMethodUnitComboStatus(mixed $combo, array $status_flags, int|string|null $experiment = NULL): void {

    $combo_keys = [
      'trait_combo' => ['trait', 'method', 'unit'],
      'status_flags' => [
        self::PHENO_COMBO_STATUS['archived'],
        self::PHENO_COMBO_STATUS['required'],
        self::PHENO_COMBO_STATUS['collected'],
        self::PHENO_COMBO_STATUS['shared'],
      ],
    ];

    // Construct status flags for update.
    $update_status_flags = [];

    foreach ($status_flags as $flag_name => $flag_value) {
      // Skip unrecognized key or invalid flag value.
      $flag_value = (int) $flag_value;
      if (!in_array($flag_name, $combo_keys['status_flags']) || !in_array($flag_value, [0, 1])) {
        continue;
      }

      $update_status_flags[$flag_name] = $flag_value;
    }

    // Nothing to update.
    if (empty($update_status_flags)) {
      throw new \InvalidArgumentException(sprintf(
        'The %s expects one or combination of status_flags keys: [ %s ] and set to either 0 (no) or 1 (yes).',
        __METHOD__,
        implode(', ', $combo_keys['status_flags']),
      ));
    }

    // $transaction = $this->chado_connection->startTransaction();
    $transaction = \Drupal::database()->startTransaction();

    // $update_query = $this->chado_connection->update('0:trpcultivate_phenocombo')
    $update_query = \Drupal::database()->update('trpcultivate_phenocombo')
      ->fields($update_status_flags);

    if ($experiment) {
      $experiment_id = $this->getExperimentId($experiment);
      $update_query
        ->condition('project_id', $experiment_id, '=');
    }

    if (is_int($combo)) {
      // Combo is integer value - combo_id.
      if ($combo <= 0) {
        throw new \InvalidArgumentException(
          sprintf(
            'The %s method parameter \'combo\' as combo_id, must be a number greater that 0.',
            __METHOD__
          )
        );
      }

      $update_query
        ->condition('combo_id', $combo, '=');
    }
    elseif (is_array($combo)) {
      // Combo is an array value - trait-method-unit combination.
      if ($option_diff = array_diff($combo_keys['trait_combo'], array_keys($combo))) {
        throw new \InvalidArgumentException(
          sprintf(
            'The %s method parameter \'combo\' as trait-method-unit combo, must contain the keys [ %s ]. You are missing the keys [ %s ]',
            __METHOD__,
            implode(', ', $combo_keys['trait_combo']),
            implode(', ', $option_diff)
          )
        );
      }

      $update_query
        ->condition('attr_id', $combo['trait'], '=')
        ->condition('observable_id', $combo['method'], '=')
        ->condition('unit_id', $combo['unit'], '=');
    }
    elseif (is_string($combo)) {
      // Combo is a string value - combo label.
      if (trim($combo) === '') {
        throw new \InvalidArgumentException(
          sprintf(
            'The %s method parameter \'combo\' as label, must not be an empty string value.',
            __METHOD__
          )
        );
      }

      $update_query
        ->condition('label', trim($combo), '=');
    }
    else {
      // Combo is of an invalid data type.
      throw new \InvalidArgumentException(
        sprintf(
          'The %s method parameter \'combo\', must be integer, array, or string value.',
          __METHOD__
        )
      );
    }

    try {
      $update_query->execute();
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw new \Exception($e);
    }
  }


  /**
   * Get the experiment unique indetifier id number from an ID or name.
   *
   * @param int|string $experiment
   *   The experiment identifier, either:
   *   - An integer project_id
   *   - A string experiment name (corresponding to 'name' column in Chado
   *    'project' table).
   *
   * @throws \Exception
   *   An exception is thrown if
   *    - experiment (id or name) did not match an experiment record.
   *
   * @return int
   *   The resolved experiment id (project_id).
   */
  protected function getExperimentId(int|string $experiment): int {

    if ((is_int($experiment) && $experiment > 0) || (is_string($experiment) && ctype_digit($experiment))) {
      $experiment_id = (int) $experiment;

      if (!ChadoProjectAutocompleteController::getProjectName($experiment_id)) {
        throw new \InvalidArgumentException('Experiment ID #' . $experiment_id . ' does not exist.');
      }
    }
    else {
      $experiment_id = ChadoProjectAutocompleteController::getProjectId($experiment);

      if (!$experiment_id) {
        throw new \InvalidArgumentException('Experiment name ' . $experiment . ' does not exist.');
      }
    }

    return (int) $experiment_id;
  }

  /**
   * Is combo label unique within an experiment.
   *
   * @param string $label
   *   A human-readable text label used as an alternative reference to the trait
   *   name. Labels must be unique within an experiment.
   * @param int|string $experiment
   *   The experiment to assign this trait-method-unit combination to.
   *   The following are supported:
   *   - An integer project_id
   *   - A string experiment name (corresponding to 'name' column in Chado
   *    'project' table).
   *
   * @throws \InvalidArgumentException
   *   An exception is thrown if
   *    - label is an empty string.
   *    - experiment did not return a project record.
   *
   * @return bool
   *   TRUE if the label is UNIQUE within the experiment and FALSE, otherwise.
   */
  protected function labelIsUniqueInExperiment(string $label, int|string $experiment): bool {

    $label = trim($label);
    $experiment_id = $this->getExperimentId($experiment);

    if ($label === '' || !$experiment_id) {
      throw new \InvalidArgumentException(
        'Invalid label or experiment value provided. Label: ' . $label . ', Experiment: ' . $experiment
      );
    }

    $label_exists = $this->chado_connection
      ->select(self::PHENO_COMBO_TABLE, 'tc')
      ->fields('tc', ['combo_id'])
      ->condition('tc.label', $label, '=')
      ->condition('tc.project_id', $experiment_id, '=')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $label_exists ? FALSE : TRUE;
  }
