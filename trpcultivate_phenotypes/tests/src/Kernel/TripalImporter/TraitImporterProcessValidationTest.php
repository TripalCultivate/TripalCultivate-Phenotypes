<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\KernelTests\AssertContentTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Plugin\TripalImporter\TripalCultivatePhenotypesTraitsImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests processValidationMessages() and related methods in the Traits Importer.
 *
 * @group traitsImporter
 */
#[Group('traitsImporter')]
class TraitImporterProcessValidationTest extends ChadoTestKernelBase {

  use AssertContentTrait;
  use PhenotypeImporterTestTrait;
  use UserCreationTrait;

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
   * Drupal render service.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Our instance of the Traits Importer for testing.
   *
   * @var Drupal\trpcultivate_phenotypes\Plugin\TripalImporter\TripalCultivatePhenotypesTraitsImporter
   */
  protected TripalCultivatePhenotypesTraitsImporter $importer;

  /**
   * A default listing of annotations associated with our importer.
   *
   * @var array
   */
  protected $definitions = [
    'test-trait-importer' => [
      'id' => 'trpcultivate-phenotypes-traits-importer',
      'label' => 'Tripal Cultivate: Phenotypic Trait Importer',
      'description' => 'Loads Traits for phenotypic data into the system. This is useful for large phenotypic datasets to ease the upload process.',
      'file_types' => ["tsv"],
      'use_analysis' => FALSE,
      'require_analysis' => FALSE,
      'upload_title' => 'Phenotypic Trait Data File*',
      'upload_description' => 'This should not be visible!',
      'button_text' => 'Import',
      'file_upload' => TRUE,
      'file_load' => FALSE,
      'file_remote' => FALSE,
      'file_required' => FALSE,
      'cardinality' => 1,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Open connection to Chado.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Ensure we can access file_managed related functionality from Drupal.
    // ... users need access to system.action config?
    $this->installConfig(['system', 'trpcultivate_phenotypes', 'trpcultivate']);
    // ... managed files are associated with a user.
    $this->installEntitySchema('user');
    // ... Finally the file module + tables itself.
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('tripal_chado', ['tripal_custom_tables']);
    // Ensure we have our tripal import tables.
    $this->installSchema('tripal', ['tripal_import', 'tripal_jobs']);
    // Create and log-in a user.
    $this->setUpCurrentUser();

    // Mock the logger to test if logged messages occur where we expect.
    $container = \Drupal::getContainer();
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['notice', 'error', 'info'])
      ->getMock();
    $mock_logger->method('error')
      ->willReturnCallback(function ($message, $context, $options) {
        // @todo Revisit print out of log messages, but perhaps setting an option
        // for log messages to not print to the UI?
        // print str_replace(array_keys($context), $context, $message);
        return NULL;
      });
    // Mock the 'info' log type as well.
    $container->set('tripal.logger', $mock_logger);

    // Get our renderer.
    $this->renderer = $this->container->get('renderer');

    // Create an instance of the Traits Importer.
    $this->importer = new TripalCultivatePhenotypesTraitsImporter(
      [],
      'trpcultivate-phenotypes-traits-importer',
      $this->definitions,
      $this->chado_connection,
      $this->container->get('trpcultivate_phenotypes.genus_ontology'),
      $this->container->get('trpcultivate_phenotypes.traits'),
      $this->container->get('plugin.manager.trpcultivate_validator'),
      $this->container->get('trpcultivate.template_generator'),
      $this->container->get('entity_type.manager'),
      $this->renderer,
      $this->container->get('messenger'),
    );
  }

