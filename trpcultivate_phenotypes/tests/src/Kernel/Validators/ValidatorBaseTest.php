<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\BasicallyBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Base functions
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class ValidatorBaseTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration
   *
   * @var config_entity
   */
  private $config;

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
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test the checkIndices() function in the Validator Base class
   */
  public function testValidatorBaseCheckIndices() {

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

    // Simulates a row within the Trait Importer
    $file_row = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit',
      'Qualitative'
    ];

    // Provide a valid list of indices
    $indices = [ 0, 1, 2, 3, 4, 5 ];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
    }
    $this->assertFalse($exception_caught, 'Caught an exception from checkIndices() in spite of valid indices being provided.');

    // ------------ ERROR CASES ---------------
    // Provide an empty array of indices
    $indices = [];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in an empty array of indices.');
    $this->assertStringContainsString('An empty indices array was provided.', $e->getMessage(), "Did not get the expected exception message when providing an empty array of indices.");

    // Provide too many indices
    $indices = [0, 1, 2, 3, 4, 5, 6, 7];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in too many indices compared to number of cells in the row.');
    $this->assertStringContainsString('Too many indices were provided (8) compared to the number of cells in the provided row (6)', $e->getMessage(), "Did not get the expected exception message when providing 8 indices compared to 6 values.");

    // Provide invalid indices
    $indices = [1, -4, 77];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in invalid indices.');
    $this->assertStringContainsString('One or more of the indices provided (-4, 77) is not valid when compared to the indices of the provided row', $e->getMessage(), "Did not get the expected exception message when providing 2 different invalid indices.");
  }

  /**
   * Test the basic getters: getValidatorName() and getConfigAllowNew().
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

    // Check that we can get the name of the validator we requested above.
    // NOTE: this is the validator_name in the annotation.
    $expected_name = $plugin_definition['validator_name'];
    $returned_name = $instance->getValidatorName();
    $this->assertEquals($expected_name, $returned_name,
      "We did not recieve the name we expected when using getValidatorName() for $validator_id validator.");

    // Check that we are able to get the configuration for allowing new traits.
    // NOTE: this is set by the admin in the ontology config form and doesn't
    // change between importers.
    $expected_allownew = TRUE;
    $returned_allownew = $instance->getConfigAllowNew();
    $this->assertEquals($expected_allownew, $returned_allownew,
      "We did not get the status for Allowing New configuration that we expected through the $validator_id validator.");

    // check that the validator scope is not returned when it is not set.
    // @deprecated Remove in issue #91
    $scope = $instance->getValidatorScope();
    $this->assertNull($scope, "The validator scope is not set for the $validator_id therefore no scope should be returned.");
  }

  /**
   * Test the input type focused getters: getSupportedInputTypes()
   * + checkInputTypeSupported().
   */
  public function testInputTypeValidatorGetters() {

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

    // Check that we can get the supported input types for this validator.
    // NOTE: use assertEqualsCanonicalizing so that order of arrays does NOT matter.
    $expected_input_types = ['data-row', 'header-row'];
    $returned_input_types = $instance->getSupportedInputTypes();
    $this->assertEqualsCanonicalizing($expected_input_types, $returned_input_types,
      "We did not get the expected input types for $validator_id validator when using getSupportedInputTypes().");

    // Check that we rightly get told the data-row is a supported input type.
    $dataRow_supported = $instance->checkInputTypeSupported('data-row');
    $this->assertTrue($dataRow_supported,
      "The data-row input type should be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this.");

    // Check that we rightly get told the data-row is a supported input type.
    $metadata_supported = $instance->checkInputTypeSupported('metadata');
    $this->assertFalse(
      $metadata_supported,
      "The metadata input type should NOT be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this."
    );

    // Check with an invalid inputType.
    $invalid_supported = $instance->checkInputTypeSupported('SARAH');
    $this->assertFalse(
      $invalid_supported,
      "The SARAH input type is invalid and thus should NOT be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this."
    );
  }

  /**
   * Test the validate methods: validateMetadata(), validateFile(),
   * validateRawRow(), validateRow(), validate().
   *
   * NOTE: These should all thrown an exception in the base class.
   */
  public function testValidatorValidateMethods() {

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

    // Tests Base Class validateMetadata().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $form_values = ['genus' => 'Fred', 'project_id' => 123];
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateMetadata() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateMetadata() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateMetadata()"
    );

    // Tests Base Class validateFile().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $filename = 'public://does_not_exist.txt';
      $fid = 123;
      $instance->validateFile($filename, $fid);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateFile() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateFile() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateFile()"
    );

    // Tests Base Class validateRawRow().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $row_values = ['col1', 'col2', 'col3', 'col4', 'col5'];
      $row_string = implode("\t", $row_values);
      $instance->validateRawRow($row_string);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateRawRow() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateRawRow() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateRawRow()"
    );

    // Tests Base Class validateRow().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $row_values = ['col1', 'col2', 'col3', 'col4', 'col5'];
      $instance->validateRow($row_values);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateRow() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateRow() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateRow()"
    );

    // Tests Base Class validate().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $instance->validate();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validate() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validate() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validate()"
    );
  }

  /**
   * DATA PROVIDER: tests the split row by providing mime type to delimiter options.
   *
   * @return array
   *   Each test scenario is an array with the following values.
   *
   *   - Mime type input.
   *   - Expected delimiter associated to the mime type provided.
   */
  public function provideMimeTypeDelimiters() {
    $sets = [];

    $sets[] = [
      'text/tab-separated-values',
      "\t",
    ];

    $sets[] = [
      'text/csv',
      ','
    ];

    /* Not currently supported as multiple delimiters match this mime-type
    $sets[] = [
      'text/plain',
      ','
    ];
    */

    return $sets;
  }

  /**
   * Data Provider: provide test data (mime types) to file delimiter getter method.
   *
   * @return array
   *   Each test scenario is an array with the following values.
   *
   *   - A string, human-readable short description of the test scenario.
   *   - A string, mime type input.
   *   - Boolean value, indicates if the scenario is expecting an exception thrown (TRUE) or not (FALSE).
   *   - The expected exception message thrown by the getter method on failed request.
   *   - The expected delimiter returned.
   */
  public function provideMimeTypesForFileDelimiterGetter() {
    return [
      [
        'test empty string mime type input',
        '',
        TRUE,
        'The getFileDelimiters() getter requires a string of the input file\'s mime-type and must not be empty.',
        FALSE
      ],
      [
        'test tab-separated values mime type (tsv)',
        'text/tab-separated-values',
        FALSE,
        '',
        ["\t"]
      ],
      [
        'test comma-separated values mime type (csv)',
        'text/csv',
        FALSE,
        '',
        [',']
      ],
      [
        'test tab-separated values mime type (txt)',
        'text/plain',
        FALSE,
        '',
        ["\t", ',']
      ],
      [
        'test unsupported mime types (docx)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        TRUE,
        'Cannot retrieve file delimiters for the mime-type provided: application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        FALSE
      ]
    ];
  }

  /**
   * Test line or row split method.
   *
   * @param $expected_mime_type
   *   Mime type input to the split row method.
   * @param $expected_delimiter
   *   The delimiter associated to the mime type in the mime type - delimiter mapping array.
   *
   * @dataProvider provideMimeTypeDelimiters
   */
  public function testSplitRowIntoColumns(string $expected_mime_type, string $expected_delimiter) {

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

    // Create a data row.
    // This line captures data values with single/double quotes and leading/trailing spaces.
    $good_line = $raw_line = ['Value A', 'Value "B"', 'Value \'C\'', 'Value D ', ' Value E', ' Value F ', ' Value G           '];
    // Sanitize the values so that the expected split values would be:
    // Value A, Value B, Value C, Value D, Value E, Value F and Value G.
    foreach($good_line as &$l) {
      $l = trim(str_replace(['"','\''], '', $l));
    }

    // At this point line is sanitized and sparkling*

    // Test:
    // 1. Failed to specify a delimiter.
    // 2. Test that delimiter could not split the line.
    // 3. Line values and split values match.
    // 4. Some other delimiter.

    // Unsupported mime type and thus unknown delimiter.
    $delimiter = '~';
    $str_line = implode($delimiter, $raw_line);

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($str_line, 'text/uncertain');
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no delimiter defined in splitRowIntoColumns().');
    $this->assertStringContainsString(
      'mime type "text/uncertain" passed into splitRowIntoColumns() is not supported', $exception_message,
      'We did not get the expected message when an unknown mime type is passed into splitRowIntoColumns().');

    // Delimiter is not present in the line and could not split the line.
    // This case will return the original line.
    $delimiter = '<not_the_delimiter>';
    $str_line = implode($delimiter, $raw_line);

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($str_line, $expected_mime_type);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when splitRowIntoColumns() could not split line using the delimiter.');
    $this->assertStringContainsString(
      'line provided could not be split into columns', $exception_message,
      'Expected exception message does not match message when splitRowIntoColumns() could not split line using the delimiter.');

    // Test that the sanitized line is the same as the split values.
    $delimiter = $expected_delimiter;
    $str_line = implode($delimiter, $raw_line);
    $values = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($str_line, $expected_mime_type);
    $this->assertEquals($good_line, $values, 'Line values does not match expected split values.');
  }

  /**
   * Test validator base file delimiter getter method.
   *
   * @param $scenario
   *   Human-readable text description of the test scenario.
   * @param $mime_type_input
   *   Mime type input.
   * @param $has_exception
   *   Indicates if the test scenario will throw an exception (TRUE) or not (FALSE).
   * @param $exception_message
   *   The exception message if the test scenario is expected to throw an exception.
   * @param $expected
   *   The returned file delimiter.
   *
   * @dataProvider provideMimeTypesForFileDelimiterGetter
   */
  public function testFileDelimiterGetter($scenario, $mime_type_input, $has_exception, $exception_message, $expected) {

    $exception_caught = FALSE;
    $exception_get_message = '';
    $delimiter = FALSE;

    try {
      $delimiter = TripalCultivatePhenotypesValidatorBase::getFileDelimiters($mime_type_input);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_get_message = $e->getMessage();
    }

    $this->assertEquals($exception_caught, $has_exception, 'An exception was expected by file delimiter getter method for scenario:' . $scenario);
    $this->assertStringContainsString(
      $exception_message,
      $exception_get_message,
      'The expected exception message thrown by file delimiter getter does not match message thrown for test scenario: ' . $scenario
    );

    $this->assertEquals($delimiter, $expected, 'Value returned does not match expected value for scenario:' . $scenario);
  }

  /**
   * Quickly test that mime-types with multiple delimiters are handled.
   */
  public function testSplitRowIntoColumnsMultiDelimiter() {

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

    $str_line = 'Line does not actually matter here as test/plain is not supported.';
    $expected_mime_type = 'text/plain';
    $expected_exception_message = "We don't currently support splitting mime types with multiple delimiter options";

    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $instance->splitRowIntoColumns($str_line, $expected_mime_type);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when splitRowIntoColumns() could not split line because text/plain has two supported delimiters and we dont yet know how to pick the right one reliably.');
    $this->assertStringContainsString(
      $expected_exception_message,
      $exception_message,
      'Expected exception message does not match message when splitRowIntoColumns() could not split line because there are too many supported delimiters.'
    );
  }

  /**
   * Tests the ValidatorBase::setLogger() setter
   *       and ValidatorBase::getLogger() getter
   *
   * @return void
   */
  public function testTripalLoggerGetterSetter() {
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

    // Try to get the logger before it has been set
    // Exception message should trigger
    $expected_message = 'Cannot retrieve the Tripal Logger property as one has not been set for this validator using the setLogger() method.';
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $instance->getLogger();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Calling getLogger() before the setLogger() method should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when trying to get the Tripal Logger property but it hasn't been set yet."
    );

    // Create a TripalLogger object and set it using setLogger()
    $my_logger = \Drupal::service('tripal.logger');

    $exception_caught = FALSE;
    try {
      $instance->setLogger($my_logger);
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling setLogger() with a valid TripalLogger object should not have thrown an exception but it threw '$exception_message'"
    );

    // Now make sure we can get the logger that was set
    $grabbed_logger = NULL;
    $exception_caught = FALSE;
    try {
      $grabbed_logger = $instance->getLogger();
    } catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling getLogger() after being set with setLogger() should not have thrown an exception but it threw '$exception_message'"
    );
    $this->assertEquals(
      $my_logger,
      $grabbed_logger,
      'Could not grab the TripalLogger object using getLogger() despite having called setLogger() on it.'
    );
  }
}
