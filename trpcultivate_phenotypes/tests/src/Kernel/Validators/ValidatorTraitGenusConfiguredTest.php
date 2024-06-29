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
   * Tripal DBX Chado Connection object
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

    // Check a configured genus.
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
  }
}
