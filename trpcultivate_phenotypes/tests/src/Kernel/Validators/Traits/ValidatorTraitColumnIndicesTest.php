<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorColumnIndices;

 /**
  * Tests the ColumnIndices validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitColumnIndicesTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'file',
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * The validator instance to use for testing.
   *
   * @var ValidatorColumnIndices
   */
  protected ValidatorColumnIndices $instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_column_indices';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using ColumnIndices Trait',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorColumnIndices(
      $configuration,
      $validator_id,
      $plugin_definition
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the ColumnIndices trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the ColumnIndices::setIndices() setter
   *
   * @return void
   */
  public function testSetter() {

  }
}
