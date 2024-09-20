<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorManager;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;


/**
 * Tests the Duplicate Traits validator
 * NOTE: This validator is specific to the Trait Importer
 * (ie. it is not also used by other importers)
 *
 * @group trpcultivate_phenotypes
 * @group validators
 * @group row_validators
 * @group trait_importer_validators
 */
class ValidatorDuplicateTraitsTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Plugin Manager service.
   *
   * @var TripalCultivatePhenotypesValidatorManager
   */
  protected TripalCultivatePhenotypesValidatorManager $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The genus for configuring and testing with our validator
   *
   * @var string
   */
  protected string $genus = 'Tripalus';

  /**
   * Saves details regarding the config.
   *
   * @var array
   */
  protected array $cvdbon;

  /**
   * The terms required by this module mapped to the cvterm_ids they are set to.
   *
   * @var array
   */
  protected array $terms;

  /**
   * Traits service
   *
   * @var TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_traits;

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

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Create our organism and configure it.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $this->genus,
        'species' => 'databasica',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");
    $this->cvdbon = $this->setOntologyConfig($this->genus);
    $this->terms = $this->setTermConfig();

    // Grab our traits service
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');
    $this->service_traits->setTraitGenus($this->genus);
  }

  /**
   * Test Duplicate Traits Plugin Validator at the file level
   * -- ONLY tests with the context of traits that are being imported and
   *    compared to other traits that exist within the same input file
   */
  public function testValidatorDuplicateTraitsFile() {

    // Create a plugin instance for this validator
    $validator_id = 'duplicate_traits';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Set the genus
    $instance->setConfiguredGenus($this->genus);

    // Simulates a row within the Trait Importer
    $file_row = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit',
      'Quantitative'
    ];

    // Default case: Enter a single row of data
    $expected_valid = TRUE;
    $instance->setIndices(['Trait Name' => 0, 'Method Short Name' => 2, 'Unit' => 4]);
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Duplicate Trait validation was expected to pass when provided the first row of values to validate."
    );
    $unique_traits = $instance->getUniqueTraits();
    $this->assertArrayHasUniqueCombo(
      'My trait', 'My method', 'My unit',
      $unique_traits,
      'Failed to find expected key within the global $unique_traits array for combo #1.'
    );

    // Case #1: Re-renter the same details of the default case, should fail since it's a duplicate of the previous row
    $expected_valid = FALSE;
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Validation was expected to fail when passed in a duplicate trait name + method + unit combination."
    );

    // Case #2: Provide an incorrect key to $context['indices']
    $instance->setIndices([ 'Trait Name' => 0, 'method name' => 2, 'Unit' => 3 ]);
    $exception_caught = FALSE;
    $exception_message = '';
    try {
      $validation_status = $instance->validateRow($file_row);
    }
    catch ( \Exception $e ) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      'Did not catch exception that should have occurred due to passing in the wrong index key "method name" to $context[\'indices\'].'
    );
    $this->assertStringContainsString(
      'The method name (key: Method Short Name) was not set',
      $exception_message,
      "Did not get the expected exception message when providing the wrong index key \"method name\"."
    );

    // Case #3: Enter a second unique row and check our global $unique_traits array
    // Note: unit is at a different index
    $file_row_2 = [
      'My trait 2',
      'My trait description',
      'My method 2',
      'My method description',
      'Qualitative',
      'My unit 2'
    ];

    $expected_valid = TRUE;
    $instance->setIndices([ 'Trait Name' => 0, 'Method Short Name' => 2, 'Unit' => 5 ]);
    $validation_status = $instance->validateRow($file_row_2);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Validation was expected to pass for row #2 which contains a unique trait name + method + unit combination."
    );
    $unique_traits = $instance->getUniqueTraits();
    $this->assertArrayHasUniqueCombo(
      'My trait 2', 'My method 2', 'My unit 2',
      $unique_traits,
      'Failed to find expected key within the global $unique_traits array for combo #2.'
    );

    // Case #4: Enter a third row that has same trait name and method name as row #1, and same unit as row #2.
    // Technically this combo is considered unique and should pass
    $file_row_3 = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit 2',
      'Qualitative'
    ];

    $expected_valid = TRUE;
    $instance->setIndices([ 'Trait Name' => 0, 'Method Short Name' => 2, 'Unit' => 4 ]);
    $validation_status = $instance->validateRow($file_row_3);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Validation was expected to pass for row #3 which contains a unique trait name + method + unit combination."
    );
    $unique_traits = $instance->getUniqueTraits();
    $this->assertArrayHasUniqueCombo(
      'My trait', 'My method', 'My unit 2',
      $unique_traits,
      'Failed to find expected key within the global $unique_traits array for combo #3.'
    );

  }

  /**
   * Test Duplicate Traits Plugin Validator at the database
   * -- ONLY tests with the context of traits that are being imported and
   *    compared to other traits that exist in the database
   */
  public function testValidatorDuplicateTraitsDatabase() {

    // Create a plugin instance for this validator
    $validator_id = 'duplicate_traits';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Set the genus
    $instance->setConfiguredGenus($this->genus);

    // For this test method, indices only need to be set once
    $instance->setIndices([ 'Trait Name' => 0, 'Method Short Name' => 2, 'Unit' => 4 ]);

    // Simulates a row in the input file for the Trait Importer
    // with the column headers as keys
    $file_row_default = [
      'Trait Name' => 'My trait',
      'Trait Description' => 'My trait description',
      'Method Short Name' => 'My method',
      'Collection Method' => 'My method description',
      'Unit' => 'My unit',
      'Type' => 'Quantitative'
    ];

    // Create a simplified array without assigning the column headers as keys
    // for use with our validator directly
    $file_row = array_values($file_row_default);

    // Default case: Validate a single row and check against an empty database
    $expected_valid = TRUE;
    $validation_status = $instance->validateRow($file_row);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Duplicate Trait validation was expected to pass when provided the first row of values to validate and an empty database."
    );

    // Verify this trait isn't in the database
    $my_trait_id = $this->service_traits->getTrait($file_row_default['Trait Name']);
    $expected_trait_id = null;
    $this->assertSame(
      $expected_trait_id,
      $my_trait_id,
      "Duplicate Trait validation did not fail, yet a trait ID was queried from the database for the same trait name."
    );

    // Case #1: Enter a trait into the database first and then try to validate it
    $file_row_case_1 = [
      'Trait Name' => 'My trait 1',
      'Trait Description' => 'My trait description',
      'Method Short Name' => 'My method 1',
      'Collection Method' => 'My method description',
      'Unit' => 'My unit 1',
      'Type' => 'Quantitative'
    ];
    $file_row_1 = array_values($file_row_case_1);

    $combo_ids = $this->service_traits->insertTrait($file_row_case_1);
    $my_trait_record = $this->service_traits->getTrait($file_row_case_1['Trait Name']);
    $expected_trait_id = $combo_ids['trait'];
    $this->assertSame(
      $expected_trait_id,
      $my_trait_record->cvterm_id,
      "The trait ID returned from inserting into the database and the trait ID that was queried for the same trait name do not match."
    );

    // Now that the trait is confirmed to be in the database, our validator should
    // return a fail status when trying to validate the same trait again
    $expected_valid = FALSE;
    $validation_status = $instance->validateRow($file_row_1);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Duplicate Trait validation was expected to fail when provided a row of values for which there already exists a trait+method+unit combo in the database."
    );

    // Case #2: Validate trait details where trait name and method name already
    // exist in the database, but unit is unique
    $file_row_case_2 = [
      'Trait Name' => 'My trait 1',
      'Trait Description' => 'My trait description',
      'Method Short Name' => 'My method 1',
      'Collection Method' => 'My method description',
      'Unit' => 'My unit 2',
      'Type' => 'Quantitative'
    ];
    $file_row_2 = array_values($file_row_case_2);

    $expected_valid = TRUE;
    $validation_status = $instance->validateRow($file_row_2);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Duplicate Trait validation was expected to pass when provided the second row of values to validate the situation where trait and method are in the database but the unit is not."
    );

    // Verify this combo does not exist in the database yet
    $my_trait_2_record = $this->service_traits->getTraitMethodUnitCombo('My trait 1', 'My method 1', 'My unit 2');
    $expected_trait_record = null; // The getTraitMethodUnitCombo method is expected to return null
    $this->assertSame(
      $expected_trait_record,
      $my_trait_2_record,
      "Duplicate Trait validation did not fail, yet a trait ID was queried from the database for the same trait name."
    );

    // Case #3: Validate where a trait + method + unit combo is duplicated in
    // BOTH the database level and the file level
    $file_row_case_3 = [
      'Trait Name' => 'My trait 3',
      'Trait Description' => 'My trait description',
      'Method Short Name' => 'My method 3',
      'Collection Method' => 'My method description',
      'Unit' => 'My unit 3',
      'Type' => 'Quantitative'
    ];
    $file_row_3 = array_values($file_row_case_3);

    $combo_ids_3 = $this->service_traits->insertTrait($file_row_case_3);
    $expected_valid = FALSE;
    $expected_case = 'A duplicate trait was found in the database';
    $validation_status = $instance->validateRow($file_row_3);
    $this->assertSame(
      $expected_valid,
      $validation_status['valid'],
      "Duplicate Trait validation was expected to fail when provided the third row of values to validate where trait + method + unit already exist in the database."
    );
    // Check that we are getting the right error code for a database duplicate
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Duplicate Trait validation did not report that there was a duplicate in the database when validating the third row."
    );

    // Now try validating a row with the exact same values as the previous one
    $file_row_4 = $file_row_3;

    $expected_valid = FALSE;
    $expected_case = 'A duplicate trait was found within both the input file and the database';
    $validation_status = $instance->validateRow($file_row_4);
    $this->assertEquals(
      $expected_valid,
      $validation_status['valid'],
      "Duplicate Trait validation was expected to fail when provided the fourth row of values to validate where trait + method + unit was in the previous row AND exists in the database."
    );
    $this->assertStringContainsString(
      $expected_case,
      $validation_status['case'],
      "Duplicate Trait validation did not report that there was a duplicate in the file AND the database when validating the fourth row."
    );
  }

  /*
  *  Custom assert function to traverse the $unique_traits global array and
  *  check for expected keys
  */
  public function assertArrayHasUniqueCombo(string $trait, string $method, string $unit, array $unique_traits, string $message) {

    // Check all 3 keys at their appropriate array depths
    $this->assertArrayHasKey($trait, $unique_traits, "Missing key: '" . $trait . "'. " . $message);
    $this->assertArrayHasKey($method, $unique_traits[$trait], "Missing key: '" . $method . "'. " . $message);
    $this->assertArrayHasKey($unit, $unique_traits[$trait][$method], "Missing key: '" . $unit . "'. " . $message);
  }
}
