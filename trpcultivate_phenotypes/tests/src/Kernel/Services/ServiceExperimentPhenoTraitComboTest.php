<?php

/**
   * Test getExperimentTraitMethodUnitCombos().
   */
  public function testGetExperimentTraitMethodUnitCombos() {

    try {
      $this->service_traits
        ->getExperimentTraitMethodUnitCombos(999);
    }
    catch (\Exception $e) {
      $this->assertEquals(
        'Experiment ID is required and must reference an existing experiment.',
        $e->getMessage(),
        'The method getExperimentTraitMethodUnitCombos() is expected to throw an exception with a non-existent project provided.',
      );
    }

    $experiment_name = 'Test Project 1';
    $experiment_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => $experiment_name])
      ->execute();

    $a_genus = 'Another Genus';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $a_genus,
        'species' => 'species',
      ])
      ->execute();

    try {
      $this->service_traits
        ->getExperimentTraitMethodUnitCombos($experiment_id, $a_genus);
    }
    catch (\Exception $e) {
      $this->assertEquals(
        'The genus is not configured to contain phenotypic traits.',
        $e->getMessage(),
        'The method getExperimentTraitMethodUnitCombos() is expected to throw an exception with a non-configured genus provided.',
      );
    }

    $this->setOntologyConfig($a_genus);

    $ins = $this->chado_connection->insert('1:projectprop')
      ->fields(['project_id', 'type_id', 'value', 'rank']);

    $ins->values([
      'project_id' => $experiment_id,
      'type_id' => $this->terms['genus'],
      'value' => $this->genus,
      'rank' => 1,
    ]);

    $ins->values([
      'project_id' => $experiment_id,
      'type_id' => $this->terms['genus'],
      'value' => $a_genus,
      'rank' => 2,
    ]);

    $ins->execute();

    $schema = $this->chado_connection->getSchemaName();
    $tmp_trait = [];

    foreach ([$this->genus, $a_genus] as $i => $genus) {
      $this->service_traits->setTraitGenus($genus);

      $trait_name = 'Trait' . $i;
      $method_name = 'Method' . $i;
      $unit_name = 'Unit' . $i;

      $trait = $this->service_traits->insertTrait([
        'Trait Name' => $trait_name,
        'Trait Description' => $trait_name . ' Description',
        'Method Short Name' => $method_name . '-SName',
        'Collection Method' => $method_name . ' - Collection Method',
        'Unit' => $unit_name,
        'Type' => 'Quantitative',
      ], $schema);

      $tmp_trait[$i] = $trait;

      $expcombo_table = 'trpcultivate_phenocombo';
      $drupal_dbconnection = $this->container->get('database');

      $drupal_dbconnection
        ->insert($expcombo_table)
        ->fields([
          'project_id' => $experiment_id,
          'attr_id' => $trait['trait'],
          'observable_id' => $trait['method'],
          'unit_id' => $trait['unit'],
          'label' => 'Label' . uniqid(),
          'is_archived' => mt_rand(0, 1),
          'is_required' => mt_rand(0, 1),
          'was_shared' => mt_rand(0, 1),
          'was_collected' => mt_rand(0, 1),
          'uid' => $this->container->get('current_user')->id(),
          'timestamp' => time(),
        ])
        ->execute();
    }

    $expcombo_table_fields = $drupal_dbconnection
      ->select('information_schema.columns', 'cl')
      ->fields('cl', ['column_name'])
      ->condition('cl.table_name', $expcombo_table, '=')
      ->execute()
      ->fetchCol();

    $combo_format = [
      'header' => [
        $combo_id = $expcombo_table_fields[0],
        'name',
        'description',
        'type',
      ],
      'component' => [
        $combo_id,
        'name',
        'definition',
      ],
      'full' => $expcombo_table_fields,
    ];

    // Test all formats and for each genus.
    foreach (array_keys($combo_format) as $format) {
      $combos = $this->service_traits
        ->getExperimentTraitMethodUnitCombos($experiment_id, NULL, ['format' => $format]);

      $combos_fetched_by_expname = $this->service_traits
        ->getExperimentTraitMethodUnitCombos($experiment_id, NULL, ['format' => $format]);

      $this->assertEquals(
        $combos,
        $combos_fetched_by_expname,
        'Fetching combos by experiment id or by experiment name is expected to return identical results.',
      );

      unset($combos_fetched_by_expname);

      foreach ($combo_format[$format] as $key) {
        foreach ($combos as $label => $combo) {
          if (isset($ombo['label'])) {
            $this->assertEquals(
              $label,
              $combo['lable'],
              'The label key of the combo does not match label/name value in the array.',
            );
          }

          $this->assertNotNull(
            $combo[$key],
            'Experiment trait combo in ' . $format . ' format, is expected to contain key: ' . $key,
          );

          if ($format == 'full') {
            // Verify the trait, method, and unit.
            $this->assertEquals(
              $combo['trait']['cvterm.cvterm_id'],
              $combo['attr_id'],
              'The attr_id resolved to incorrect cvterm record.',
            );

            $this->assertEquals(
              $combo['method']['cvterm.cvterm_id'],
              $combo['observable_id'],
              'The observable_id resolved to incorrect cvterm record.',
            );

            $this->assertEquals(
              $combo['unit']['cvterm.cvterm_id'],
              $combo['unit_id'],
              'The unit_id resolved to incorrect cvterm record.',
            );
          }
        }
      }
    }

    // Test full dataset.
    $combos = $this->service_traits->getExperimentTraitMethodUnitCombos($experiment_id);

    $i = 0;
    foreach ($combos as $combo) {
      $this->assertEquals(
        $tmp_trait[$i]['trait'],
        $combo['attr_id'],
        'The attr_id value of the combo returned does not match expected value.',
      );

      $this->assertEquals(
        $tmp_trait[$i]['method'],
        $combo['observable_id'],
        'The observable_id value of the combo returned does not match expected value.',
      );

      $this->assertEquals(
        $tmp_trait[$i]['unit'],
        $combo['unit_id'],
        'The unit_id value of the combo returned does not match expected value.',
      );

      $i++;
    }

    // Pull specific genus.
    foreach ([$this->genus, $a_genus] as $i => $genus) {
      $combos = $this->service_traits->getExperimentTraitMethodUnitCombos($experiment_id, $genus);

      foreach ($combos as $combo) {
        $this->assertEquals(
          $tmp_trait[$i]['trait'],
          $combo['attr_id'],
          'The attr_id value of the combo returned does not match expected value.',
        );

        $this->assertEquals(
          $tmp_trait[$i]['method'],
          $combo['observable_id'],
          'The observable_id value of the combo returned does not match expected value.',
        );

        $this->assertEquals(
          $tmp_trait[$i]['unit'],
          $combo['unit_id'],
          'The unit_id value of the combo returned does not match expected value.',
        );
      }
    }
  }

  /**
   * Test getExperimentTraitMethodUnitCombo().
   */
  public function testGetExperimentTraitMethodUnitCombo() {

    $a_genus = 'Get Combo - Test Genus';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $a_genus,
        'species' => 'species',
      ])
      ->execute();

    $this->setOntologyConfig($a_genus);
    $test_combo = $this->createExperimentWithTraitMethodUnitCombos($a_genus);

    $combo_row = $this->chado_connection->select('trpcultivate_phenocombo', 'pc')
      ->fields('pc')
      ->condition('pc.combo_id', $test_combo['combo_id'], '=')
      ->execute()
      ->fetchAssoc();

    $combo_by = [];

    // Get by trait combo - trait, method and unit.
    $combo_by['trait_combo'] = $this->service_traits->getExperimentTraitMethodUnitCombo(
      $test_combo['experiment']['id'],
      $test_combo['trait_inserted']
    );

    // Get by label.
    $combo_by['label'] = $this->service_traits->getExperimentTraitMethodUnitCombo(
      $test_combo['experiment']['id'],
      $test_combo['combo_details']['label']
    );

    foreach ($combo_by as $by_key => $exp_combo) {
      $this->assertEquals(
        $combo_row,
        $exp_combo,
        'Failed to get the expected experiment trait-method-unit combo by ' . $by_key . ' key.',
      );
    }
  }

  /**
   * Test assignTraitMethodUnitComboToExperiment().
   */
  public function testAssignTraitMethodUnitComboToExperiment() {

    $experiment_name = 'Experiment With Phenotypes';
    $experiment_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => $experiment_name])
      ->execute();

    $pheno_genus = 'Genus With Pheno';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $pheno_genus,
        'species' => 'species',
      ])
      ->execute();

    $this->setOntologyConfig($pheno_genus);

    $this->chado_connection->insert('1:projectprop')
      ->fields(['project_id', 'type_id', 'value', 'rank'])
      ->values([
        'project_id' => $experiment_id,
        'type_id' => $this->terms['genus'],
        'value' => $this->genus,
        'rank' => 1,
      ])
      ->execute();

    $exp_phenocombo = $this->service_traits
      ->getExperimentTraitMethodUnitCombos($experiment_id, $pheno_genus);

    $this->assertEmpty(
      $exp_phenocombo,
      'The experiment is expected to contain 0 combo at this point.'
    );

    // Test paramenter array keys.
    $combo_keys = [
      'trait_combo' => ['trait', 'method', 'unit'],
      'combo_experiment_details' => [
        'label',
        $this->service_traits::PHENO_COMBO_STATUS['archived'],
        $this->service_traits::PHENO_COMBO_STATUS['required'],
        $this->service_traits::PHENO_COMBO_STATUS['collected'],
        $this->service_traits::PHENO_COMBO_STATUS['shared'],
      ],
    ];

    // Create parameter values of key => key to trigger missing key exception.
    $entry = [];
    foreach ($combo_keys as $param => $keys) {
      $entry[$param] = array_combine($keys, $keys);
    }

    foreach ($entry as $param_name => $param_values) {
      $param_value_keys = array_keys($param_values);

      foreach ($param_value_keys as $key) {
        $input = $entry[$param_name];
        // Remove current key.
        unset($input[$key]);

        if ($param_name == 'trait_combo') {
          $trait_combo = $input;
          $combo_experiment_details = $entry['combo_experiment_details'];
        }
        else {
          $trait_combo = $entry['trait_combo'];
          $combo_experiment_details = $input;
        }

        try {
          $this->service_traits
            ->assignTraitMethodUnitComboToExperiment(
              $trait_combo,
              $combo_experiment_details,
              $experiment_id
            );
        }
        catch (\Exception $e) {
          $missing_keys = array_diff($param_values, array_keys($input));
          $this->assertEquals(
            'The Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService::assignTraitMethodUnitComboToExperiment method accepts keys for parameter ' . $param_name . ' [ ' . implode(', ', $param_values) . ' ]. You are missing keys [ ' . implode(', ', $missing_keys) . ' ].',
            $e->getMessage(),
            'An exception with the message shown is expected if parameter is missing a key',
          );
        }

        unset($input);
      }
    }

    // Assign a combo.
    $this->service_traits->setTraitGenus($pheno_genus);

    $trait_name = 'Trait 1';
    $method_name = 'Method 1';
    $unit_name = 'Unit 1';

    $trait_combo = $this->service_traits->insertTrait([
      'Trait Name' => $trait_name,
      'Trait Description' => $trait_name . ' Description',
      'Method Short Name' => $method_name . '-SName',
      'Collection Method' => $method_name . ' - Collection Method',
      'Unit' => $unit_name,
      'Type' => 'Quantitative',
    ], $this->chado_connection->getSchemaName());

    $label = 'My Trait 1 Label';
    $assigned_combo = $this->service_traits->assignTraitMethodUnitComboToExperiment(
      $trait_combo,
      $combo_experiment_details = [
        'label' => $label,
        $this->service_traits::PHENO_COMBO_STATUS['archived'] => 1,
        $this->service_traits::PHENO_COMBO_STATUS['required'] => 0,
        $this->service_traits::PHENO_COMBO_STATUS['collected'] => 0,
        $this->service_traits::PHENO_COMBO_STATUS['shared'] => 1,
      ],
      $experiment_id
    );

    $this->assertNotNull($assigned_combo, 'Test failed to assign a combo to experiment.');

    // Result is keyed by label.
    $exp_phenocombo = $this->service_traits
      ->getExperimentTraitMethodUnitCombos($experiment_id, $pheno_genus);

    foreach ($combo_keys['trait_combo'] as $key) {
      $this->assertEquals(
        $trait_combo[$key],
        $exp_phenocombo[$label][$key]['cvterm.cvterm_id'],
        'The assigned trait key ' . $key . ' does not match expected value.',
      );
    }

    foreach ($combo_keys['combo_experiment_details'] as $key) {
      $this->assertEquals(
        $combo_experiment_details[$key],
        $exp_phenocombo[$label][$key],
        'The assigned combo key ' . $key . ' does not match expected value.',
      );
    }
  }

  /**
   * Test setExperimentTraitMethodUnitComboStatus().
   */
  public function testSetExperimentTraitMethodUnitComboStatus() {

    $status_flags = [
      $this->service_traits::PHENO_COMBO_STATUS['archived'],
      $this->service_traits::PHENO_COMBO_STATUS['required'],
      $this->service_traits::PHENO_COMBO_STATUS['collected'],
      $this->service_traits::PHENO_COMBO_STATUS['shared'],
    ];

    $a_genus = 'Set Combo Status - Test Genus';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $a_genus,
        'species' => 'species',
      ])
      ->execute();

    $this->setOntologyConfig($a_genus);
    $test_combo = $this->createExperimentWithTraitMethodUnitCombos($a_genus);

    $invalid_combo_param = [
      'combo_id' => 0,
      'trait-method-unit' => ['trait' => '', 'method' => ''],
      'label' => '',
    ];

    foreach ($invalid_combo_param as $combo_as => $combo_value) {
      try {
        $this->service_traits->setExperimentTraitMethodUnitComboStatus(
          $combo_value,
          array_fill_keys(array_values($status_flags), mt_rand(0, 1)),
        );
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'method parameter \'combo\' as ' . $combo_as,
          $e->getMessage(),
          'The exception message does not match expected message when invalid combo parameter as ' . $combo_as . ' is set to an invalid value.',
        );
      }
    }

    $this->service_traits->setExperimentTraitMethodUnitComboStatus(
      $test_combo['combo_id'],
      ['is_required' => 1]
    );
  }

  /**
   * Insert a test trait-method-unit combo row.
   *
   * @param string $genus
   *   Genus the trait is specific to.
   *
   * @return array
   *   The combo parameter values.
   *   - 'genus': the genus the trait is specific to.
   *   - 'experiment': contains the id and name of the experiment.
   *   - 'trait_inserted': the trait-method-unit combo inserted.
   *   - 'combo_trait': the trait meta data used to create trait-method-unit.
   *   - 'combo_details': the experiment combo metadata.
   *   - 'combo_id': the combo_id of the trait assigned to an experiment.
   */
  public function createExperimentWithTraitMethodUnitCombos($genus): array {

    $combo['genus'] = $genus;
    $this->service_traits->setTraitGenus($genus);

    $experiment_name = 'Experiment With Phenotypes';
    $combo['experiment']['name'] = $experiment_name;
    $experiment_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => $experiment_name])
      ->execute();
    $combo['experiment']['id'] = $experiment_id;

    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'species',
      ])
      ->execute();

    $this->chado_connection->insert('1:projectprop')
      ->fields(['project_id', 'type_id', 'value', 'rank'])
      ->values([
        'project_id' => $experiment_id,
        'type_id' => $this->terms['genus'],
        'value' => $genus,
        'rank' => 1,
      ])
      ->execute();

    $combo['combo_trait'] = [
      'Trait Name' => $this->randomString(10),
      'Trait Description' => $this->randomString(10),
      'Method Short Name' => $this->randomString(10),
      'Collection Method' => $this->randomString(10),
      'Unit' => $this->randomString(10),
      'Type' => (mt_rand(0, 1) == 0) ? 'Quantitative' : 'Quantitative',
    ];

    $combo['trait_inserted'] = $this->service_traits->insertTrait(
      $combo['combo_trait'],
      $this->chado_connection->getSchemaName()
    );

    $combo['combo_id'] = $this->service_traits->assignTraitMethodUnitComboToExperiment(
      $combo['trait_inserted'],
      $combo['combo_details'] = [
        'label' => $this->randomString(10),
        $this->service_traits::PHENO_COMBO_STATUS['archived'] => mt_rand(0, 1),
        $this->service_traits::PHENO_COMBO_STATUS['required'] => mt_rand(0, 1),
        $this->service_traits::PHENO_COMBO_STATUS['collected'] => mt_rand(0, 1),
        $this->service_traits::PHENO_COMBO_STATUS['shared'] => mt_rand(0, 1),
      ],
      $combo['experiment']['id']
    );

    return $combo;
  }