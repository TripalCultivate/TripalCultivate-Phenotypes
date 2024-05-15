<?php

/**
 * @file
 * Kernel tests for validator plugins that operate within the scope of "FILE ROW"
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Plugins that apply to a single row
  * of the input file - the "FILE ROW" scope
  *
  * @group trpcultivate_phenotypes
  * @group validators
  * @group file_row_scope
  */
class FileRowScopeTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $chado;

  /**
   * Configuration
   *
   * @var config_entity
   */
  private $config;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test Trait Type Column Plugin Validator.
   */
  public function testTraitTypeColumnPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_trait_type_column';
    $instance = $this->plugin_manager->createInstance($validator_id);
    //$assets = $this->assets;

    // A list of valid inputs
    $valid_inputs = ['Quantitative', 'Qualitative'];

    // A list of invalid inputs
    $invalid_inputs = ['Collective', 'Type', ' '];
  }

  /**
   * Test Empty Cell Plugin Validator.
   */
  public function testEmptyCellPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_empty_cell';
    $instance = $this->plugin_manager->createInstance($validator_id);
  }

  /**
   * Test Duplicate Traits Plugin Validator.
   */
  public function testDuplicateTraitsPluginValidator() {

    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_duplicate_traits';
    $instance = $this->plugin_manager->createInstance($validator_id);
  }
}
