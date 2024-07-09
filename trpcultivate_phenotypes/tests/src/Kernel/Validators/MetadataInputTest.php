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
   * A project that exists. 
   */
  protected $test_project;

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

    // Create another organism and not configure.
    $genus = 'notconfiggenus';
    $organism_id = $this->connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
    ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (not configured).');
    $this->test_genus['not-configured'] = $genus;
    
    // Set terms configuration.
    $this->setTermConfig();


    // Create test project.
    $project = 'Research Project 1A';
    $project_id = $this->connection->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => 'A test project',
      ])
      ->execute();

    $this->assertIsNumeric($project_id, 'We were not able to create a project for testing.');
    $this->test_project['name'] = $project;
    $this->test_project['id'] = $project_id;
    
    
    // Create a project - genus relationship.
    \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings')
      ->set('trpcultivate.phenotypes.ontology.terms.genus', 1);
    
    $project_prop = $this->connection->insert('1:projectprop')
      ->fields([
        'project_id' => $this->test_project['id'],
        'type_id' => 1,
        'value' => $this->test_genus['configured']
      ])
      ->execute();

    $this->assertIsNumeric($project_prop, 'We were not able to create a project-genus property for testing.');
  }

  /**
   * Test genus input.
   */
  public function testGenusInput() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_genus_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);
    
    // Test items that will throw exception:
    // 1. Passing a string value.
    // 2. Failed to implement a form field element with genus name/key.
    // 3. Passing object or the entire $form_state.

    // Test passing a string value.
    $form_values = 'Not a valid form values';

    $exception_caught  = FALSE;
    $exception_message = ''; 
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message =  $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to genus metadata validator.');
    $this->assertStringContainsString('Unexpected string type was passed', $exception_message, 
      'Expected exception message does not match message when passing $form_state to genus metadata validator.');
    
      
    // No genus field.
    $form_values = ['project_id' => 1111];

    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no genus form field was implemented.');
    $this->assertStringContainsString('Failed to locate genus field element', $exception_message, 
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key genus.');

    
    // A Drupal $form_state object.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['project' => uniqid()]);
    
    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message =  $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to genus metadata validator.');
    $this->assertStringContainsString('Unexpected object type was passed', $exception_message, 
      'Expected exception message does not match message when passing $form_state to genus metadata validator.');
    

    // Other tests:
    // Each test will test that genusExists generated the correct case, valid status and failed item.
    // Failed item is the failed genus value. Failed information is contained in the case
    // whether the genus failed because it does not exist or not configured.

    // Genus does not exits.
    $genus = 'genus-' . uniqid();
    $form_values = ['genus' => $genus];  
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Genus does not exist', $validation_status['case'],
      'Genus exists validator case title does not match expected title for non-existent genus.');
    $this->assertFalse($validation_status['valid'], 'A failed genus must return a FALSE valid status.');
    $this->assertStringContainsString($genus, $validation_status['failedItems'], 'Failed genus value is expected in failed items.');
    

    // Genus exists but not configured/recognized by the module.
    $genus = $this->test_genus['not-configured'];
    $form_values = ['genus' => $genus];
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Genus does not exist', $validation_status['case'],
      'Genus exists validator case title does not match expected title for not configured genus.');
    $this->assertFalse($validation_status['valid'], 'A failed genus must return a FALSE valid status.');
    $this->assertStringContainsString($genus, $validation_status['failedItems'], 'Failed genus value is expected in failed items.');
    

    // A valid genus - exists and is configured.
    $genus = $this->test_genus['configured'];
    $form_values = ['genus' => $genus];  
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals('Genus exists and is configured with phenotypes', $validation_status['case'],
      'Genus exists validator case title does not match expected title for a valid genus.');
    $this->assertTrue($validation_status['valid'], 'A valid genus must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid genus does not return a failed item value.');
  }

  /**
   * Test project input - project exists.
   */
  public function testProjectExistsInput() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_project_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);
    
    // Test items that will throw exception:
    // 1. Passing a string value.
    // 2. Failed to implement a form field element with genus name/key.
    // 3. Passing object or the entire $form_state.

    // Test passing a string value.
    $form_values = 'Not a valid form values';

    $exception_caught  = FALSE;
    $exception_message = ''; 
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message =  $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to project exists metadata validator.');
    $this->assertStringContainsString('Unexpected string type was passed', $exception_message, 
      'Expected exception message does not match message when passing $form_state to project exists metadata validator.');
    
      
    // No genus field.
    $form_values = ['project_id' => 1111];

    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no project form field was implemented.');
    $this->assertStringContainsString('Failed to locate project field element', $exception_message, 
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key project.');

    
    // A Drupal $form_state object.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['genus' => uniqid()]);
    
    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message =  $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to project exists metadata validator.');
    $this->assertStringContainsString('Unexpected object type was passed', $exception_message, 
      'Expected exception message does not match message when passing $form_state to project exists metadata validator.');


    // Other tests:
    // Each test will test that projectExists generated the correct case, valid status and failed item.
    // Failed item is the failed project value. Failed information is contained in the case to indicate project exists or not.

    // Project does not exist.
    $project = 'project-' . uniqid();
    $form_values = ['project' => $project];  
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Project does not exist', $validation_status['case'],
      'Project exists validator case title does not match expected title for non-existent project.');
    $this->assertFalse($validation_status['valid'], 'A failed project must return a FALSE valid status.');
    $this->assertStringContainsString($project, $validation_status['failedItems'], 'Failed project value is expected in failed items.');

    // Project exists - by project id.
    
    // Project exists - by project name.
  }

  /**
   * Test project and genus input - project exists and genus match the set genus of a project.
   */
  public function testProjectGenusInput() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_project_genus_match';
    $instance = $this->plugin_manager->createInstance($validator_id);
  
    // Test items that will throw exception:
    // 1. Passing a string value.
    // 2. Failed to implement a form field element with project and genus name/key.
    // 3. Passing object or the entire $form_state.

    // Test passing a string value.
    $form_values = 'Not a valid form values';

    $exception_caught  = FALSE;
    $exception_message = ''; 
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message =  $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a string to project genus match metadata validator.');
    $this->assertStringContainsString('Unexpected string type was passed', $exception_message, 
      'Expected exception message does not match message when passing $form_state to project genus match metadata validator.');
    
      
    // No project field.
    $form_values = ['genus' => 'Lens'];

    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no project form field was implemented.');
    $this->assertStringContainsString('Failed to locate project field element', $exception_message, 
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key project.');

    
    // No genus field.
    $form_values = ['project' => 'Test Project'];

    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no genus form field was implemented.');
    $this->assertStringContainsString('Failed to locate genus field element', $exception_message, 
      'Expected exception message does not match message when importer failed to implement a form field element with the name/key genus.');


    // A Drupal $form_state object.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['project' => uniqid()]);
    
    $exception_caught  = FALSE;
    $exception_message = '';        
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message =  $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to project exists metadata validator.');
    $this->assertStringContainsString('Unexpected object type was passed', $exception_message, 
      'Expected exception message does not match message when passing $form_state to project exists metadata validator.');


    // Other tests:
    // Each test will test that projectGenusMatch generated the correct case, valid status and failed item.
    // Failed item is the failed project/genus value. Failed information is contained in the case to indicate project and genus match.

    // Test project does not exist.
    $project = 'project-' . uniqid();
    $genus = $this->test_genus['configured'];

    $form_values = ['project' => $project, 'genus' => $genus];  
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Project does not exist', $validation_status['case'],
      'Project genus match validator case title does not match expected title for non-existent project.');
    $this->assertFalse($validation_status['valid'], 'A failed project must return a FALSE valid status.');
    $this->assertStringContainsString($project, $validation_status['failedItems'], 'Failed project value is expected in failed items.');

    // Test project with genus set.
    $project = $this->test_project['id'];
    $genus = $this->test_genus['configured'];

    $form_values = ['project' => $project, 'genus' => $genus];  
    $validation_status = $instance->validateMetadata($form_values);
    
    $this->assertEquals('Project exists and project-genus match the genus provided', $validation_status['case'],
      'Project genus match validator case title does not match expected title for a valid project+genus.');
    $this->assertTrue($validation_status['valid'], 'A valid project+genus must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid project+genus does not return a failed item value.');
  }
}