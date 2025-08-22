<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\BasicallyBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Tripal Cultivate Phenotypes Validator Base functions.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
#[Group('trpcultivate_phenotypes')]
#[Group('validators')]
class ValidatorBaseTest extends ChadoTestKernelBase {

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration.
   *
   * @var config_entity
   */
  private $config;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'file',
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
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
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);
  }

  /**
   * Test the basic getters.
   *
   * - getConfigAllowNew()
   */
  public function testBasicValidatorGetters() {

    $configuration = [];
    $validator_id = 'fake_basically_base';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Basically Base Validator',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new BasicallyBase($configuration, $validator_id, $plugin_definition);
    $this->assertIsObject(
      $instance,
      "Unable to create fake_basically_base validator instance to test the base class."
    );

    // Check that we are able to get the configuration for allowing new traits.
    // NOTE: this is set by the admin in the ontology config form and doesn't
    // change between importers.
    $expected_allownew = TRUE;
    $returned_allownew = $instance->getConfigAllowNew();
    $this->assertEquals($expected_allownew, $returned_allownew,
      "We did not get the status for Allowing New configuration that we expected through the $validator_id validator.");
  }

}
