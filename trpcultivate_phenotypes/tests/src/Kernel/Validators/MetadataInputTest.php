<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating metadata/input to importer.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

 /**
  * Tests Tripal Cultivate Phenotypes Metadata Validator Plugins.
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class MetadataInputTest extends ChadoTestKernelBase {
  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   */
  protected static $modules = [
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

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    $genus = 'Tripalus';
    // Create our organism and configure it.
    $organism_id = $this->connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");
    $this->cvdbon = $this->setOntologyConfig($genus);
    $this->terms = $this->setTermConfig();
  }

  /**
   * Test genus input.
   */
  public function testGenusInput() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_genus_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);
    
    $form_values = ['genus' => '1'];

    $validation_status = $instance->validateMetadata($form_values);
    //print_r($validation_status);
  }
}