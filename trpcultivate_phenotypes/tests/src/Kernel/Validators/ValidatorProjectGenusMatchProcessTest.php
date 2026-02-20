<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests any message processing methods for the ProjectGenusMatch validator.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
#[Group('trpcultivate_phenotypes')]
#[Group('validators')]
#[RunTestsInSeparateProcesses]
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
   * Drupal render service.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected Renderer $renderer;

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

    // Get our renderer.
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for testProcessItemWithSimpleList().
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
   *     - 'expected_items': An array of the expected failed items.
   */
  public static function provideProjectGenusMatchFailedCases() {
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
        'expected_message' => 'The selected Project does not exist. Please contact your administrator to have this added.',
        'expected_item_count' => 1,
        'expected_items' => [
          'Project: Non-existing project',
        ],
      ],
    ];

    // #1: Project has no genus set.
    $scenarios[] = [
      [
        'case' => 'Project has no genus set and could not compare with the genus provided',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'An existing project',
          'genus_provided' => 'Tripalus',
        ],
      ],
      [],
      [
        'expected_message' => 'The selected Project does not have a genus paired to it. Please contact your administrator to have this set up.',
        'expected_item_count' => 2,
        'expected_items' => [
          'Project: An existing project',
          'Genus: Tripalus',
        ],
      ],
    ];

    // #2: The selected genus is not one of the genus set to project.
    $scenarios[] = [
      [
        'case' => 'Genus does not match a genus set to the project',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'An existing project',
          'genus_provided' => 'Tripalus',
        ],
      ],
      [],
      [
        'expected_message' => 'The selected genus has not been paired to the selected Project. Please select a paired genus or contact your administrator if you think one is missing.',
        'expected_item_count' => 2,
        'expected_items' => [
          'Project: An existing project',
          'Genus: Tripalus',
        ],
      ],
    ];

    // -------- TESTING TOKENS ---------
    // #3: 1 token provided
    $scenarios[] = [
      [
        'case' => 'Project has no genus set and could not compare with the genus provided',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'My Research Experiment',
          'genus_provided' => 'Tripalus',
        ],
      ],
      [
        'project' => 'Research Experiment',
      ],
      [
        'expected_message' => 'The selected Research Experiment does not have a genus paired to it. Please contact your administrator to have this set up.',
        'expected_item_count' => 2,
        'expected_items' => [
          'Research Experiment: My Research Experiment',
          'Genus: Tripalus',
        ],
      ],
    ];

    // #4: 2 tokens provided
    $scenarios[] = [
      [
        'case' => 'Project does not exist',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'False Experiment',
        ],
      ],
      [
        'project' => 'Experiment',
        'contact-admin' => 'email your administrator at admin@email.com',
      ],
      [
        'expected_message' => 'The selected Experiment does not exist. Please email your administrator at admin@email.com to have this added.',
        'expected_item_count' => 1,
        'expected_items' => [
          'Experiment: False Experiment',
        ],
      ],
    ];

    // #5: Customize each of the case messages
    $scenarios[] = [
      [
        'case' => 'Genus does not match a genus set to the project',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'An existing project',
          'genus_provided' => 'Tripalus',
        ],
      ],
      [
        'case-no-project' => 'The project is having an existential crisis.',
        'case-no-paired-genus' => 'The project has no friends.',
        'case-project-genus-mismatch' => 'The genus and project do not get along.',
      ],
      [
        'expected_message' => 'The genus and project do not get along.',
        'expected_item_count' => 2,
        'expected_items' => [
          'Project: An existing project',
          'Genus: Tripalus',
        ],
      ],
    ];

    // #6: Provide a custom case message with tokens
    $scenarios[] = [
      [
        'case' => 'Project has no genus set and could not compare with the genus provided',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'An existing project',
          'genus_provided' => 'Tripalus',
        ],
      ],
      [
        'case-no-paired-genus' => 'The [project] has no friends. Please [contact-admin].',
        'project' => 'Research Experiment',
        // 'contact-admin' token is left as the default.
      ],
      [
        'expected_message' => 'The Research Experiment has no friends. Please contact your administrator.',
        'expected_item_count' => 2,
        'expected_items' => [
          'Research Experiment: An existing project',
          'Genus: Tripalus',
        ],
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ProjectGenusMatch validator.
   *
   * @param array $validation_status
   *   The validation status array that gets passed to the process method. It
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
   *   - 'expected_items': An array of the expected failed items.
   *
   * @dataProvider provideProjectGenusMatchFailedCases
   */
  #[DataProvider('provideProjectGenusMatchFailedCases')]
  public function testProcessItemWithSimpleList(array $validation_status, array $tokens, array $expectations) {

    // Create a plugin instance for this validator.
    $validator_id = 'project_genus_match';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Call the process method on our validation result.
    $render_array = $instance->processItemWithSimpleList($validation_status, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the render array here.
    $selected_message_title = $this->cssSelect('div.tcp-project-genus-match-failures label');
    $provided_message = (string) $selected_message_title[0];
    $this->assertStringContainsString($expectations['expected_message'], $provided_message, 'The message expected from processing ProjectGenusMatch failures for this scenario did not match the one in the rendered output.');

    // Next, check for expected items. Make sure we have the expected 1 item.
    $selected_list_items = $this->cssSelect('div.tcp-project-genus-match-failures ul li');
    $list_item_count = count($selected_list_items);
    $this->assertCount($expectations['expected_item_count'], $selected_list_items, 'We expected ' . $expectations['expected_item_count'] . ' list items in the render array from processing ProjectGenusMatch failures, but instead found ' . $list_item_count . '.');

    // Grab the contents of 'SimpleXMLElement Object' and assert it matches what
    // we expect.
    foreach ($selected_list_items as $provided_item) {
      $provided_item = (string) $provided_item;
      $this->assertContains($provided_item, $expectations['expected_items'], 'The render array from processing ProjectGenusMatch failures did not contain one of the expected failed items.');
    }
  }

  /**
   * Data Provider for triggering exceptions in processItemWithSimpleList().
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
   */
  public static function provideExceptionCases() {
    $scenarios = [];

    // Make tokens an empty array for now. Maybe in the future we'll want to
    // incorporate them into exception messages?
    $tokens = [];

    // #0: Case 'Project exists and project-genus match the genus provided'
    $scenarios[] = [
      [
        'case' => 'Project exists and project-genus match the genus provided',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'Non-existing project',
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ProjectGenusMatch validator implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: Unrecognizable case.
    $scenarios[] = [
      [
        'case' => 'Unrecognizable case string',
        'valid' => FALSE,
        'failedItems' => [
          'project_provided' => 'An existing project',
          'genus_provided' => 'Tripalus',
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ProjectGenusMatch validator is not recognized as a potential case.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests for exceptions thrown for passed and unrecognizable case strings.
   *
   * @param array $validation_status
   *   The validation status array that gets passed to the process method. It
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
   *   An array of expectations in the rendered output which has the following
   *   keys:
   *   - 'expected_message': The exception message that is expected to be
   *     triggered.
   *
   * @dataProvider provideExceptionCases
   */
  #[DataProvider('provideExceptionCases')]
  public function testProcessItemWithSimpleListExceptions(array $validation_status, array $tokens, array $expectations) {

    // Create a plugin instance for this validator.
    $validator_id = 'project_genus_match';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Call the process method on our validation result.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $instance->processItemWithSimpleList($validation_status, $tokens);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      'We expected an exception to be caught for case ' . $validation_status['case'] . 'but one was not thrown.',
    );
    $this->assertEquals(
      $expectations['expected_message'],
      $exception_message,
      "We expected the exception message to indicate that case " . $validation_status['case'] . " occurred, but the message does not match what was expected.",
    );
  }

}
