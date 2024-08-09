<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorProject;

 /**
  * Tests the project validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitProjectTest extends ChadoTestKernelBase {

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
   * The validator instance to use for testing.
   *
   * @var ValidatorProject
   */
  protected ValidatorProject $instance;

  /**
   * Test projects with reference to project id and project name
   * keyed by id and name, respectively.
   * 
   * @var array
   */
  protected array $test_projects;


  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create test projects.
    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    $project_name = 'ATP: A Test Project';
    $project_id = $this->chado_connection
      ->insert('1:project')
      ->fields([
        'name' => $project_name
      ])
      ->execute();
    
    $this->assertIsNumeric($project_id, "We were not able to create an project for testing.");
    
    $this->test_project = [
      'id' => $project_id,
      'name' => $project_name
    ];

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_project';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Project Trait',
      'input_types' => ['metadata'],
    ];

    $instance = new ValidatorProject(
      $configuration,
      $validator_id,
      $plugin_definition
    );

    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Project trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the Project::setProject() setter and
   * Project::getProject() getter.
   *
   * @return void
   */
  public function testProjectSetterGetter() {
    // Test getter will trigger an error when attempting to get a project
    // prior to a call to project setter method.
    
    // Exception message when failed to set a project.
    $expected_message = 'Cannot retrieve project set by the importer';

    try {
      $this->instance->getProject();
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Project getter method should throw an exception for unset project.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset project.'
    );
    

    // Test invalid project will trigger an exception.

    // Not valid projects:
    $invalid_projects = ['', 0];
    // Exception message when invalid project was provided to the setter.
    $expected_message = 'Invalid project provided.';

    foreach($invalid_projects as $invalid_project) {
      $exception_caught = FALSE;
      $exception_message = '';
      
      try {
        $this->instance->setProject($invalid_project);
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
      
      $this->assertTrue($exception_caught, 'Project setter method should throw an exception for invalid project value.');
      $this->assertStringContainsString(
        sprintf($expected_message, $invalid_project),
        $exception_message,
        'Expected exception message does not match the message when invalid value was passed to the project setter method'
      );
    }

    // A non-existent project id (integer).
    $exception_message = '';

    try {
      $this->instance->setProject(14344);
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Project setter method should throw an exception for non-existent project id.');
    $this->assertStringContainsString(
      $exception_message,
      'The project provided does not exist in chado.project table.',
      'Expected exception message does not match the message when non-existent project id was passed to the project setter method'
    );

    // A non-existent project name (string).
    $exception_caught = FALSE;
    $exception_message = '';
    
    try {
      $this->instance->setProject('IP: Incognito Project');
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    
    $this->assertTrue($exception_caught, 'Project setter method should throw an exception for non-existent project name.');
    $this->assertStringContainsString(
      $exception_message,
      'The project provided does not exist in chado.project table.',
      'Expected exception message does not match the message when non-existent project name was passed to the project setter method'
    );

    
    // Test that an existing project set, the getter will return
    // back the same value using the getter method. The project name or id
    // has been resolved to correct project id and name.

    // Valid project by id (integer):
    $this->instance->setProject($this->test_project['id']);

    $project = $this->instance->getProject();
    $this->assertEquals(
      $project, 
      $this->test_project, 
      'The set project does not match the project returned by the project getter method'
    );

    // Valid project by name (string):
    $this->instance->setProject($this->test_project['name']);

    $project = $this->instance->getProject();
    $this->assertEquals(
      $project, 
      $this->test_project, 
      'The set project does not match the project returned by the project getter method'
    );
  }
}