  /**
   * Data Provider for testProcessGenusExistsFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The validation result array that gets passed to the process method. It
   *     contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'genus_provided': The name of the genus provided.
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   */
  public static function provideGenusExistsFailedCases() {
    $scenarios = [];

    // #0: The genus does not exist
    $scenarios[] = [
      [
        'case' => 'Genus does not exist',
        'valid' => FALSE,
        'failedItems' => [
          'genus_provided' => 'Tripalus',
        ],
      ],
      [
        'expected_message' => 'The selected genus does not exist in this site.',
      ],
    ];

    // #1: The genus exists but is not configured.
    $scenarios[] = [
      [
        'case' => 'Genus exists but is not configured',
        'valid' => FALSE,
        'failedItems' => [
          'genus_provided' => 'Tripalus',
        ],
      ],
      [
        'expected_message' => 'The selected genus has not yet been configured for use with phenotypic data.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the GenusExists validator.
   *
   * @param array $validation_result
   *   The validation result array that gets passed to the process method. It
   *     contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'genus_provided': The name of the genus provided.
   * @param array $expectations
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *
   * @dataProvider provideGenusExistsFailedCases
   */
  #[DataProvider('provideGenusExistsFailedCases')]
  public function testProcessGenusExistsFailures(array $validation_result, array $expectations) {
    // Call the process method on our validation result.
    $render_array = $this->importer->processGenusExistsFailures($validation_result);
    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the render array here.
    $selected_message_title = $this->cssSelect('div.tcp-genus-exists-failures label');
    $provided_message = (string) $selected_message_title[0];
    $this->assertStringContainsString($expectations['expected_message'], $provided_message, 'The message expected from processing GenusExists failures for this scenario did not match the one in the rendered output.');
    // Check for an unordered list with one item in it - the genus provided.
    $selected_list_items = $this->cssSelect('div.tcp-genus-exists-failures ul li');
    $this->assertCount(1, $selected_list_items, 'We expect only one list item in the render array from processing GenusExists failures.');
    // Grab the contents of 'SimpleXMLElement Object' and assert it is our
    // genus.
    $provided_genus = (string) $selected_list_items[0];
    $this->assertEquals('Tripalus', $provided_genus, 'The render array from processing GenusExists failures did not contain the expected genus.');
  }

  /**
   * Data Provider for testProcessDuplicateTraitsFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The failures array that gets passed to the process method. It contains
   *     the following keys:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'combo_provided': The combination of trait, method, and unit
   *           provided in the file. The keys used are the same name of the
   *           column header for the cell containing the failed value.
   *           - 'Trait Name': The trait name provided in the file.
   *           - 'Method Short Name': The method name provided in the file.
   *           - 'Unit': The unit provided in the file.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. This array is nested by the tables expected (keyed by type),
   *     in the order they are expected to show up on the page (ie. 1 array per
   *     table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'expected_trait': The expected name of the trait that failed.
   *       - 'expected_method': The expected short name of the method of the
   *         trait combo that failed.
   *       - 'expected_unit': The expected unit of the trait combo that failed.
   */
  public static function provideDuplicateTraitsFailedCases() {
    $scenarios = [];

    // #0: A duplicate trait was found at line #3 in the input file.
    $scenarios[] = [
      [
        3 => [
          'case' => 'A duplicate trait was found within the input file',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              'Trait Name' => 'Test File Trait',
              'Method Short Name' => 'Test File Method',
              'Unit' => 'Test File Unit',
            ],
          ],
        ],
      ],
      [
        'file' => [
          'expected_message' => 'These trait-method-unit combinations occurred multiple times within your input file.',
          3 => [
            'expected_trait' => 'Test File Trait',
            'expected_method' => 'Test File Method',
            'expected_unit' => 'Test File Unit',
          ],
        ],
      ],
    ];

    // #1: A duplicate trait was found in the database on line #4.
    $scenarios[] = [
      [
        4 => [
          'case' => 'A duplicate trait was found in the database',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              'Trait Name' => 'Test DB Trait',
              'Method Short Name' => 'Test DB Method',
              'Unit' => 'Test DB Unit',
            ],
          ],
        ],
      ],
      [
        'database' => [
          'expected_message' => 'These trait-method-unit combinations have already been imported into this site.',
          4 => [
            'expected_trait' => 'Test DB Trait',
            'expected_method' => 'Test DB Method',
            'expected_unit' => 'Test DB Unit',
          ],
        ],
      ],
    ];

