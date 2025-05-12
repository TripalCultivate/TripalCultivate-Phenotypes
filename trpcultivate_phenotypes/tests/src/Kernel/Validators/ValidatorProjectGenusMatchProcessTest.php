<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests any message processing methods for the ProjectGenusMatch validator.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
class ValidatorProjectGenusMatchProcessTest extends ChadoTestKernelBase {

  /**
   * Plugin Manager service.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The genus for configuring and testing with our validator.
   *
   * @var string
   */
  protected string $genus = 'Tripalus';

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

  }

  /**
   * Data Provider for testProcessSimpleList().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The validation result array that gets passed to the process method. It
   *     contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'project_provided': The name of the project provided.
   *       - 'genus_provided': The name of the genus provided.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_item_count': The number of failed items expected.
   *     - 'expected_item': The expected failed item.
   */
  public function provideProjectGenusMatchFailedCases() {
    $scenarios = [];

    // ------ DEFAULT CASES (no tokens) ------
    // #0: The project does not exist.
    $scenarios[] = [
      [
        'case' => 'Project does not exist',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'Non-existing project',
        ],
      ],
      [],
      [
        'expected_message' => 'The selected genus does not exist in this site.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ProjectGenusMatch validator.
   *
   * @param array $validation_result
   *   The validation result array that gets passed to the process method. It
   *   contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'project_provided': The name of the project provided.
   *     - 'genus_provided': The name of the genus provided.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of the expected items in the rendered output. It has the
   *   following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *   - 'expected_item_count': The number of failed items expected.
   *   - 'expected_item': The expected failed item.
   *
   * @dataProvider provideProjectGenusMatchFailedCases
   */
  public function testProcessSimpleList(array $validation_result, array $tokens, array $expectations) {

    // Create a plugin instance for this validator.
    $validator_id = 'project_genus_match';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Call the process method on our validation result.
    $render_array = $instance->processSimpleList($validation_result, $tokens);
  }

}
