<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\TripalImporter;

use Drupal\KernelTests\AssertContentTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Plugin\TripalImporter\TripalCultivatePhenotypesTraitsImporter;

/**
 * Tests processValidationMessages() and related methods in the Traits Importer.
 *
 * @group traitsImporter
 */
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
    $this->installConfig(['system', 'trpcultivate_phenotypes']);
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
      $this->container->get('entity_type.manager'),
      $this->container->get('trpcultivate_phenotypes.template_generator'),
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
  public function provideGenusExistsFailedCases() {
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
   * Data Provider for testProcessValidDataFileFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The validation result array that gets passed to the process method. It
   *     contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'filename': The provided name of the file.
   *       - 'fid': The fid of the provided file.
   *       - 'mime': The mime type of the input file if it is not supported.
   *       - 'extension': The extension of the input file if not supported.
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_item_count': The number of failed items expected.
   *     - 'expected_item': The expected failed item.
   */
  public function provideValidDataFileFailedCases() {
    $scenarios = [];

    // #0: An invalid file ID is provided.
    $scenarios[] = [
      [
        'case' => 'Invalid file id number',
        'valid' => FALSE,
        'failedItems' => [
          'fid' => 'wrongid',
        ],
      ],
      [
        'expected_message' => 'A problem occurred in between uploading the file and submitting it for validation.',
        'expected_item_count' => 0,
      ],
    ];

    // #1: An empty file was provided.
    $filename = 'empty_file.txt';
    $scenarios[] = [
      [
        'case' => 'The file has no data and is an empty file',
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $filename,
          'fid' => 123,
        ],
      ],
      [
        'expected_message' => 'The file provided has no contents in it to import. Please ensure your file has the expected header row and at least one row of data.',
        'expected_item_count' => 1,
        'expected_item' => 'Filename: ' . $filename,
      ],
    ];

    // #2: The file MIME type is unsupported.
    $mime = 'application/pdf';
    $extension = 'tsv';
    $scenarios[] = [
      [
        'case' => 'Unsupported file MIME type',
        'valid' => FALSE,
        'failedItems' => [
          'mime' => $mime,
          'extension' => $extension,
        ],
      ],
      [
        'expected_message' => 'The type of file uploaded is not supported by this importer. Please ensure your file has one of the supported file extensions and was saved using software that supports that type of file.',
        'expected_item_count' => 1,
        'expected_item' => "The file extension indicates the file is \"$extension\" but our system detected the file is of type \"$mime\"",
      ],
    ];

    // #3: The data file couldn't be opened.
    $filename = 'unopenable.tsv';
    $scenarios[] = [
      [
        'case' => 'Data file cannot be opened',
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $filename,
          'fid' => 456,
        ],
      ],
      [
        'expected_message' => 'The file provided could not be opened. Please contact your administrator for help.',
        'expected_item_count' => 1,
        'expected_item' => 'Filename: ' . $filename,
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for ValidDataFile validator.
   *
   * @param array $validation_result
   *   The validation result array that gets passed to the process method. It
   *   contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'filename': The provided name of the file.
   *     - 'fid': The fid of the provided file.
   *     - 'mime': The mime type of the input file if it is not supported.
   *     - 'extension': The extension of the input file if not supported.
   * @param array $expectations
   *   An array of the expected items in the rendered output. It has the
   *   following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *   - 'expected_item_count': The number of failed items expected.
   *   - 'expected_item': The expected failed item.
   *
   * @dataProvider provideValidDataFileFailedCases
   */
  public function testProcessValidDataFileFailures(array $validation_result, array $expectations) {
    // Call the process method on our validation result.
    $render_array = $this->importer->processValidDataFileFailures($validation_result);
    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // First check that we were given the correct message.
    $selected_message_title = $this->cssSelect('div.tcp-valid-data-file-failures label');
    $provided_message = (string) $selected_message_title[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $provided_message,
      'The message expected from processing ValidDataFile failures for this scenario did not match the one in the rendered output.'
    );

    // Next, check for expected items.
    $selected_list_items = $this->cssSelect('div.tcp-valid-data-file-failures ul li');
    $list_item_count = count($selected_list_items);
    $this->assertEquals($expectations['expected_item_count'], $list_item_count, 'We expected ' . $expectations['expected_item_count'] . ' list items in the render array from processing ValidDataFile failures, but instead found ' . $list_item_count . '.');
    // If this is a case where we expect an item, grab the contents of
    // 'SimpleXMLElement Object' and assert it matches what we expect.
    if (array_key_exists('expected_item', $expectations)) {
      $provided_item = (string) $selected_list_items[0];
      $this->assertEquals($expectations['expected_item'], $provided_item, 'The render array from processing ValidDataFile failures did not contain the expected failed item.');
    }
  }

  /**
   * Data Provider for testProcessValidHeadersFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The validation result array that gets passed to the process method. It
   *     contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'headers': A string indicating the header row is empty.
   *       - an array of column headers that was in the input file.
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   */
  public function provideValidHeadersFailedCases() {
    $scenarios = [];

    // #0: The header row is empty.
    $scenarios[] = [
      [
        'case' => 'Header row is an empty value',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ],
      [
        'expected_message' => 'The file has an empty row where the header was expected.',
      ],
    ];

    // #1: Correct number of headers, but there's a mismatch.
    $scenarios[] = [
      [
        'case' => 'Headers do not match expected headers',
        'valid' => FALSE,
        'failedItems' => [
          'Trait Name',
          'Trait Description',
          '',
          'Method Description',
          'Unit',
          'Type',
        ],
      ],
      [
        'expected_message' => 'One or more of the column headers in the input file does not match what was expected.',
      ],
    ];

    // #2: Incorrect number of headers
    $scenarios[] = [
      [
        'case' => 'Headers provided does not have the expected number of headers',
        'valid' => FALSE,
        'failedItems' => [
          'Trait Name',
          'Trait Description',
          'Method Short Name',
          'Method Description',
        ],
      ],
      [
        'expected_message' => 'This importer requires a strict number of 6 column headers.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for ValidHeaders validator.
   *
   * @param array $validation_result
   *   The validation result array that gets passed to the process method. It
   *   contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'headers': A string indicating the header row is empty.
   *     - an array of column headers that was in the input file.
   * @param array $expectations
   *   An array of the expected items in the rendered output. It has the
   *   following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *
   * @dataProvider provideValidHeadersFailedCases
   */
  public function testProcessValidHeadersFailures(array $validation_result, array $expectations) {
    // Call the process method on our validation result.
    $render_array = $this->importer->processValidHeadersFailures($validation_result);
    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // First check that we were given the correct message.
    $selected_message_markup = $this->cssSelect('ul li div.case-message');
    $this->assertStringContainsString(
      $expectations['expected_message'],
      (string) $selected_message_markup[0],
      'The message expected from processing ValidHeaders failures for this scenario did not match the message in the render array.'
    );
    // Check that we have a table that contains the expected 2 rows.
    $selected_table_rows = $this->cssSelect('tbody tr');
    $this->assertCount(2, $selected_table_rows, 'The rendered table by processValidHeadersFailures does not contain the expected 2 rows for this scenario.');
    // Check for the "Provided Headers" heading on the 2nd row.
    $selected_provided_headers_th = $this->cssSelect('tbody tr.provided-headers th');
    $this->assertEquals(
      'Provided Headers',
      (string) $selected_provided_headers_th[0],
      'The second row of the rendered table does not contain the "Provided Headers" table header for this scenario.'
    );
    // Check that the row values are the same as what we provided.
    $selected_provided_headers_td = $this->cssSelect('tbody tr.provided-headers td');
    // If the headers row was empty, check that we have no values in row 2.
    if (array_key_exists('headers', $validation_result['failedItems'])) {
      $this->assertEmpty($selected_provided_headers_td, "The values in the \"Provided Headers\" row were expected to be empty since an empty header was provided, but are not.");
    }
    else {
      // Iterate through our provided headers to compare with what's in the
      // rendered provided headers row.
      $this->assertEquals($validation_result['failedItems'], $selected_provided_headers_td, "The header row provided does not match the second row (the \'provided headers\' row) of the rendered table.");
    }
  }

  /**
   * Data Provider for testProcessValidDelimitedFileFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The failures array that gets passed to the process method. It contains
   *     the following keys:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'raw_row': The contents of the raw row as it appears in the file.
   *         - 'expected_columns': The number of columns expected in the input
   *           file as determined by calling getExpectedColumns().
   *         - 'strict': A boolean indicating whether the number of expected
   *           columns by the validator is strict (TRUE) or is the minimum
   *           number required (FALSE).
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. This array is nested by the tables expected (keyed by type -
   *     'unsupported' or 'delimited'), in the order they are expected to show
   *     up on the page (1 array per table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'line_contents': The raw contents of this line that failed.
   */
  public function provideValidDelimitedFileFailedCases() {
    $scenarios = [];

    // #0: The first row is empty (single whitespace).
    $scenarios[] = [
      [
        1 => [
          'case' => 'Raw row is empty',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => ' ',
          ],
        ],
      ],
      [
        'unsupported' => [
          'expected_message' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
          1 => [
            'line_contents' => ' ',
          ],
        ],
      ],
    ];

    // #1: No supported delimiters were used.
    $raw_row = "This is one very long string without any supported delimiters in it.";
    $scenarios[] = [
      [
        2 => [
          'case' => 'None of the delimiters supported by the file type was used',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row,
          ],
        ],
      ],
      [
        'unsupported' => [
          'expected_message' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
          2 => [
            'line_contents' => $raw_row,
          ],
        ],
      ],
    ];

    // #2: Multiple rows with too few columns and strict = FALSE
    $raw_row_2 = "Column 1\tColumn 2";
    $raw_row_4 = "Column 1\tColumn 2\tColumn 3";
    $scenarios[] = [
      [
        2 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_2,
            'expected_columns' => 4,
            'strict' => FALSE,
          ],
        ],
        4 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_4,
            'expected_columns' => 4,
            'strict' => FALSE,
          ],
        ],
      ],
      [
        'delimited' => [
          'expected_message' => 'This importer requires a minimum number of 4 columns for each line. The following lines do not contain the expected number of columns.',
          2 => [
            'line_contents' => $raw_row_2,
          ],
          4 => [
            'line_contents' => $raw_row_4,
          ],
        ],
      ],
    ];

    // #3: 1 row with too few columns, 1 with too many columns, strict = TRUE
    $raw_row_3 = "Column 1\tColumn 2\tColumn 3\tColumn 4";
    $raw_row_5 = "Column 1\tColumn 2\tColumn 3\tColumn 4\tColumn 5\tColumn 6\tColumn 7\tColumn 8";
    $scenarios[] = [
      [
        3 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_3,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
        5 => [
          'case' => 'Raw row exceeds number of strict columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_5,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
      ],
      [
        'delimited' => [
          'expected_message' => 'This importer requires a strict number of 6 columns for each line. The following lines do not contain the expected number of columns.',
          3 => [
            'line_contents' => $raw_row_3,
          ],
          5 => [
            'line_contents' => $raw_row_5,
          ],
        ],
      ],
    ];

    // #4: A mix of unsupported delimiters and insufficient columns.
    $raw_row_6 = "Column 1 Column 2 Column 3 Column 4";
    $raw_row_7 = "Column 1\tColumn 2\tColumn 3\tColumn 4\tColumn 5\tColumn 6\tColumn 7";
    $scenarios[] = [
      [
        6 => [
          'case' => 'None of the delimiters supported by the file type was used',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_6,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
        7 => [
          'case' => 'Raw row exceeds number of strict columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_7,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
      ],
      [
        'unsupported' => [
          'expected_message' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
          6 => [
            'line_contents' => $raw_row_6,
          ],
        ],
        'delimited' => [
          'expected_message' => 'This importer requires a strict number of 6 columns for each line. The following lines do not contain the expected number of columns.',
          7 => [
            'line_contents' => $raw_row_7,
          ],
        ],
      ],
    ];
    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValidDelimitedFile validator.
   *
   * @param array $failures
   *   The failures array that gets passed to the process method. It contains
   *   the following keys:
   *   - The line number that triggered this failed validation status.
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'raw_row': The contents of the row as it appears in the file.
   *       - 'expected_columns': The number of columns expected in the input
   *         file as determined by calling getExpectedColumns().
   *       - 'strict': A boolean indicating whether the number of expected
   *         columns by the validator is strict (TRUE) or is the minimum number
   *         required (FALSE).
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output. This array is nested by the tables expected (keyed by type -
   *   'unsupported' or 'delimited'), in the order they are expected to show
   *   up on the page (1 array per table). Each array has the following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *   - 1+ arrays keyed by the line number in the input file that triggered
   *     the failed validation status, further keyed by:
   *     - 'line_contents': The raw contents of this line that failed.
   *
   * @dataProvider provideValidDelimitedFileFailedCases
   */
  public function testProcessValidDelimitedFileFailures(array $failures, array $expectations) {
    // Process our test failures array.
    $render_array = $this->importer->processValidDelimitedFileFailures($failures);
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
        'The message expected from processing ValidDelimitedFile failures for this scenario did not match the message in the render array.'
      );

      // Pull out the table rows for this table case.
      $selected_rows = $this->cssSelect("table.table-case-$table_case tbody tr");
      // Assert that the number of rows matches what we expect.
      $expected_row_count = (count($table) - 1);
      $this->assertCount(
        $expected_row_count,
        $selected_rows,
        'We expected ' . $expected_row_count . 'rows in the rendered table for ValidDelimitedFile failures for this scenario, but there are ' . count($selected_rows) . '.'
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
        $line_number = $select_row_cells[0];
        $this->assertEquals($expected_line_no, $line_number, "Did not get the expected line number in the rendered table from processing ValidDelimitedFile failures.");
        // 2nd Column: The contents of this line in the file
        $line_contents = (string) $select_row_cells[1];
        $this->assertEquals($expected_values['line_contents'], $line_contents, 'Did not get the expected line contents in the rendered table from processing ValidDelimitedFile failures.');
        // Move onto the next row.
        $current_row_index++;
      }
    }
  }

  /**
   * Data Provider for testProcessEmptyCellFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The failures array that gets passed to the process method. It contains
   *     the following keys:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'empty_indices': A list of column indices in the line which were
   *           checked and found to be empty.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output which has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'expected_columns': A comma-separated list of column headers that
   *         map to the expected columns with empty values.
   */
  public function provideEmptyCellFailedCases() {
    $scenarios = [];

    // #0: One empty required column on line #5
    $scenarios[] = [
      [
        5 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [0],
          ],
        ],
      ],
      [
        'expected_message' => 'The following line number and column header combinations were empty, but a value is required.',
        5 => [
          'expected_columns' => 'Trait Name',
        ],
      ],
    ];

    // #1: One required column is empty on multiple rows
    $scenarios[] = [
      [
        3 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [5],
          ],
        ],
        8 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [5],
          ],
        ],
      ],
      [
        'expected_message' => 'The following line number and column header combinations were empty, but a value is required.',
        3 => [
          'expected_columns' => 'Type',
        ],
        8 => [
          'expected_columns' => 'Type',
        ],
      ],
    ];

    // #2: Multiple different required columns on multiple lines
    $scenarios[] = [
      [
        2 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [0, 2, 4],
          ],
        ],
        6 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [4, 5],
          ],
        ],
      ],
      [
        'expected_message' => 'The following line number and column header combinations were empty, but a value is required.',
        2 => [
          'expected_columns' => 'Trait Name, Method Short Name, Unit',
        ],
        6 => [
          'expected_columns' => 'Unit, Type',
        ],
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the EmptyCell validator.
   *
   * @param array $failures
   *   The failures array that gets passed to the process method. It contains
   *   the following keys:
   *   - The line number that triggered this failed validation status.
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'empty_indices': A list of column indices in the line which were
   *         checked and found to be empty.
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output which has the following keys:
   *   - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *   - 1+ arrays keyed by the line number in the input file that triggered
   *     the failed validation status, further keyed by:
   *     - 'expected_columns': A comma-separated list of column headers that
   *       map to the expected columns with empty values.
   *
   * @dataProvider provideEmptyCellFailedCases
   */
  public function testProcessEmptyCellFailures(array $failures, array $expectations) {
    // Process our test failures array.
    $render_array = $this->importer->processEmptyCellFailures($failures);
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Check the message above this table is correct.
    $selected_message_markup = $this->cssSelect("ul li div.case-message");
    $table_message = (string) $selected_message_markup[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $table_message,
      'The message expected from processing EmptyCell failures for this scenario did not match the message in the render array.'
    );

    // Select the table rows and check for our expected values.
    $selected_rows = $this->cssSelect("tbody tr");
    // Assert that the number of rows matches what we expect.
    $expected_row_count = (count($expectations) - 1);
    $this->assertCount(
      $expected_row_count,
      $selected_rows,
      'We expected ' . $expected_row_count . 'rows in the rendered table for EmptyCell failures for this scenario, but there are ' . count($selected_rows) . '.'
    );

    $current_row_index = 0;
    // Loop through expectations for each row in the table.
    foreach ($expectations as $expected_line_no => $expected_values) {
      if ($expected_line_no == 'expected_message') {
        continue;
      }
      // Select our current row as an array.
      $select_row_cells = (array) $selected_rows[$current_row_index]->td;
      // 1st Column: Line Number
      $line_number = $select_row_cells[0];
      $this->assertEquals($expected_line_no, $line_number, "Did not get the expected line number in the rendered table from processing EmptyCell failures.");
      // 2nd Column: Column(s) with empty value
      $empty_columns = $select_row_cells[1];
      $this->assertEquals($expected_values['expected_columns'], $empty_columns, 'Did not get the expected column names of empty cells in the rendered table from processing EmptyCell failures.');
      // Move onto the next row.
      $current_row_index++;
    }
  }

  /**
   * Data Provider for testProcessValueInListFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The failures array that gets passed to the process method. It contains
   *     the following keys:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed, where the key => value
   *         pairs map to the index => cell value(s) that failed validation.
   *   - The list of values that are considered valid by this validator.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output which has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_column_count': The number of columns expected in the
   *       rendered table for this scenario.
   *     - 'expected_table_rows': 1+ arrays keyed by the line number in the
   *       input file that triggered the failed validation status, further keyed
   *       by the column header name of a cell in this row and its value is the
   *       invalid value. For example:
   *       - 2 => [ 'Type' => 'Invalid Value' ]
   */
  public function provideValueInListFailedCases() {
    $scenarios = [];

    // #0: An invalid value in a required column on one row.
    $valid_values = [
      'Quantitative',
      'Qualitative',
    ];
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'Invalid Type',
          ],
        ],
      ],
      $valid_values,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $valid_values) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'Invalid Type',
          ],
        ],
      ],
    ];

    $valid_values = [
      'cm',
      'days',
      'scale',
    ];
    // #1: An invalid value on multiple rows (1 column)
    $scenarios[] = [
      [
        2 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Unit' is at index 4.
            4 => 'Amy',
          ],
        ],
        5 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            4 => 'Sam',
          ],
        ],
      ],
      $valid_values,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $valid_values) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          2 => [
            'Unit' => 'Amy',
          ],
          5 => [
            'Unit' => 'Sam',
          ],
        ],
      ],
    ];

    // #2: Multiple different invalid values in different columns.
    $scenarios[] = [
      [
        2 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'Amy',
          ],
        ],
        5 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Unit' is at index 4.
            4 => 'Sam',
            5 => 'Ben',
          ],
        ],
      ],
      $valid_values,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $valid_values) . '".',
        'expected_column_count' => 3,
        'expected_table_rows' => [
          2 => [
            'Unit' => '',
            'Type' => 'Amy',
          ],
          5 => [
            'Unit' => 'Sam',
            'Type' => 'Ben',
          ],
        ],
      ],
    ];

    // Potential @todo scenario: ValueInList is configured for multiple columns,
    // but at least one of the columns doesn't have any failures. This isn't
    // testable since this data provider only supplies failures, but it's some-
    // thing to keep in mind if we have the opportunity to test in the future.
    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValueInList validator.
   *
   * @param array $failures
   *   The failures array that gets passed to the process method. It contains
   *   the following keys:
   *   - The line number that triggered this failed validation status.
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys,
   *       where the key => value pairs map to the index => cell value(s) that
   *       failed validation.
   * @param array $valid_values
   *   A list of values that would have been considered valid by this validator.
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output which has the following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *   - 'expected_column_count': The number of columns expected in the
   *     rendered table for this scenario.
   *   - 'expected_table_rows': 1+ arrays keyed by the line number in the
   *     input file that triggered the failed validation status, further keyed
   *     by the column header name of a cell in this row and its value is the
   *     invalid value. For example:
   *     - 2 => [ 'Type' => 'Invalid Value' ].
   *
   * @dataProvider provideValueInListFailedCases
   */
  public function testProcessValueInListFailures(array $failures, array $valid_values, array $expectations) {
    // Process our test failures array for this scenario.
    $render_array = $this->importer->processValueInListFailures($failures, $valid_values);
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Check the message above this table is correct.
    $selected_message_markup = $this->cssSelect("ul li div.case-message");
    $table_message = (string) $selected_message_markup[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $table_message,
      'The message expected from processing ValueInList failures for this scenario did not match the message in the render array.'
    );

    // Select and save the table header.
    $selected_table_header = $this->cssSelect("thead tr");
    $select_column_headers = (array) $selected_table_header[0]->th;
    // Assert that the number of columns matches the number of we expect.
    $this->assertCount(
      $expectations['expected_column_count'],
      $select_column_headers,
      'We expected ' . $expectations['expected_column_count'] . 'columns to be in the rendered table for ValueInList failures for this scenario, but instead there are ' . count($select_column_headers) . '.'
    );

    // Select the table rows.
    $selected_rows = $this->cssSelect("tbody tr");
    // Assert that the number of rows matches what we expect.
    $expected_row_count = count($expectations['expected_table_rows']);
    $this->assertCount(
      $expected_row_count,
      $selected_rows,
      'We expected ' . $expected_row_count . 'rows in the rendered table for ValueInList failures for this scenario, but there are ' . count($selected_rows) . '.'
    );

    // Now check the cell values.
    $current_row_index = 0;
    // Loop through expectations for each row in the table.
    foreach ($expectations['expected_table_rows'] as $expected_line_no => $expected_values) {
      $select_row_cells = (array) $selected_rows[$current_row_index]->td;
      // 1st Column: Line Number
      $line_number = $select_row_cells[0];
      $this->assertEquals(
        $expected_line_no,
        $line_number,
        "Did not get the expected line number in the rendered table from processing ValueInList failures."
      );
      // 2nd Column and up: Column(s) with invalid value
      $current_column_index = 1;
      foreach ($expected_values as $column_header => $invalid_value) {
        // Check that the invalid value is under the correct column header.
        $this->assertEquals(
          $column_header,
          $select_column_headers[$current_column_index],
          "We expected the column header $column_header to be present in the rendered table's header for ValueInList failures at index $current_column_index but it was not."
        );
        // Check that the invalid value in the table matches what we expect.
        $this->assertEquals(
          $invalid_value,
          (string) $select_row_cells[$current_column_index],
          "We expected an invalid value to be listed for $column_header at line #$expected_line_no in the rendered table for ValueInList failures."
        );
        $current_column_index++;
      }
      // Move onto the next row.
      $current_row_index++;
    }
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
  public function provideDuplicateTraitsFailedCases() {
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

}
