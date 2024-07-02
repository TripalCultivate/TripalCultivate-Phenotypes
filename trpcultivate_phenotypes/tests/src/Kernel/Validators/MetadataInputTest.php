<?php

/**
 * @file
 * Kernel tests for validator plugins specific to validating metadata/input to importer.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Core\Form\FormState;

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
    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing.');
    $this->setOntologyConfig($genus);
    $this->setTermConfig();
  }

  /**
   * Test genus input.
   */
  public function testGenusInput() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_genus_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);
    
    // The validator expects the form values entered into the form elements
    // through the $form_state.
    
    // This would have been a form that has been submitted.
    $form_state = new FormState();


    // Test passing the $form_state.

    // Test passing $form_state values that does not contain expected field: genus.

    // Test passing $form_state values with a genus field but genus does not exits.
  
    // Test passing $form_state values with a genus field but genus was not configured.
    
    // A valid $form_state values: with genus that existed and was configured.
    
    // Create a form element name/key genus in the $form_state.
  
    $form_state->setValues(['genus' => 'Tripalus']);


    // This is an important step before passing values to the plugin.
    $form_values = $form_state->getValues();
    $validation_status = $instance->validateMetadata($form_state);

    print_r($validation_status);
}
}