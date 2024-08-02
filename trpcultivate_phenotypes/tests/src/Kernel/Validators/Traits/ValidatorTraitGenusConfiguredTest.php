<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfigured;

 /**
  * Tests the GenusConfigured validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitGenusConfiguredTest extends ChadoTestKernelBase {

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
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The validator instance to use for testing.
   *
   * @var ValidatorGenusConfigured
   */
  protected ValidatorGenusConfigured $instance;

  /**
   * The genus we will configure and test this validator trait with.
   *
   * @var string
   */
  protected string $configured_genus = 'Tripalus';

  /**
   * A genus in the chado instance but not configured.
   *
   * @var string
   */
  protected string $existing_genus = 'Citrus';

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

    // Configure the module.
    $organism_id = $this->chado_connection->insert('1:organism')
    ->fields([
      'genus' => $this->configured_genus,
      'species' => uniqid(),
    ])
      ->execute();
    $this->assertIsNumeric(
      $organism_id,
      "We were not able to create an organism for testing."
    );
    $this->setOntologyConfig($this->configured_genus);
    $this->setTermConfig();

    // Create another organism but DONT configure this genus.
    $organism_id = $this->chado_connection->insert('1:organism')
    ->fields([
      'genus' => $this->existing_genus,
      'species' => uniqid(),
    ])
      ->execute();
    $this->assertIsNumeric(
      $organism_id,
      "We were not able to create an organism for testing."
    );

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_configured_genus';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using GenusConfigured Trait',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorGenusConfigured(
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

    $this->instance = $instance;
  }

  /**
   * Tests the GenusConfigured::setConfiguredGenus() setter
   *
   * @return void
   */
  public function testSetter() {

    // Check a NOT EXISTENT genus in a well setup validator.
    $genus = uniqid();
    $expected_message = "genus '$genus' does not exist in chado";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setConfiguredGenus($genus);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling setConfiguredGenus() with genus that is not in chado should have thrown an exception but didn't."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected for a genus that doesn't even exist in chado."
    );

    // Check that a genus has NOT been set by using getConfguredGenus()
    $expected_message = "Cannot retrieve the genus as one has not been set by the setGenusConfigured() method.";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getConfiguredGenus();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling getConfiguredGenus() when a genus has not been succesfully set yet (after attempting to set a non-existing genus) should have thrown an exception but didn't."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when trying to get a configured genus but one hasn't been set yet."
    );

    // Check a NOT CONFIGURED genus in a well setup validator.
    $expected_message = "genus '" . $this->existing_genus . "' is not configured";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setConfiguredGenus($this->existing_genus);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling setConfiguredGenus() with an existing genus that is not configured should have thrown an exception but didn't."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected for a genus existing in chado that is not configured."
    );

    // Check that a genus still has NOT been set by using getConfguredGenus()
    $expected_message = "Cannot retrieve the genus as one has not been set by the setGenusConfigured() method.";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getConfiguredGenus();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling getConfiguredGenus() when a genus has not been succesfully set yet (after attempting to set an existing but not configured genus) should have thrown an exception but didn't."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when trying to get a configured genus but one hasn't been set yet."
    );

    // Check a CONFIGURED genus in a well setup validator.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setConfiguredGenus($this->configured_genus);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling setConfiguredGenus() with a configured genus should not have thrown an exception but it threw '$exception_message'"
    );

    // Check that the genus was set correctly by using getConfiguredGenus()
    $grabbed_genus = $this->instance->getConfiguredGenus();
    $this->assertEquals(
      $this->configured_genus,
      $grabbed_genus,
      "Could not grab the configured genus using getGenusConfigured() despite having called setConfiguredGenus() with a valid configured genus."
    );
  }
}
