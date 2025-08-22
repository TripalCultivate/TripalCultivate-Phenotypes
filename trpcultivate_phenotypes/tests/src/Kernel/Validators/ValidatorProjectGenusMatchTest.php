<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Tripal Cultivate Phenotypes Project-Genus Match Validator Plugin.
 *
 * @group trpcultivate_phenotypes
 * @group validators
 */
#[Group('trpcultivate_phenotypes')]
#[Group('validators')]
class ValidatorProjectGenusMatchTest extends ChadoTestKernelBase {

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
   * An array of project-genus input values for testing.
   *
   * Has the following keys:
   * - 'with-config-genus': a project with a configured genus.
   * - 'with-more-configgenus': a project with multiple configured genus.
   * - 'with-unconfig-genus': a project with unconfigured genus.
   * - 'with-no-genus': a project without a genus.
   * - 'with-other-term': project with project-genus set not through phenotypes.
   * - 'non-existent': a project that does not exist.
   * - 'conflicting-genus': a project with set genus but is tested with another.
   *
   * @var array
   */
  private array $test_project_genus;

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

    $this->setTermConfig();
    $genus_term = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.ontology.terms.genus');

    $test_project_genus = [
      'with-config-genus' => ['Tripalus'],
      'with-more-configgenus' => ['Plantanus', 'Tripalus', 'Pinus'],
      'with-unconfig-genus' => ['notconfiggenus'],
      'with-no-genus' => [''],
      'with-other-term' => ['Tripalus'],
    ];

    foreach ($test_project_genus as $type => $genus) {
      // Create the project.
      $project = 'Project: ' . $type;
      $project_id = $this->chado_connection->insert('1:project')
        ->fields([
          'name' => $project,
          'description' => 'A test project',
        ])
        ->execute();

      $this->assertIsNumeric($project_id, 'We were not able to create a project ' . $project . ' for testing.');
      $this->test_project_genus[$type]['project_id'] = $project_id;
      $this->test_project_genus[$type]['name'] = $project;

      // Create and set each genus.
      $genus_list = [];
      foreach ($genus as $i => $genus_ins) {
        if (empty($genus_ins)) {
          continue;
        }

        $organism_id = $this->chado_connection->insert('1:organism')
          ->fields([
            'genus' => $genus_ins,
            'species' => 'species:' . $type,
          ])
          ->execute();

        $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing: ' . $genus_ins);

        if ($genus_ins != 'notconfiggenus') {
          $this->setOntologyConfig($genus_ins);
        }

        $project_genus_id = $this->chado_connection->insert('1:projectprop')
          ->fields([
            'project_id' => $project_id,
            'type_id' => ($type == 'with-other-term') ? 1 : $genus_term,
            'value' => $genus_ins,
            'rank' => $i + 1,
          ])
          ->execute();

        $this->assertIsNumeric($project_genus_id, 'We were not able to create an project-genus property for testing.');
        $genus_list[] = $genus_ins;
      }

      $this->test_project_genus[$type]['genus'] = $genus_list;
    }

    // Adds a non-existent project.
    $this->test_project_genus['non-existent'] = [
      'project_id' => 999,
      'name' => 'A Spurious Project',
      'genus' => ['Tripalus'],
    ];

    // Adds an existing project with configured genus but using a genus
    // of another project.
    $project = $this->test_project_genus['with-config-genus'];
    $this->test_project_genus['conflicting-genus'] = [
      'project_id' => $project['project_id'],
      'name' => $project['name'],
      'genus' => ['Pinus'],
    ];

