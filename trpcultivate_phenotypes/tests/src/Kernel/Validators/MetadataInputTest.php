<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests Tripal Cultivate Phenotypes Metadata Validator Plugins.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
class MetadataInputTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * The Validators plugin manager for creating new validator instances.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * An array of genus for testing.
   *
   * Has the following keys:
   * - 'configured': a configured genus.
   * - 'not-created': a genus not created.
   * - 'not-configured': a genus that is not configured.
   *
   * @var array
   */
  private array $test_genus;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes', 'trpcultivate']);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    $genus = 'Tripalus';
    // Create our organism and configure it.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (configured).');
    $this->test_genus['configured'] = $genus;
    $this->setOntologyConfig($this->test_genus['configured']);

    // Create another organism and not configure.
    $genus = 'notconfiggenus';
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing (not configured).');
    $this->test_genus['not-configured'] = $genus;

    // Not created.
    $this->test_genus['not-created'] = 'Pinus';

    // Set plugin manager service.
    $this->plugin_manager = $this->container->get('plugin.manager.trpcultivate_validator');
  }

  /**
   * Data Provider: provides genus test form values.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - Form values input.
   *   - An array of expected values, with the following keys:
   *     - 'has_exception': TRUE if exception is expected and FALSE if not.
   *     - 'error_message': the expected exception message.
   */
  public function provideGenusTestFormValue() {

    return [
      // #0: An empty string as form values.
      [
        'A string value',
        '',
        [
          'has_exception' => TRUE,
          'error_message' => 'Argument #1 ($form_values) must be of type array, string given',
        ],
      ],

      // #1: No genus field in the form values.
      [
        'No genus field element',
        [
          'project_id' => 999,
          'file_id' => 7,
          'genes' => TRUE,
        ],
        [
          'has_exception' => TRUE,
          'error_message' => 'Failed to locate genus field element. GenusExists validator expects a form field element name genus',
        ],
      ],

      // #2: FormState object passed.
      [
        'Drupal FormState object',
        new FormState(),
        [
          'has_exception' => TRUE,
          'error_message' => 'Argument #1 ($form_values) must be of type array, Drupal\Core\Form\FormState given',
        ],
      ],

      // #3: Input value is good.
      [
        'Input value is valid',
        [
          'project_id' => 999,
          'file_id' => 7,
          'genus' => 'Tripalus',
          'genes' => TRUE,
        ],
        [
          'has_exception' => FALSE,
          'error_message' => '',
        ],
      ],
    ];
  }

  /**
   * Test genus exists validator input requirements.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param mixed $form_values
   *   Form values input.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'has_exception': TRUE if exception is expected and FALSE if not.
   *     - 'error_message': the expected exception message.
   *
   * @dataProvider provideGenusTestFormValue
   */
  public function testGenusExistsInputValue($scenario, $form_values, $expected) {

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      $this->plugin_manager->createInstance('genus_exists')
        ->validateMetadata($form_values);
    }
    catch (\Throwable $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertEquals(
      $expected['has_exception'],
      $exception_caught,
      'Failed to catch exception in scenario: ' . $scenario
    );

    $this->assertStringContainsString(
      $expected['error_message'],
      $exception_message,
      'Expected exception message does not match expected error message in scenario: ' . $scenario
    );
  }

  /**
   * Data Provider: provides genus test input value.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - Array key to reference a element in the $test_genus property.
   *   - An array of expected values, with the following keys:
   *     - 'case': the case title that evaluated the genus value.
   *     - 'valid': the validation status value returned.
   *     - 'failedItems': genus input value that failed validation.
   */
  public function provideTestGenusInput() {

    return [
      // #0: A non-existent genus.
      [
        'Non-existent genus',
        'not-created',
        [
          'case' => 'Genus does not exist',
          'valid' => FALSE,
          'failedItems' => [
            'genus_provided' => 'not-created',
          ],
        ],
      ],

      // #1: Genus is not configured.
      [
        'Not configured genus',
        'not-configured',
        [
          'case' => 'Genus exists but is not configured',
          'valid' => FALSE,
          'failedItems' => [
            'genus_provided' => 'not-configured',
          ],
        ],
      ],

      // #2: Exists and configured genus.
      [
        'A valid genus',
        'configured',
        [
          'case' => 'Genus exists and is configured with phenotypes',
          'valid' => TRUE,
          'failedItems' => [],
        ],
      ],
    ];
  }

  /**
   * Test genus exists validator.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param string $genus_input
   *   Array key to reference a element in the $test_genus property.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'case': the case title that evaluated the genus value.
   *     - 'valid': the validation status value returned.
   *     - 'failedItems':  genus input value that failed validation.
   *
   * @dataProvider provideTestGenusInput
   */
  public function testValidatorGenusExists($scenario, $genus_input, $expected) {

    $form_values = ['genus' => $this->test_genus[$genus_input]];

    $validation_status = $this->plugin_manager->createInstance('genus_exists')
      ->validateMetadata($form_values);

    $this->assertEquals(
      $expected['case'],
      $validation_status['case'],
      'Genus exists validator case title does not match expected title in scenario: ' . $scenario
    );

    $this->assertEquals(
      $expected['valid'],
      $validation_status['valid'],
      'The validation status value does not match expected value in scenario: ' . $scenario
    );

    if (!$validation_status['valid']) {
      // Resolve the genus.
      $key = $expected['failedItems']['genus_provided'];
      $expected['failedItems']['genus_provided'] = $this->test_genus[$key];

      $this->assertEquals(
        $expected['failedItems'],
        $validation_status['failedItems'],
        'Failed genus value does not match expected failed items in scenario: ' . $scenario
      );
    }
  }

}
