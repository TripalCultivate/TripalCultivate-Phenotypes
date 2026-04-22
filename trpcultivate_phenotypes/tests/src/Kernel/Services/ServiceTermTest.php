<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager;
use Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoCvtermBuddy;
use Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoDbxrefBuddy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test Tripal Cultivate Phenotypes Terms service.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceTermTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Term service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService
   */
  protected $service_PhenoTerms;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'tripal',
    'tripal_layout',
    'tripal_chado',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * Tripal Logger log message.
   *
   * @var string
   */
  private string $log_message;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The Chado Buddy service manager.
   *
   * @var Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager
   */
  protected ChadoBuddyPluginManager $buddy_manager;

  /**
   * The Chado Buddy cvterm.
   *
   * @var \Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoCvtermBuddy
   */

  protected ChadoCvtermBuddy $cvterm_buddy;

  /**
   * The Chado Buddy Dbxref.
   *
   * @var \Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoDbxrefBuddy
   */
  protected ChadoDbxrefBuddy $dbxref_buddy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    $this->buddy_manager = $this->container->get('tripal_chado.chado_buddy');

    // Chado cvterm buddy.
    $this->cvterm_buddy = $this->buddy_manager->createInstance('chado_cvterm_buddy', []);
    // Chado dbxref buddy.
    $this->dbxref_buddy = $this->buddy_manager->createInstance('chado_dbxref_buddy', []);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    $this->prepareEnvironment(['TripalTerm']);

    $this->installConfig('trpcultivate');
    $this->container->get('trpcultivate.setup_module_service')
      ->installTerms();

    // Mock Tripal Logger.
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['error'])
      ->getMock();

    $mock_logger->method('error')
      ->willReturnCallback(function ($message) {
        $this->log_message = $message;
        return NULL;
      }
    );

    $container = \Drupal::getContainer();
    $container->set('tripal.logger', $mock_logger);

    // Term Service.
    $this->service_PhenoTerms = \Drupal::service('trpcultivate_phenotypes.terms');
    $this->assertNotNull($this->service_PhenoTerms, 'Failed to instantiate Terms Service.');

    // Set terms used to create relations.
    $this->setTermConfig();
  }

  /**
   * Data Provider: provides term identifier to test Term Service getTermId().
   *
   * @return array
   *   Each term identifier test scenario is an array with the following values:
   *   - A string, human-readable short descriptionn of the test scenario.
   *   - A string, term identifier input.
   *   - An array of expected values, with the following keys.
   *     - 'term_exists': boolean value to idicate if a term identifier exits
   *    of if it is a non-existent identifier.
   */
  public static function provideTermIdentifierForGetTermIdMethod() {
    return [
      // #0: An integer term identifier.
      [
        'integer term identifier',
        99999,
        [
          'term_exists' => FALSE,
        ],
      ],

      // #1: An empty string value.
      [
        'empty string value',
        '',
        [
          'term_exists' => FALSE,
        ],
      ],

      // #2: A non-existent term indentifier.
      [
        'non-existent term identifier',
        'not a term identifier',
        [
          'term_exists' => FALSE,
        ],
      ],

      // #3: A valid term unique identifier.
      [
        'valid identifier',
        'method_to_trait_relationship_type',
        [
          'term_exists' => TRUE,
        ],
      ],

    ];
  }

  /**
   * Test Term Service getTermId() method.
   *
   * @param string $scenario
   *   A string, human-readable short descriptionn of the test scenario.
   * @param string $input_term_identifier
   *   A string, term identifier input.
   * @param array $expected
   *   An array of expected values, with the following keys.
   *     - 'term_exists': boolean value to indicate if a term identifier exits
   *    (TRUE) or of if it is a non-existent identifier (FALSE).
   *
   * @dataProvider provideTermIdentifierForGetTermIdMethod
   */
  #[DataProvider('provideTermIdentifierForGetTermIdMethod')]
  public function testGetTermId($scenario, $input_term_identifier, $expected) {
    $term_id = $this->service_PhenoTerms->getTermId($input_term_identifier);
    $term_exists = ($term_id > 0) ? TRUE : FALSE;

    $this->assertEquals(
      $expected['term_exists'],
      $term_exists,
      'getTermId() should return ' . $expected['term_exists'] . ' for the input indentifier in scenario ' . $scenario
    );
  }

  /**
   * Data Provider: provides terms to test Term Service saveTermConfigValues().
   *
   * @return array
   *   Each term test scenario is an array with the following values:
   *   - A string, human-readable short descriptionn of the test scenario.
   *   - An array, term input with the following keys:
   *     - 'name': the name of the term.
   *     - 'cv': the cv vocabulary the term will be associated.
   *   - A string, the term identifier of the module the term array will
   *     be saved and mapped to.
   *   - An array of expected values, with the following keys.
   *     - 'is_saved': the expected return value of the method.
   */
  public static function provideTermsForSaveTermConfigValuesMethod() {
    return [
      // #0: New term.
      [
        'new term',
        [
          'cvterm.name' => 'New Term',
          'cv.name' => 'local',
          'db.name' => 'null',
          'dbxref.accession' => 'New Term',
        ],
        'genus',
        [
          'is_saved' => TRUE,
        ],
      ],

      // #1: Existing term.
      [
        'existing term',
        [
          'cvterm.name' => 'null',
          'cv.name' => 'null',
          'db.name' => 'null',
          'dbxref.accession' => 'null',
        ],
        'location',
        [
          'is_saved' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Test Term Service saveTermConfigValues() method.
   *
   * @param string $scenario
   *   A string, human-readable short descriptionn of the test scenario.
   * @param array $input_term
   *   An array, term input with the following keys:
   *     - 'name': the name of the term.
   *     - 'cv': the cv vocabulary the term will be associated.
   * @param string $term_identifier
   *   A string, the term identifier of the module the term array will
   *   be saved and mapped to.
   * @param array $expected
   *   An array of expected values, with the following keys.
   *     - 'is_saved': the expected return value of the method.
   *
   * @dataProvider provideTermsForSaveTermConfigValuesMethod
   */
  #[DataProvider('provideTermsForSaveTermConfigValuesMethod')]
  public function testSaveTermConfigValuesMethod($scenario, $input_term, $term_identifier, $expected) {
    // Create or fectch input term.
    $term_exists = $this->chado_connection->select('1:cvterm', 'cvt')
      ->fields('cvt', ['cvterm_id'])
      ->condition('cvt.name', $input_term['cvterm.name'], '=')
      ->execute()
      ->fetchField();

    if ($term_exists) {
      $cvterm_id = $term_exists;
    }
    else {
      $cvterm = $this->cvterm_buddy->upsertCvterm($input_term, []);
      $cvterm_id = $cvterm->getValue('cvterm.cvterm_id');
    }

    $is_saved = $this->service_PhenoTerms->saveTermConfigValues([$term_identifier => $cvterm_id]);
    $this->assertEquals(
      $expected['is_saved'],
      $is_saved,
      'saveTermConfigValues() should return ' . $expected['is_saved'] . ' in scenario ' . $scenario
    );

    // Test to see if the configuration value is the input term saved.
    $this->assertEquals(
      $this->service_PhenoTerms->getTermId($term_identifier),
      $cvterm_id,
      'saveTermConfigValues() failed to save term in the expected term identifier in scenario ' . $scenario
    );
  }

  /**
   * Data Provider: provide terms to test saveTermConfigValues Invalid term key.
   *
   * @return array
   *   Each term test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An array, term input with the following keys:
   *     - 'name': the name of the term.
   *     - 'cv': the cv vocabulary the term will be associated.
   *   - A string, the term identifier of the module the term array will
   *     be saved and mapped to.
   *   - An array of expected values, with the following keys.
   *     - 'is_saved': the expected return value of the method.
   *     - 'log_messages': the expected error log message.
   */
  public static function provideInvalidTermsForSaveTermConfigValuesMethod() {
    return [
      // #0: Non-existant term key
      [
        'non-existant term',
        [
          'name' => 'null',
          'cv' => 'null',
        ],
        'Invalid term key',
        [
          'is_saved' => FALSE,
          'log_message' => 'Error. Failed to save configuration: Invalid term key=1',
        ],
      ],
      // #1: Empty term key
      [
        'empty term',
        [
          'name' => 'null',
          'cv' => 'null',
        ],
        '',
        [
          'is_saved' => FALSE,
          'log_message' => 'Error. Failed to save configuration: =1',
        ],
      ],
    ];
  }

  /**
   * Test for failure cases in saveTermConfigValues() method with invalid key.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param array $input_term
   *   An array, term input with the following keys:
   *     - 'name': the name of the term.
   *     - 'cv': the cv vocabulary the term will be associated.
   * @param string $term_identifier
   *   A string, the term identifier of the module the term array will
   *   be saved and mapped to.
   * @param array $expected
   *   An array of expected values, with the following keys.
   *     - 'is_saved': the expected return value of the method.
   *     - 'log_messages': the expected error log message.
   *
   * @dataProvider provideInvalidTermsForSaveTermConfigValuesMethod
   */
  #[DataProvider('provideInvalidTermsForSaveTermConfigValuesMethod')]
  public function testSaveTermConfigValuesMethodInvalidKey($scenario, $input_term, $term_identifier, $expected) {
    // Create or fectch input term.
    $cvterm_id = $this->chado_connection->select('1:cvterm', 'cvt')
      ->fields('cvt', ['cvterm_id'])
      ->condition('cvt.name', $input_term['name'], '=')
      ->execute()
      ->fetchField();

    // Test to see if saveTermConfigValues() returns false.
    $is_saved = $this->service_PhenoTerms->saveTermConfigValues([$term_identifier => $cvterm_id]);
    $this->assertEquals(
      $expected['is_saved'],
      $is_saved,
      'saveTermConfigValues() should return ' . $expected['is_saved'] . ' in scenario: ' . $scenario
    );

    // Test whether the correct error log message is returned.
    $this->assertEquals(
      $expected['log_message'],
      $this->log_message,
      "The logged error message does not have the message we expected for in scenario " . $scenario
    );

    // Test to see whether the term is not saved as expected.
    $this->assertEquals(
      $this->service_PhenoTerms->getTermId($term_identifier),
      0,
      'saveTermConfigValues() saved the term even when the key is not existing in scenario: ' . $scenario
    );
  }

  /**
   * Data Provider: provides invalid config values to saveTermConfigValues().
   *
   * @return array
   *   Each term test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An array or a string, invalid input for the config values.
   *   - An array of expected values, with the following keys.
   *     - 'is_saved': the expected return value of the method.
   */
  public static function provideInvalidConfigForSaveTermConfigValuesMethod() {
    return [
      // #0: An Empty array
      [
        'an empty array',
        [],
        [
          'is_saved' => FALSE,
        ],
      ],
      // #1: Is not an array
      [
        'a string',
        '',
        [
          'is_saved' => FALSE,
        ],
      ],
      // #2: Not a registered config name.
      [
        'not a config name',
        [
          'not_a_config_name' => 1,
        ],
        [
          'is_saved' => FALSE,
        ],
      ],
    ];
  }

  /**
   * Test for Failure cases in saveTermConfigValues() with invalid config value.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param mixed $config_value
   *   An array or string, invalid input for the config values.
   * @param array $expected
   *   An array of expected values, with the following keys.
   *     - 'is_saved': the expected return value of the method.
   *
   * @dataProvider provideInvalidConfigForSaveTermConfigValuesMethod
   */
  #[DataProvider('provideInvalidConfigForSaveTermConfigValuesMethod')]
  public function testSaveTermConfigValuesMethodFailure($scenario, $config_value, $expected) {
    // Test when the config values is not valid.
    $is_saved = $this->service_PhenoTerms->saveTermConfigValues($config_value);
    $this->assertEquals(
      $expected['is_saved'],
      $is_saved,
      'saveTermConfigValues() should return FALSE when config_values is ' . $scenario
    );
  }

  /**
   * Test Term Service.
   */
  public function testTermService() {
    // Test defineTerms().
    $define_terms = $this->service_PhenoTerms->defineTerms();
    $keys = array_keys($define_terms);

    $this->assertNotNull($define_terms);
    $this->assertIsArray($define_terms,
      "We expected defineTerms() to return an array but it did not.");

    // Compare what was defined and the pre-defined terms in the
    // config settings file.
    $term_set = $this->config->get('trpcultivate.default_terms.term_set');
    foreach ($term_set as $id => $terms) {
      foreach ($terms['terms'] as $term) {
        $this->assertNotNull($term['config_map']);
        $this->assertArrayHasKey($term['config_map'], $define_terms,
          "The config_map retrieved from config should match one of the keys from defineTerms().");
      }
    }

    // Test loadTerms().
    $is_loaded = $this->service_PhenoTerms->loadTerms($this->testSchemaName);
    $this->assertTrue($is_loaded,
      "We expect loadTerms() to return TRUE to indicate it successfully loaded the terms.");

    // Test values matched to what was loaded into the table.
    foreach ($keys as $term_identifier) {
      $id = $this->service_PhenoTerms->getTermId($term_identifier);
      $this->assertNotNull($id,
        "We should have been able to retrieve the term based on the config_map value but we were not.");
      $this->assertGreaterThan(0, $id,
        "We expect the value returned from getTermId() to be a valid cvterm_id.");

      // Keep track of our expectations.
      // mapping of config key => [cvterm_id, expected cvterm name].
      $cvterm_id = $id;
      $expected_cvterm_name = $define_terms[$term_identifier]['name'];

      $saved_cvterm_name = $this->chado_connection->select('1:cvterm', 'cvt')
        ->fields('cvt', ['name'])
        ->condition('cvt.cvterm_id', $cvterm_id)
        ->execute()
        ->fetchField();

      $this->assertNotNull($saved_cvterm_name,
        "We should have been able to retrieve the term $expected_cvterm_name using the id $cvterm_id but could not.");
      $this->assertEquals(
        $expected_cvterm_name,
        $saved_cvterm_name,
        "The name of the cvterm with the id $cvterm_id did not match the one we expected based on the config key $term_identifier.");
    }
  }

}