    // Set plugin manager service.
    $this->plugin_manager = $this->container->get('plugin.manager.trpcultivate_validator');
  }

  /**
   * Data Provider: provides project-genus test form values.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - Form values input.
   *   - An array of expected values, with the following keys:
   *     - 'has_exception': TRUE if exception is expected and FALSE if not.
   *     - 'error_message': the expected exception message.
   */
  public static function provideProjectGenusTestFormValue() {

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

      // #1: No project field in the form values.
      [
        'No genus field element',
        [
          'genus' => 'Lens',
        ],
        [
          'has_exception' => TRUE,
          'error_message' => 'Failed to locate project field element',
        ],
      ],

      // #2: No genus field in the form values.
      [
        'No genus field element',
        [
          'project' => 999,
        ],
        [
          'has_exception' => TRUE,
          'error_message' => 'Failed to locate genus field element',
        ],
      ],

      // #3: FormState object passed.
      [
        'Drupal FormState object',
        new FormState(),
        [
          'has_exception' => TRUE,
          'error_message' => 'Argument #1 ($form_values) must be of type array, Drupal\Core\Form\FormState given',
        ],
      ],

      // #4: Input values are valid.
      [
        'Input values are valid',
        [
          'project' => 1,
          'genus' => 'Genus',
        ],
        [
          'has_exception' => FALSE,
          'error_message' => '',
        ],
      ],
    ];
  }

  /**
   * Test project genus match validator input requirements.
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
   * @dataProvider provideProjectGenusTestFormValue
   */
  #[DataProvider('provideProjectGenusTestFormValue')]
  public function testProjectGenusInputValue($scenario, $form_values, $expected) {

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      $this->plugin_manager->createInstance('project_genus_match')
        ->validateMetadata($form_values);
    }
    catch (\Throwable $e) {
      $exception_caught = TRUE;
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
   * Data Provider: provides project-genus test input values.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - Array key to reference an element in the $test_project_genus property.
   *   - An array of expected values, with the following keys:
   *     - 'case': the case title that evaluated the project/genus value.
   *     - 'valid': the validation status value returned.
   *     - 'failedItems': input items that failed validation, including the
   *       project and/or genus input values provided.
   */
  public static function provideTestProjectGenusInput() {

    return [
      // #0: A non-existent project.
      [
        'Non-existent project',
        'non-existent',
        [
          'case' => 'Project does not exist',
          'valid' => FALSE,
          'failedItems' => [
            'project_provided' => 'project',
          ],
        ],
      ],

      // #1: A project without genus attached.
      [
        'Project without genus',
        'with-no-genus',
        [
          'case' => 'Project has no genus set and could not compare with the genus provided',
          'valid' => FALSE,
          'failedItems' => [
            'project_provided' => 'project',
            'genus_provided' => 'genus',
          ],
        ],
      ],

      // #2: Using a genus not set to a project.
      [
        'Incorrect genus for a project',
        'conflicting-genus',
        [
          'case' => 'Genus does not match a genus set to the project',
          'valid' => FALSE,
          'failedItems' => [
            'project_provided' => 'project',
            'genus_provided' => 'genus',
          ],
        ],
      ],

      // #3: A genus set to a project not through the phenotypes module.
      [
        'Project-genus set not through phenotypes',
        'with-other-term',
        [
          'case' => 'Project has no genus set and could not compare with the genus provided',
          'valid' => FALSE,
          'failedItems' => [
            'project_provided' => 'project',
            'genus_provided' => 'genus',
          ],
        ],
      ],

      // #4: A project set with a non-configured genus.
      [
        'Unconfigured genus to a project',
        'with-unconfig-genus',
        [
          'case' => 'Project has no genus set and could not compare with the genus provided',
          'valid' => FALSE,
          'failedItems' => [
            'project_provided' => 'project',
            'genus_provided' => 'genus',
          ],
        ],
      ],

      // #5: Project and genus matched.
      [
        'A project-genus match',
        'with-config-genus',
        [
          'case' => 'Project exists and project-genus match the genus provided',
          'valid' => TRUE,
          'failedItems' => [],
        ],
      ],

      // #6: Project and genus matched from a project with multiple genus set.
      [
        'A project-genus match from multiple genus',
        'with-more-configgenus',
        [
          'case' => 'Project exists and project-genus match the genus provided',
          'valid' => TRUE,
          'failedItems' => [],
        ],
      ],
    ];
  }

  /**
   * Test project-genus match validator.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param string $project_genus_input
   *   Array key to reference a element in the $test_project_genus property.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'case': the case title that evaluated the project/genus value.
   *     - 'valid': the validation status value returned.
   *     - 'failedItems': input items that failed validation, including the
   *       project and/or genus input values provided.
   *
   * @dataProvider provideTestProjectGenusInput
   */
  #[DataProvider('provideTestProjectGenusInput')]
  public function testValidatorProjectGenusMatch($scenario, $project_genus_input, $expected) {

    $input_values = $this->test_project_genus[$project_genus_input];
    $genus = reset($input_values['genus']);

    // Project value can be the project id or project name. Test for both cases.
    foreach (['project_id', 'name'] as $project_input) {
      $project = $input_values[$project_input];

      $form_values = ['project' => $project, 'genus' => $genus];
      $validation_status = $this->plugin_manager->createInstance('project_genus_match')
        ->validateMetadata($form_values);

      $this->assertEquals(
        $expected['case'],
        $validation_status['case'],
        'Project-genus match validator case title does not match expected title in scenario: ' . $scenario
      );

      $this->assertEquals(
        $expected['valid'],
        $validation_status['valid'],
        'The validation status value does not match expected value in scenario: ' . $scenario
      );

      if (!$validation_status['valid']) {
        $expected_failed_items = [];
        foreach ($expected['failedItems'] as $item => $input_key) {
          $expected_failed_items[$item] = $form_values[$input_key];
        }

        $this->assertEquals(
          $expected_failed_items,
          $validation_status['failedItems'],
          'Failed test value does not match expected failed items in scenario: ' . $scenario
        );
      }
    }
  }

}
