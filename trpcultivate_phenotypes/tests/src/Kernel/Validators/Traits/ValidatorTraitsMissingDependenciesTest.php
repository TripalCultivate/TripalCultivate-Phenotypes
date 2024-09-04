<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOConnection;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOService;

/**
 * Tests the GenusConfigured validator trait.
 *
 * @group trpcultivate_phenotypes
 * @group validator_traits
 */
class ValidatorTraitsMissingDependenciesTest extends ChadoTestKernelBase {

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
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);
  }

  /**
   * Tests the GenusConfigured::setConfiguredGenus() setter
   *       and GenusConfigured::getConfiguredGenus() getter
   *
   * @return void
   */
  public function testConfiguredGenusBadlyConfigured() {

    // Bad Validator: No $chado_connection.
    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_configured_genus_no_connection';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using GenusConfigured Trait But Missing Connection',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorGenusConfiguredNOConnection(
      $configuration,
      $validator_id,
      $plugin_definition,
      $this->chado_connection,
      $this->container->get('trpcultivate_phenotypes.genus_ontology')
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the GenusConfigured trait."
    );

    // Now try setting the genus when the dependencies are not met.
    // We expect an exception to be thrown before setting is even attempted
    // so the genus passed in here doesn't matter.
    $expected_message = "GenusConfigured Trait needs an instance of ChadoConnection";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $instance->setConfiguredGenus('Tripalus');
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling setConfiguredGenus() on a validator that has not set the chado connection should throw an exception."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected for a validator without the chado connection set."
    );

    // Bad Validator: No trpcultivate_phenotypes.genus_ontology service
    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_configured_genus_no_service';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using GenusConfigured Trait But Missing Genus Service',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorGenusConfiguredNOService(
      $configuration,
      $validator_id,
      $plugin_definition,
      $this->chado_connection,
      $this->container->get('trpcultivate_phenotypes.genus_ontology')
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the GenusConfigured trait."
    );

    // Now try setting the genus when the dependencies are not met.
    // We expect an exception to be thrown before setting is even attempted
    // so the genus passed in here doesn't matter.
    $expected_message = "GenusConfigured Trait needs the Genus ontology";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $instance->setConfiguredGenus('Tripalus');
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling setConfiguredGenus() on a validator that has not set the genus ontology service should throw an exception."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected for a validator without the genus ontology service set."
    );
  }
}
