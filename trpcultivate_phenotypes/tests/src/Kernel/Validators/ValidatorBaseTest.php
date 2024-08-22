<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\BasicallyBase;

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

    // Create a plugin instance for any validator that uses this function
    $validator_id = 'value_in_list';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Simulates a row within the Trait Importer
    $file_row = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit',
      'Qualitative'
    ];

    // ERROR CASES

    // Provide an empty array of indices
    $context['indices'] = [];
    $instance->context = $context;
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in an empty array of indices.');
    $this->assertStringContainsString('An empty indices array was provided.', $e->getMessage(), "Did not get the expected exception message when providing an empty array of indices.");

    // Provide too many indices
    $context['indices'] = [ 0, 1, 2, 3, 4, 5, 6, 7 ];
    $instance->context = $context;
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in too many indices compared to number of cells in the row.');
    $this->assertStringContainsString('Too many indices were provided (8) compared to the number of cells in the provided row (6)', $e->getMessage(), "Did not get the expected exception message when providing 8 indices compared to 6 values.");

    // Provide invalid indices
    $context['indices'] = [ 1, -4, 77 ];
    $instance->context = $context;
    $exception_caught = FALSE;
    try {
      $validation_status = $instance->validateRow($file_row);
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

    $sets[] = [
      'text/plain',
      ','
    ];

    return $sets;
  }

  /**
   * Test line or row split method.
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
      $instance->splitRowIntoColumns($str_line, 'text/uncertain');
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
      $instance->splitRowIntoColumns($str_line, $expected_mime_type);
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
    $values = $instance->splitRowIntoColumns($str_line, $expected_mime_type);
    $this->assertEquals($good_line, $values, 'Line values does not match expected split values.');
  }
}
