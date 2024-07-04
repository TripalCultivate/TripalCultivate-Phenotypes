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
   * A genus that exists and is configured. 
   */
  protected $test_genus;

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

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (configured).');
    $this->test_genus['configured'] = $genus;
    $this->setOntologyConfig($this->test_genus['configured']);

    // Create another organism but create a configuration item
    // where the cv for trait is set to 0 - not configured.
    $genus = 'notconfiggenus';
    $organism_id = $this->connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
    ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (not configured).');
    $this->test_genus['not-configured'] = $genus;
    $config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings')
      ->set('trpcultivate.phenotypes.ontology.cvdbon.' . $this->test_genus['not-configured'], ['trait' => 0]);
    
    // Set terms configuration.
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
    
    // Create form fields and set value.
    // This would have been a form that has been submitted.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['project' => uniqid()]);

    // Test items that will throw exception:
    // 1. Passing object or the entire $form_state.
    // 2. Failed to implement a form field element with genus name/key.

    // Test passing the $form_state.
    $exception_caught  = FALSE;        
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to genus metadata validator.');
    $this->assertStringContainsString('Unexpected object type was passed', $e->getMessage(), 
      'Expected exception message does not match message when passing $form_state to genus metadata validator.');
    
    
    // No genus field.
    $form_values = $form_state->getValues();

    $exception_caught  = FALSE;        
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no genus form field was implemented.');
    $this->assertStringContainsString('Failed to locate genus field element', $e->getMessage(), 
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key genus.');

    // Other tests:
    // Each test will test that genusExists generated the correct case, valid status and failed item.
    // Failed item is the failed genus value. Failed information is contained in the case
    // whether the genus failed because it does not exist or not configured.

    // Test passing $form_state values with a genus field but genus does not exits.
    $genus = 'genus-' . uniqid();
    $form_state->setValues(['genus' => $genus]);
    $form_values = $form_state->getValues();  
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Genus does not exist', $validation_status['case'],
      'Genus exists validator case title does not match expected title for non-existent genus.');
    $this->assertFalse($validation_status['valid'], 'A failed genus must return a FALSE valid status.');
    $this->assertStringContainsString($genus, $validation_status['failedItems'],);
    

    // Test passing $form_state values with a genus field but genus is not configured.
    $genus = $this->test_genus['not-configured'];
    $form_state->setValues(['genus' => $genus]);
    $form_values = $form_state->getValues();  
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Genus exists but is not configured', $validation_status['case'],
      'Genus exists validator case title does not match expected title for not configured genus.');
    $this->assertFalse($validation_status['valid'], 'A failed genus must return a FALSE valid status.');
    $this->assertStringContainsString($genus, $validation_status['failedItems'],);
    

    // A valid genus - exists and is configured.
    $genus = $this->test_genus['configured'];
    $form_state->setValues(['genus' => $genus]);
    $form_values = $form_state->getValues();  
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Genus exists and is configured with phenotypes', $validation_status['case'],
      'Genus exists validator case title does not match expected title for a valid genus.');
    $this->assertTrue($validation_status['valid'], 'A valid genus must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid genus does not return a failed item value.');
  }
}