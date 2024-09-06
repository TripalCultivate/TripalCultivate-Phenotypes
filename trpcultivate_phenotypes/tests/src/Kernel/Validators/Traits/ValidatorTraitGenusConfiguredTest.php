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
   * The ontology terms that have been configured for our genus.
   * NOTE: These will be created in setup.
   *
   * @var array
   */
  protected array $ontology_terms;

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
    $this->ontology_terms = $this->setOntologyConfig($this->configured_genus);
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
      "Unable to create $validator_id validator instance to test the GenusConfigured trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the GenusConfigured::setConfiguredGenus() setter
   *       and GenusConfigured::getConfiguredGenus() getter
   *
   * @return void
   */
  public function testConfiguredGenusSetterGetter() {

    // Check a NOT EXISTENT genus in a well setup validator.
    $genus = uniqid();
    $printed_output = '';
    $expected_message = "The genus '$genus' does not exist in chado";
    ob_start();
    $this->instance->setConfiguredGenus($genus);
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      "The exception thrown does not have the message we expected for a genus that doesn't even exist in chado."
    );

    // Check that a genus has NOT been set by using getConfguredGenus()
    $expected_message = "Cannot retrieve the genus name as one has not been set";
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

    // Check that ontology terms for a non-configured genus have also not been set
    $expected_message = "Cannot retrieve the ontology terms of the genus as one has not been set";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getConfiguredGenusOntologyTerms();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling getConfiguredGenusOntologyTerms() when a genus has not been succesfully set yet (after attempting to set a non-existing genus) should have thrown an exception but didn't."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when trying to get ontology terms for a configured genus but one hasn't been set yet."
    );

    // Check a NOT CONFIGURED genus in a well setup validator.
    $printed_output = '';
    $expected_message = "The genus '" . $this->existing_genus . "' is not configured";
    ob_start();
    $this->instance->setConfiguredGenus($this->existing_genus);
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      "The exception thrown does not have the message we expected for a genus existing in chado that is not configured."
    );

    // Check that a genus still has NOT been set by using getConfguredGenus()
    $expected_message = "Cannot retrieve the genus name as one has not been set";
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
      "Could not grab the configured genus using getConfiguredGenus() despite having called setConfiguredGenus() with a valid configured genus."
    );

    // Check that the ontology cvterm IDs can be retrieved for our configured genus
    $grabbed_genus_ontology_terms = $this->instance->getConfiguredGenusOntologyTerms();
    foreach($grabbed_genus_ontology_terms as $term => $grabbed_id) {
      $expected_id = NULL;
      if(array_key_exists('db_id', $this->ontology_terms[$term])){
        $expected_id = $this->ontology_terms[$term]['db_id'];
      } else if (array_key_exists('cv_id', $this->ontology_terms[$term])){
        $expected_id = $this->ontology_terms[$term]['cv_id'];
      }
      $this->assertNotNull(
        $expected_id,
        "Could not find the db_id or cv_id for term '$term' in the ontology terms configured at setup."
      );
      $this->assertEquals(
        $expected_id,
        $grabbed_id,
        "Could not grab the configured ontology term '$term' for our genus using getConfiguredGenusOntologyTerms() despite having called setConfiguredGenus() with a valid configured genus."
      );
    }
  }
}
