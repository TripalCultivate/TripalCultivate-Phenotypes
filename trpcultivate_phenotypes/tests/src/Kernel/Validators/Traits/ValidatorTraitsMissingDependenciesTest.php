<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOConnection;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOServiceGenusontology;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOServiceTraits;

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
   * DATA PROVIDER: provides fake validators to be tested for bad configuration.
   *
   * @return array
   *   Returns an array of senarios where each one has a 'case', 'validator_defn',
   *   and 'expected_message'. Specifically.
   *     - case: a short senario description for the assert fail messages.
   *     - validator_defn: the plugin definition to use when creating the validator.
   *       Must include 'id', 'validator_name', 'input_types', 'class'.
   *     - expected_message: the exception message expected when trying to set
   *       the genus for a badly configured validator.
   */
  public function provideBadlyConfiguredValidators() {
    $senarios = [];

    $senarios['no_connection'] = [
      'case' => 'validator without the chado connection set',
      'validator_defn' => [
        'id' => 'validator_configured_genus_no_connection',
        'validator_name' => 'Validator Using GenusConfigured Trait',
        'input_types' => ['header-row', 'data-row'],
        'class' => '\Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOConnection',
      ],
      'expected_message' => 'GenusConfigured Trait needs an instance of ChadoConnection',
    ];

    $senarios['no_genus_ontolgy'] = [
      'case' => 'validator without the genus ontology service set',
      'validator_defn' => [
        'id' => 'validator_configured_genus_no_service_genusontology',
        'validator_name' => 'Validator Using GenusConfigured Trait',
        'input_types' => ['header-row', 'data-row'],
        'class' => '\Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOServiceGenusontology',
      ],
      'expected_message' => 'GenusConfigured Trait needs the Genus ontology (trpcultivate_phenotypes.genus_ontology) service',
    ];

    $senarios['no_traits'] = [
      'case' => 'validator without the trait service set',
      'validator_defn' => [
        'id' => 'validator_configured_genus_no_service_trait',
        'validator_name' => 'Validator Using GenusConfigured Trait',
        'input_types' => ['header-row', 'data-row'],
        'class' => '\Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorGenusConfiguredNOServiceTraits',
      ],
      'expected_message' => 'GenusConfigured Trait needs the Trait (trpcultivate_phenotypes.traits) service',
    ];

    return $senarios;
  }

/**
 * Tests the GenusConfigured::setConfiguredGenus() setter
 *       and GenusConfigured::getConfiguredGenus() getter
 *
 * @dataProvider provideBadlyConfiguredValidators
 *
 * @param string $case
 *   A short senario description for the assert fail messages.
 * @param array $validator_defn
 *   The plugin definition to use when creating the validator.
 *   Must include
 *    - id: the fake validator id in the annotation
 *    - validator_name: the name of the validator in the annotation
 *    - input_types: an array of input-types this fake validator supports
 *    - class: the name of the class this fake validator is in with full namespace.
 * @param string $expected_message
 *   The exception message expected when trying to set the genus for a badly
 *   configured validator.
 */
  public function testConfiguredGenusBadlyConfigured($case, $validator_defn, $expected_message) {

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = $validator_defn['id'];
    $validator_class = $validator_defn['class'];
    $instance = new $validator_class(
      $configuration,
      $validator_defn['id'],
      $validator_defn,
      $this->chado_connection,
      $this->container->get('trpcultivate_phenotypes.genus_ontology'),
      $this->container->get('trpcultivate_phenotypes.traits')
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the GenusConfigured trait for '$case'."
    );

    // Now try setting the genus when the dependencies are not met.
    // We expect an exception to be thrown before setting is even attempted
    // so the genus passed in here doesn't matter.
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
      "Calling setConfiguredGenus() on a '$case' should throw an exception."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected for a '$case'."
    );
  }
}
