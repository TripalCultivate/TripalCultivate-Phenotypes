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
   * keyed by project_id and name, respectively.
   *
   * @var array
   */
  protected array $test_project;


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
      'project_id' => $project_id,
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

    // We need to mock the logger to test the progress reporting.
    $mock_logger = $this->getMockBuilder(\Drupal\tripal\Services\TripalLogger::class)
      ->onlyMethods(['notice', 'error'])
      ->getMock();
    $mock_logger->method('notice')
    ->willReturnCallback(function ($message, $context, $options) {
      print str_replace(array_keys($context), $context, $message);
      return NULL;
    });
    $mock_logger->method('error')
    ->willReturnCallback(function ($message, $context, $options) {
      print str_replace(array_keys($context), $context, $message);
      return NULL;
    });
    // Finally, use setLogger() for this validator instance
    $instance->setLogger($mock_logger);

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
    $exception_caught = FALSE;
    $expected_message = 'Cannot retrieve project from the context array as one has not been set by setProject() method.';

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

    // Test that invalid projects will log an error

    // Not valid project id: 0
    $printed_output = '';
    $expected_message = 'The Project Trait requires project id number to be a number greater than 0.';
    ob_start();
    $this->instance->setProject(0);
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      'The logged error message does not have the message we expected when project id of 0 was passed to the setProject() method.'
    );

    // Test the getter method after calling setProject but it failed.
    $exception_caught = FALSE;
    $expected_message = 'Cannot retrieve project from the context array as one has not been set by setProject() method.';

    try {
      $this->instance->getProject();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Project getter method should throw an exception for unset project.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset project.'
    );

    // Not valid project name: Empty string.
    $printed_output = '';
    $expected_message = 'The Project Trait requires project name to be a non-empty string value.';
    ob_start();
    $this->instance->setProject('');
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      'The logged error message does not have the message we expected when project name of empty string was passed to the setProject() method.'
    );

    // Test the getter method after calling setProject but it failed.
    $exception_caught = FALSE;
    $expected_message = 'Cannot retrieve project from the context array as one has not been set by setProject() method.';

    try {
      $this->instance->getProject();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Project getter method should throw an exception for unset project.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset project.'
    );

    // A non-existent project id (integer).
    $printed_output = '';
    $expected_message = 'The Project Trait requires a project that exists in the database.';
    ob_start();
    $this->instance->setProject(14344);
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      'The logged error message does not have the message we expected when non-existent project id was passed to the setProject() method.'
    );

    // Test the getter method after calling setProject but it failed.
    $exception_caught = FALSE;
    $expected_message = 'Cannot retrieve project from the context array as one has not been set by setProject() method.';

    try {
      $this->instance->getProject();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Project getter method should throw an exception for unset project.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset project.'
    );

    // A non-existent project name (string).
    $printed_output = '';
    $expected_message = 'The Project Trait requires a project that exists in the database.';
    ob_start();
    $this->instance->setProject('IP: Incognito Project');
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      'The logged error message does not have the message we expected when a non-existent project name was passed to the setProject() method.'
    );

    // Test the getter method after calling setProject but it failed.
    $exception_caught = FALSE;
    $expected_message = 'Cannot retrieve project from the context array as one has not been set by setProject() method.';

    try {
      $this->instance->getProject();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Project getter method should throw an exception for unset project.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset project.'
    );

    // Test that with an existing project set, the getter will return
    // back the same value using the getter method. The project name or id
    // has been resolved to correct project id and name.

    // Valid project by project id (integer):
    $this->instance->setProject($this->test_project['project_id']);

    $project = $this->instance->getProject();
    $this->assertEquals(
      $project,
      $this->test_project,
      'The set project does not match the project returned by the project getter method.'
    );

    // Valid project by name (string):
    $this->instance->setProject($this->test_project['name']);

    $project = $this->instance->getProject();
    $this->assertEquals(
      $project,
      $this->test_project,
      'The set project does not match the project returned by the project getter method.'
    );
  }
}