    // #2: A duplicate trait was found within both the input file and database
    // on line #5.
    $scenarios[] = [
      [
        5 => [
          'case' => 'A duplicate trait was found within both the input file and the database',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              'Trait Name' => 'Test Both Trait',
              'Method Short Name' => 'Test Both Method',
              'Unit' => 'Test Both Unit',
            ],
          ],
        ],
      ],
      [
        'file' => [
          'expected_message' => 'These trait-method-unit combinations occurred multiple times within your input file.',
          5 => [
            'expected_trait' => 'Test Both Trait',
            'expected_method' => 'Test Both Method',
            'expected_unit' => 'Test Both Unit',
          ],

        ],
        'database' => [
          'expected_message' => 'These trait-method-unit combinations have already been imported into this site.',
          5 => [
            'expected_trait' => 'Test Both Trait',
            'expected_method' => 'Test Both Method',
            'expected_unit' => 'Test Both Unit',
          ],
        ],
      ],
    ];

    // #3: All 3 possible scenarios occur in the same file:
    // - Line 2 has a duplicate in the database.
    // - Line 3 has a duplicate within the file and the database.
    // - Line 11 has a duplicate within the file.
    $scenarios[] = [
      [
        2 => [
          'case' => 'A duplicate trait was found in the database',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              'Trait Name' => 'Test DB Trait 2',
              'Method Short Name' => 'Test DB Method 2',
              'Unit' => 'Test DB Unit 2',
            ],
          ],
        ],
        3 => [
          'case' => 'A duplicate trait was found within both the input file and the database',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              'Trait Name' => 'Test Both Trait 3',
              'Method Short Name' => 'Test Both Method 3',
              'Unit' => 'Test Both Unit 3',
            ],
          ],
        ],
        11 => [
          'case' => 'A duplicate trait was found within the input file',
          'valid' => FALSE,
          'failedItems' => [
            'combo_provided' => [
              'Trait Name' => 'Test File Trait 11',
              'Method Short Name' => 'Test File Method 11',
              'Unit' => 'Test File Unit 11',
            ],
          ],
        ],
      ],
      [
        'database' => [
          'expected_message' => 'These trait-method-unit combinations have already been imported into this site.',
          2 => [
            'expected_trait' => 'Test DB Trait 2',
            'expected_method' => 'Test DB Method 2',
            'expected_unit' => 'Test DB Unit 2',
          ],
          3 => [
            'expected_trait' => 'Test Both Trait 3',
            'expected_method' => 'Test Both Method 3',
            'expected_unit' => 'Test Both Unit 3',
          ],
        ],
        'file' => [
          'expected_message' => 'These trait-method-unit combinations occurred multiple times within your input file.',
          3 => [
            'expected_trait' => 'Test Both Trait 3',
            'expected_method' => 'Test Both Method 3',
            'expected_unit' => 'Test Both Unit 3',
          ],
          11 => [
            'expected_trait' => 'Test File Trait 11',
            'expected_method' => 'Test File Method 11',
            'expected_unit' => 'Test File Unit 11',
          ],
        ],
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the DuplicateTraits validator.
   *
   * @param array $failures
   *   The failures array that gets passed to the process method. It contains
   *   the following keys:
   *   - The line number that triggered this failed validation status.
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'combo_provided': The combination of trait, method, and unit
   *         provided in the file. The keys used are the same name of the column
   *         header for the cell containing the failed value.
   *         - 'Trait Name': The trait name provided in the file.
   *         - 'Method Short Name': The method name provided in the file.
   *         - 'Unit': The unit provided in the file.
   * @param array $expectations
   *   An array containing the expected output from the process method. This
   *   array is nested by tables expected (keyed by table type), in the order
   *   they are expected to show up on the page (ie. 1 array per table). Each
   *   array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'expected_trait': The expected name of the trait that failed.
   *       - 'expected_method': The expected short name of the method of the
   *         trait combo that failed.
   *       - 'expected_unit': The expected unit of the trait combo that failed.
   *
   * @dataProvider provideDuplicateTraitsFailedCases
   */
  #[DataProvider('provideDuplicateTraitsFailedCases')]
  public function testProcessDuplicateTraitsFailures(array $failures, array $expectations) {
    // Process our test failures array.
    $render_array = $this->importer->processDuplicateTraitsFailures($failures);
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Loop through expectations one table at a time.
    foreach ($expectations as $table_case => $table) {
      // Check the message above this table is correct.
      $selected_message_markup = $this->cssSelect("ul li div.case-message.case-$table_case");
      $table_message = (string) $selected_message_markup[0];
      $this->assertStringContainsString(
        $expectations[$table_case]['expected_message'],
        $table_message,
        'The message expected from processing DuplicateTraits failures for this scenario did not match the message in the render array.'
      );

      // Pull out the table rows for this table case.
      $selected_rows = $this->cssSelect("table.table-case-$table_case tbody tr");
      // Assert that the number of rows matches what we expect.
      $expected_row_count = (count($table) - 1);
      $this->assertCount(
        $expected_row_count,
        $selected_rows,
        'We expected ' . $expected_row_count . 'rows in the rendered table for DuplicateTraits failures for this scenario, but there are ' . count($selected_rows) . '.'
      );

      $current_row_index = 0;
      // Loop through expectations for each row of a table.
      foreach ($table as $expected_line_no => $expected_values) {
        if ($expected_line_no == 'expected_message') {
          continue;
        }
        // Select our current row as an array.
        $select_row_cells = (array) $selected_rows[$current_row_index]->td;
        // 1st Column: Line Number
        $line_number = (string) $select_row_cells[0];
        $this->assertEquals($expected_line_no, $line_number, "Did not get the expected line number in the rendered $table_case table from processing DuplicateTraits failures.");
        // 2nd Column: Trait Name
        $trait_name = (string) $select_row_cells[1];
        $this->assertEquals($expected_values['expected_trait'], $trait_name, "Did not get the expected trait name in the rendered $table_case table from processing DuplicateTraits failures.");
        // 3rd Column: Method Short Name
        $method_name = (string) $select_row_cells[2];
        $this->assertEquals($expected_values['expected_method'], $method_name, "Did not get the expected method name in the rendered $table_case table from processing DuplicateTraits failures.");
        // 4th Column: Unit
        $unit_name = (string) $select_row_cells[3];
        $this->assertEquals($expected_values['expected_unit'], $unit_name, "Did not get the expected unit in the rendered $table_case table from processing DuplicateTraits failures.");

        // Move onto the next row.
        $current_row_index++;
      }
    }
  }

  /**
   * Data Provider for triggering exceptions in all process failures methods.
   *
   * @return array
   *   Each scenario contains a string of the process validator method to be
   *   called, 1 array containing a passed validation result and the expected
   *   exception message, and 1 array containing an unrecognizable case in the
   *   validation status array with the expected exception message. The 2 arrays
   *   are layed out as follows:
   *   - Passed validation case:
   *     - 'process_method_params':
   *       - The failures array that gets passed to the process method. It
   *         contains the following keys:
   *         - [ROW-LEVEL ONLY] The line number that triggered this failed
   *           validation status. This key is NOT set for non row-level
   *           validators.
   *           - 'case': a developer-focused string describing a case of passed
   *             validation.
   *           - 'valid': FALSE to indicate that validation failed.
   *           - 'failedItems': array of items that failed consistent with the
   *             validator in this scenario.
   *       - Any additional parameters IF required by the process validation
   *         method (eg. processValueInListFailures requires expected values).
   *     - 'expected_message': The expected exception message to be triggered
   *       by the case message that indicates passed validation.
   *   - Unrecognized validation case:
   *     - 'process_method_params':
   *       - The failures array that gets passed to the process method. It
   *         contains the following keys:
   *         - [ROW-LEVEL ONLY] The line number that triggered this failed
   *           validation status. This key is NOT set for non row-level
   *           validators.
   *           - 'case': a string that is NOT one of the available case strings
   *             returned by this validator (neither pass or fail).
   *           - 'valid': FALSE to indicate that validation failed.
   *           - 'failedItems': array of items that failed consistent with the
   *             validator in this scenario.
   *       - Any additional parameters IF required by the process validation
   *         method (eg. processValueInListFailures requires expected values).
   *     - 'expected_message': The expected exception message to be triggered
   *       by the case message that is not recognized by the process validation
   *       method for this validator.
   */
  public static function providePassedAndUnrecognizableCases() {
    $scenarios = [];

    $unrecognized_case_string = 'unrecognizable case';

    // #0: GenusExists passed + unrecognizable validation case message.
    $scenarios[] = [
      'processGenusExistsFailures',
      [
        'process_method_params' => [
          [
            'case' => 'Genus exists and is configured with phenotypes',
            'valid' => FALSE,
            'failedItems' => [
              'genus_provided' => 'Tripalus',
            ],
          ],
        ],
        'expected_message' => 'The case string returned by the GenusExists validator implies validation passed, but valid is set to FALSE.',
      ],
      [
        'process_method_params' => [
          [
            'case' => $unrecognized_case_string,
            'valid' => FALSE,
            'failedItems' => [
              'genus_provided' => 'Tripalus',
            ],
          ],
        ],
        'expected_message' => 'The case string returned by the GenusExists validator is not recognized as a potential case.',
      ],
    ];

    // #1: DuplicateTraits passed + unrecognizable validation case message.
    $scenarios[] = [
      'processDuplicateTraitsFailures',
      [
        'process_method_params' => [
          [
            8 => [
              'case' => 'Confirmed that the current trait being validated is unique',
              'valid' => FALSE,
              'failedItems' => [
                'combo_provided' => [
                  'Trait Name' => 'My Trait',
                  'Method Short Name' => 'My Method',
                  'Unit' => 'My Unit',
                ],
              ],
            ],
          ],
        ],
        'expected_message' => 'The case string returned by the DuplicateTraits validator at line #8 implies validation passed, but valid is set to FALSE.',
      ],
      [
        'process_method_params' => [
          [
            9 => [
              'case' => $unrecognized_case_string,
              'valid' => FALSE,
              'failedItems' => [
                'combo_provided' => [
                  'Trait Name' => 'My Trait',
                  'Method Short Name' => 'My Method',
                  'Unit' => 'My Unit',
                ],
              ],
            ],
          ],
        ],
        'expected_message' => 'The case string returned by the DuplicateTraits validator at line #9 is not recognized as a potential case.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests for exceptions thrown for passed and unrecognizable case strings.
   *
   * @param string $process_method
   *   The name of the process failures method being called in this test.
   * @param array $passed_case
   *   An array with the following keys:
   *   - 'process_method_params':
   *     - The failures array that gets passed to the process method. It
   *       contains the following keys:
   *       - [ROW-LEVEL ONLY] The line number that triggered this failed
   *         validation status. This key is NOT set for non row-level
   *         validators.
   *         - 'case': a developer-focused string describing a case of passed
   *           validation.
   *         - 'valid': FALSE to indicate that validation failed.
   *         - 'failedItems': array of items that failed consistent with the
   *           validator in this scenario.
   *   - 'expected_message': The expected exception message to be triggered
   *     by the case message that indicates passed validation.
   * @param array $unrecognized_case
   *   An array with the following keys:
   *   - 'process_method_params':
   *     - The failures array that gets passed to the process method. It
   *       contains the following keys:
   *       - [ROW-LEVEL ONLY] The line number that triggered this failed
   *         validation status. This key is NOT set for non row-level
   *         validators.
   *         - 'case': a string that is NOT one of the available case strings
   *           returned by this validator (pass or fail).
   *         - 'valid': FALSE to indicate that validation failed.
   *         - 'failedItems': array of items that failed consistent with the
   *           validator in this scenario.
   *   - 'expected_message': The expected exception message to be triggered
   *     by the case message that is not recognized by the process validation
   *     method for this validator.
   *
   * @dataProvider providePassedAndUnrecognizableCases
   */
  #[DataProvider('providePassedAndUnrecognizableCases')]
  public function testProcessFailuresExceptions(string $process_method, array $passed_case, array $unrecognized_case) {
    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      // The code below is essentially the same as:
      // @code
      // $this->importer->$process_method($passed_case['process_method_params'][0]);
      // @endcode
      // When there is only 1 parameter. But this code also seemlessly handles
      // any number of additional parameters.
      $process_method_callable = [$this->importer, $process_method];
      call_user_func_array($process_method_callable, $passed_case['process_method_params']);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expected an exception to be caught for providing a passed validation case string to $process_method, but one wasn't thrown.",
    );
    $this->assertEquals(
      $passed_case['expected_message'],
      $exception_message,
      "We expected the exception message to indicate that a passed validation string was provided to $process_method, but it does not match what was expected.",
    );

    // Test with an unrecognizable validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $process_method_callable = [$this->importer, $process_method];
      call_user_func_array($process_method_callable, $unrecognized_case['process_method_params']);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expected an exception to be caught for providing an unrecognized validation case string to $process_method, but one wasn't thrown.",
    );
    $this->assertEquals(
      $unrecognized_case['expected_message'],
      $exception_message,
      "We expected the exception message to indicate that an unrecognized validation string was provided to $process_method, but it does not match what was expected.",
    );
  }

}
