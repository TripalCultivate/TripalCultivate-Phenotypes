<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Test Tripal Cultivate Phenotypes Terms service.
 *
 * @group trpcultivate_phenotypes
 */
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
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

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

    // Install required dependencies.
    $tripal_chado_path = 'modules/contrib/tripal/tripal_chado/src/api/';
    $tripal_chado_api = [
      'tripal_chado.cv.api.php',
      'tripal_chado.variables.api.php',
      'tripal_chado.schema.api.php',
    ];

    if ($handle = opendir($tripal_chado_path)) {
      while (FALSE !== ($file = readdir($handle))) {
        if (strlen($file) > 2 && in_array($file, $tripal_chado_api)) {
          include_once $tripal_chado_path . $file;
        }
      }

      closedir($handle);
    }

    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

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
  public function provideTermIdentifierForGetTermIdMethod() {
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
  public function provideTermsForSaveTermConfigValuesMethod() {
    return [
      // #0: New term.
      [
        'new term',
        [
          'name' => 'New Term',
          'cv' => 'local',
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
          'name' => 'null',
          'cv' => 'null',
        ],
        'location',
        [
          'is_saved' => TRUE,
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
   *     - 'term_exists': boolean value to idicate if a term identifier exits
   *    (TRUE) or of if it is a non-existent identifier (FALSE).
   *
   * @dataProvider provideTermIdentifierForGetTermIdMethod
   */
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
  public function testSaveTermConfigValuesMethod($scenario, $input_term, $term_identifier, $expected) {
    // Create or fectch input term.
    $term_exists = $this->chado_connection->select('1:cvterm', 'cvt')
      ->fields('cvt', ['cvterm_id'])
      ->condition('cvt.name', $input_term['name'])
      ->execute()
      ->fetchField();

    if ($term_exists) {
      $cvterm_id = $term_exists;
    }
    else {
      $cvterm = chado_insert_cvterm($input_term, [], $schema = NULL);
      $cvterm_id = $cvterm->cvterm_id;
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
    $is_loaded = $this->service_PhenoTerms->loadTerms();
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
