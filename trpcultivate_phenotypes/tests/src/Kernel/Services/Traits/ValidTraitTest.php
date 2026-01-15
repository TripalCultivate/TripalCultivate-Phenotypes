<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services\Traits;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Core\Url;
use Drupal\Core\Database\StatementWrapperIterator;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a valid trait/method/unit combination can be inserted/retrieved.
 *
 * @group trpcultivate_phenotypes
 * @group services
 * @group traits
 */
#[Group('trpcultivate_phenotypes')]
#[Group('services')]
#[Group('traits')]
class ValidTraitTest extends ChadoTestKernelBase {
  use PhenotypeImporterTestTrait;

  /**
   * Plugin Manager service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
   */
  protected $service_traits;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration Factory.
   *
   * @var config_entity
   */
  protected $config;

  /**
   * A test genus.
   *
   * @var string
   */
  private $genus;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * Term config key to cvterm_id mapping.
   *
   * Note: we just grabbed some random cvterm_ids that we know for sure exist.
   */
  protected array $terms = [
    'method_to_trait_relationship_type' => 100,
    'unit_to_method_relationship_type' => 200,
    'trait_to_synonym_relationship_type' => 300,
    'unit_type' => 400,
  ];

  /**
   * CV and DB's configured for this genus.
   *
   * NOTE: We will create these in the setUp.
   */
  protected array $cvdbon;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $this->prepareEnvironment(['TripalTerm']);

    // Open connection to Chado.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Install module configuration/settings.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->installSchema('trpcultivate_phenotypes', ['trpcultivate_phenocombo']);

    // Configure the module.
    $this->genus = 'Tripalus';
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $this->genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing.');
    $this->cvdbon = $this->setOntologyConfig($this->genus);
    $this->terms = $this->setTermConfig();

    // Set the traits service.
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');
  }

  /**
   * Tests that inserting a trait/method/unit populates the chado as we expect.
   */
  public function testTraitsServiceDatabaseExpectations() {
    // Generate some fake/unique names.
    $trait_name  = 'TraitABC' . uniqid();
    $method_name = 'MethodABC' . uniqid();
    $unit_name   = 'UnitABC' . uniqid();

    // Now bring these together into the array of values
    // requested by the insertTrait() method.
    $trait = [
      'Trait Name' => $trait_name,
      'Trait Description' => $trait_name . ' Description',
      'Method Short Name' => $method_name . '-SName',
      'Collection Method' => $method_name . ' - Pull from ground',
      'Unit' => $unit_name,
      'Type' => 'Quantitative',
    ];

    // Set genus to use by the traits service.
    $this->service_traits->setTraitGenus($this->genus);

    // Get schema name.
    $schema = $this->chado_connection->getSchemaName();

    // Save the trait.
    $trait_assets = $this->service_traits->insertTrait($trait, $schema);

    // Trait, method and unit.
    $sql = "SELECT * FROM {1:cvterm} WHERE cvterm_id = :id LIMIT 1";

    foreach ($trait_assets as $type => $value) {
      // Retrieve the cvterm with the cvterm_di returned by the service.
      $rec = $this->chado_connection->query($sql, [':id' => $value])
        ->fetchObject();
      $this->assertIsObject($rec,
        "We were unable to retrieve the $type record from chado based on the cvterm_id $value provided by the service.");

      // The was configured in setUp and is keyed by the type.
      $expected_cv = $this->cvdbon[$type]['cv_id'];
      // Ensure it was inserted into the correct cv the genus is configured for.
      $this->assertEquals($expected_cv, $rec->cv_id,
        "Failed to insert $type into cv genus is configured.");

      // Check that the name of the cvterm is as we expect.
      $expected_name = NULL;
      if ($type == 'trait') {
        $expected_name = $trait['Trait Name'];
      }
      if ($type == 'method') {
        $expected_name = $trait['Method Short Name'];
      }
      if ($type == 'unit') {
        $expected_name = $trait['Unit'];
      }
      $this->assertEquals($expected_name, $rec->name,
        "The name in the database for the $type did not match the one we expected.");
    }

    // Test relationships.
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship}
      WHERE subject_id = :s_id AND type_id = :t_id AND object_id = :o_id";

    // Method - trait.
    // @todo this relationship is currently in the wrong order
    // for the term we choose but is the same order as in AP.
    // Expected "Measured with ruler" is "Method" of "Plant Height"
    // but is currently saved as "Plant Height" is
    // "Method" of "Measured with ruler".
    $rec = $this->chado_connection->query($sql, [
      ':s_id' => $trait_assets['trait'],
      ':t_id' => $this->terms['method_to_trait_relationship_type'],
      ':o_id' => $trait_assets['method'],
    ]);
    $this->assertNotNull($rec,
      'Failed to insert a relationship between the method and it\'s trait.');

    // Method - unit.
    // @todo this relationship is currently in the wrong order
    // for the term we choose but is the same order as in AP.
    // Expected "cm" is "Unit" of "Measured with Ruler"
    // but it is currently saved as "Measures with ruler" is "Unit" of "cm"
    $rec = $this->chado_connection->query($sql, [
      ':s_id' => $trait_assets['method'],
      ':t_id' => $this->terms['unit_to_method_relationship_type'],
      ':o_id' => $trait_assets['unit'],
    ]);
    $this->assertNotNull($rec,
      'Failed to insert a relationship between the method and it\'s unit.');

    // Test unit data type.
    $sql = "SELECT cvtermprop_id, value FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado_connection->query($sql, [
      ':c_id' => $trait_assets['unit'],
      ':t_id' => $this->terms['unit_type'],
    ])->fetchObject();

    $this->assertNotNull($data_type, 'Failed to insert unit property - additional type.');
    $this->assertEquals($data_type->value, 'Quantitative', 'Unit property - additional type does not match expected value (Quantitative).');
  }

  /**
   * Test setTraitGenus() method for error cases.
   */
  public function testSetTraitGenus() {
    // Test case for where the genus is not configured.
    $exception_message = '';
    try {
      $this->service_traits->setTraitGenus('Test Genus');
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      'The genus "Test Genus" was not configured for
      use with Tripal Cultivate Phenotypes. To configure this genus, go to ' .
      Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString() .
      ' and set the controlled vocabularies associated with this genus.',
      $exception_message,
      "We expected an exception to be caught when the genus is not configured, but one wasn't thrown.",
    );

    // Test case for where the name is not returned.
    $mock_statement = $this->getMockBuilder(StatementWrapperIterator::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['fetchField'])
      ->getMock();
    $mock_statement->method('fetchField')
      ->willReturn(NULL);

    $mock_database = $this->getMockBuilder(ChadoConnection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['query'])
      ->getMock();
    $mock_database->method('query')
      ->willReturn($mock_statement);

    $this->container->set('tripal_chado.database', $mock_database);

    $this->container->set('trpcultivate_phenotypes.traits', NULL);
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');

    $exception_message = '';
    try {
      $this->service_traits->setTraitGenus($this->genus);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertStringStartsWith(
      'We were unable to retrieve the name for the Genus ',
      $exception_message,
      "We expected an exception to be caught when the name for genus was not
      found, but the error was not thrown.",
    );

    // Test case where the terms are not set correctly.
    $new_terms = [
      'method_to_trait_relationship_type' => 0,
      'unit_to_method_relationship_type' => -2,
    ];
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');
    foreach ($new_terms as $key => $value) {
      $this->config->set("trpcultivate.phenotypes.ontology.terms.$key", $new_terms[$key]);
    }

    $this->container->set('trpcultivate_phenotypes.traits', NULL);
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');

    $exception_message = '';
    try {
      $this->service_traits->setTraitGenus($this->genus);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      'Term(s) method_to_trait_relationship_type, unit_to_method_relationship_type used to create trait
      asset relationships was not configured. To configure terms, go to'
      . Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString() .
      'and set the controlled vocabulary associated with the term.',
      $exception_message,
      "We expected an exception to be caught when the terms are not configured, but one wasn't thrown.",
    );
  }

  /**
   * Test the insertTrait() method when the genus is not set.
   */
  public function testInsertTraitUnsetGenus() {
    // Set the genus to null.
    $this->genus = NULL;

    // Generate some fake/unique names.
    $trait_name  = 'TraitABC' . uniqid();
    $method_name = 'MethodABC' . uniqid();
    $unit_name   = 'UnitABC' . uniqid();
    $trait       = [
      'Trait Name' => $trait_name,
      'Trait Description' => $trait_name . ' Description',
      'Method Short Name' => $method_name . '-SName',
      'Collection Method' => $method_name . ' - Pull from ground',
      'Unit' => $unit_name,
      'Type' => 'Quantitative',
    ];

    $exception_message = '';
    try {
      $this->service_traits->insertTrait($trait);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      'No genus has been set. See setting a genus in the
        Traits Service and make sure to use a configured genus. To configure a
        genus or see all configured genus, go to ' .
        Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString(),
      $exception_message,
      "We expected an exception to be caught when no genus is set, but one wasn't thrown.",
    );
    // Set the genus back to original genus.
    $this->genus = 'Tripalus';
  }

  /**
   * Test that we can retrieve a trait we just inserted.
   */
  public function testTraitsServiceGetters() {
    // Set the genus.
    $this->service_traits->setTraitGenus($this->genus);

    // Test Data.
    // Keys.
    $keys = [
      'trait' => 'Trait Name',
      'method' => 'Method Short Name',
      'unit' => 'Unit',
    ];

    $test_combo = [
      [
        $keys['trait']  => 'A Trait',
        $keys['method'] => 'A Method',
        $keys['unit']   => 'A Unit',
      ],

      // This is trait A Trait
      // New method
      // Re-use C Unit.
      [
        $keys['trait']  => 'A Trait',
        $keys['method'] => 'B Method',
        $keys['unit']   => 'A Unit',
      ],

      // This is trait A Trait
      // New Method
      // Re-use C Unit.
      [
        $keys['trait']  => 'A Trait',
        $keys['method'] => 'C Method',
        $keys['unit']   => 'A Unit',
      ],

      // This is trait A Trait
      // Re-use B Method
      // New Unit.
      [
        $keys['trait']  => 'A Trait',
        $keys['method'] => 'B Method',
        $keys['unit']   => 'B Unit',
      ],

      // This is trait A Trait
      // Re-use B Method
      // New Unit.
      [
        $keys['trait']  => 'A Trait',
        $keys['method'] => 'B Method',
        $keys['unit']   => 'C Unit',
      ],

      // This is new trait B Trait
      // Re-use B Method
      // Re-use A Unit.
      [
        $keys['trait']  => 'B Trait',
        $keys['method'] => 'B Method',
        $keys['unit']   => 'A Unit',
      ],

      // This is trait B Trait
      // Re-use B Method
      // New Unit.
      [
        $keys['trait']  => 'B Trait',
        $keys['method'] => 'B Method',
        $keys['unit']   => 'D Unit',
      ],

      // This is trait B Trait
      // New method
      // Re-use C Unit.
      [
        $keys['trait']  => 'B Trait',
        $keys['method'] => 'C Method',
        $keys['unit']   => 'C Unit',
      ],

      // This is trait C Trait
      // New method
      // New Unit.
      [
        $keys['trait']  => 'C Trait',
        $keys['method'] => 'D Method',
        $keys['unit']   => 'E Unit',
      ],
    ];

    // Summary:
    // A Trait has 3 methods - A, B, C Method
    // B Trait has 2 methods - B and C Method
    // C Trait has 1 method - C Method
    // A Method has 1 unit - A Unit
    // B Method has 4 units - A, B, C, and D Unit
    // C Method has 2 units - A and C Unit.
    // D Method has 1 unit - E Unit.
    // Construct trait asset array.
    $expected_cvterms = [];

    foreach ($test_combo as $i => $combo) {
      $data_type = ($i % 2 == 0) ? 'Qualitative' : 'Quantitative';

      // Force E Unit to be Quantitative for the test.
      if ($combo['Unit'] == 'E Unit') {
        $data_type = 'Quantitative';
      }

      $ins_trait = [
        'Trait Name' => $combo['Trait Name'],
        'Trait Description' => $combo['Trait Name'] . ' Description',
        'Method Short Name' => $combo['Method Short Name'],
        'Collection Method' => $combo['Method Short Name'] . ' Collection Method',
        'Unit' => $combo['Unit'],
        'Type' => $data_type,
      ];

      // Set genus to use by the traits service.
      // This method will return the inserted cvterm ids.
      $trait_assets = $this->service_traits->insertTrait($ins_trait);

      // Track the ids.
      $expected_cvterms[$combo['Trait Name']] = $trait_assets['trait'];
      $expected_cvterms[$combo['Method Short Name']] = $trait_assets['method'];
      $expected_cvterms[$combo['Unit']] = $trait_assets['unit'];

      // Check trait assets got inserted.
      $trait_combo = $this->service_traits->getTraitMethodUnitCombo($combo['Trait Name'], $combo['Method Short Name'], $combo['Unit']);

      // Trait.
      $this->assertEquals($trait_assets['trait'], $trait_combo['trait']->cvterm_id,
        'Test trait ' . $combo['Trait Name'] . ' was not inserted.');
      // Method.
      $this->assertEquals($trait_assets['method'], $trait_combo['method']->cvterm_id,
        'Test ' . $combo['Method Short Name'] . ' was not inserted.');
      // Unit.
      $this->assertEquals($trait_assets['unit'], $trait_combo['unit']->cvterm_id,
        'Test unit ' . $combo['Unit'] . ' was not inserted.');
    }

    // At this point, all traits should be in, test the relationships,
    // connections and all.
    // Test nothing got inserted more than once and re-using a trait asset meant
    // that it just referenced existing asset and not creating another copy
    // in the same cv the genus is configured.
    // A Trait was re-used 5x, test that there is only one inserted.
    $a_trait = $trait = $this->service_traits->getTrait('A Trait');
    $this->assertEquals($a_trait->cvterm_id, $expected_cvterms['A Trait'], 'A Trait has duplicate values');

    // Test get trait using trait name or trait id number as parameter
    // to getTrait() method.
    foreach ($test_combo as $combo) {
      $name_key = 'Trait Name';

      // By string parameter.
      $trait = $this->service_traits->getTrait($combo[$name_key]);
      $trait_id = (int) $trait->cvterm_id;
      $this->assertNotEquals($trait->cvterm_id, 0, 'Failed to fetch trait ' . $combo[$name_key] . ' (by trait name parameter).');

      // By id number parameter.
      $trait = $this->service_traits->getTrait($trait->cvterm_id);
      $this->assertNotEquals($trait->cvterm_id, 0, 'Failed to fetch trait ' . $combo[$name_key] . ' (by trait id parameter).');

      // Either cases both should match.
      $this->assertEquals($trait_id, (int) $trait->cvterm_id,
        'Trait id returned by trait getter with string and integer parameters do not match.');

      // Id number is the id number that got created by the insert method.
      $this->assertEquals($trait_id, $expected_cvterms[$combo['Trait Name']],
        'Trait id returned by trait getter does not match the trait id inserted.');
    }

    // Test get trait method using trait name or trait id as parameter
    // to getTraitMethod() method.
    // Test getTraitMethod() with invalid trait name.
    $this->service_traits->getTraitMethod('Invalid Trait');

    // Based on the summary of traits assets above, test the following.
    // 1. A Trait has 3 methods - A, B, C Method.
    // 2. C Trait has 1 method - D Method.
    $a_trait_methods_byname = $this->service_traits->getTraitMethod('A Trait');
    $a_trait                = $expected_cvterms['A Trait'];
    $a_trait_methods_byid   = $this->service_traits->getTraitMethod($a_trait);

    // Assert that in both cases, the returned set of methods was the same.
    // Then proceed to assert that methods returned are the expected methods.
    $this->assertEquals($a_trait_methods_byname, $a_trait_methods_byid,
      'A Trait methods returned by methods getter with name and id as parameter do not match.');

    // 3 methods in the set?
    $this->assertCount(3, $a_trait_methods_byid, 'A Trait methods returned by methods getter does not match expected count (3).');

    foreach (['A', 'B', 'C'] as $expected) {
      $method_name = $expected . ' Method';
      $a_found = FALSE;

      foreach ($a_trait_methods_byid as $a_m) {
        if ($a_m->name == $method_name) {
          $a_found = TRUE;
          break;
        }
      }

      $this->assertTrue($a_found, 'The method ' . $method_name . ' was not found in the trait methods.');
    }

    // The same steps for C Trait.
    $c_trait_methods_byname = $this->service_traits->getTraitMethod('C Trait');
    $c_trait                = $expected_cvterms['C Trait'];
    $c_trait_methods_byid   = $this->service_traits->getTraitMethod($c_trait);

    $this->assertEquals($c_trait_methods_byname, $c_trait_methods_byid,
      'C Trait methods returned by methods getter with name and id as parameter do not match.');

    $this->assertCount(1, $c_trait_methods_byid, 'C Trait methods returned by methods getter does not match expected count (1).');

    $this->assertEquals($c_trait_methods_byid[0]->name, 'D Method', 'The method D Method was not found in the trait methods.');

    // Test get unit method using method name or method id as parameter
    // to getMethodUnit() method.
    // Test getMethodUnit() method with invalid method.
    $this->service_traits->getMethodUnit('Invalid Method');

    // Based on the summary of traits assets above, test the following.
    // B Method has 4 units - A, B, C, and D Unit
    // D Method has 1 unit - E Unit.
    $b_method_units_byname = $this->service_traits->getMethodUnit('B Method');
    $b_method              = $expected_cvterms['B Method'];
    $b_method_units_byid   = $this->service_traits->getMethodUnit($b_method);

    $this->assertEquals($b_method_units_byname, $b_method_units_byid,
      'B Method units returned by units getter with name and id as parameter do not match.');

    $this->assertCount(4, $b_method_units_byid, 'B Method units returned by units getter does not match expected count (4).');

    foreach (['A', 'B', 'C', 'D'] as $expected) {
      $unit_name = $expected . ' Unit';
      $a_found = FALSE;

      foreach ($b_method_units_byid as $a_u) {
        if ($a_u->name == $unit_name) {
          $a_found = TRUE;
          break;
        }
      }

      $this->assertTrue($a_found, 'The method unit ' . $unit_name . ' was not found in the method units.');
    }

    // The same steps for D Method.
    $d_method_units_byname = $this->service_traits->getMethodUnit('D Method');
    $d_method              = $expected_cvterms['D Method'];
    $d_method_units_byid   = $this->service_traits->getMethodUnit($d_method);

    $this->assertEquals($d_method_units_byname, $d_method_units_byid,
      'D Method units returned by units getter with name and id as parameter do not match.');

    $this->assertCount(1, $d_method_units_byid, 'D Method units returned by units getter does not match expected count (1).');

    $this->assertEquals($d_method_units_byid[0]->name, 'E Unit', 'The unit E Unit was not found in the method units.');

    // Test get unit data type method using unit name or unit id as parameter
    // to getMethodUnitDataType() method.
    // Test getMethodUnitDataType() with invalid unit.
    $this->service_traits->getMethodUnitDataType('Invalid Unit');

    // From the trait asset insert test, E Unit was set to
    // Qualitative data type.
    $e_unit_type_byname = $this->service_traits->getMethodUnitDataType('E Unit');
    $e_unit             = $expected_cvterms['E Unit'];
    $e_unit_type_byid   = $this->service_traits->getMethodUnitDataType($e_unit);

    // Assert that in both cases, the returned data types were the same.
    $this->assertEquals($e_unit_type_byname, $e_unit_type_byid,
      'E Unit data type returned by unit type getter with name and id as parameter do not match.');

    // Is it Quantitative?
    $this->assertEquals($e_unit_type_byid, 'Quantitative',
      'E Unit data type returned by unit type getter does not match expected data type (Quantitative).');

    // Test to trigger Multiple data types error.
    // Insert two values with the same cvterm_id and type_id.
    $this->chado_connection->insert('1:cvtermprop')
      ->fields([
        'cvterm_id' => $trait_assets['unit'],
        'type_id' => $this->terms['unit_type'],
        'value' => 'Value 1',
      ])
      ->execute();

    $this->chado_connection->insert('1:cvtermprop')
      ->fields([
        'cvterm_id' => $trait_assets['unit'],
        'type_id' => $this->terms['unit_type'],
        'value' => 'Value 2',
      ])
      ->execute();

    $exception_message = '';
    try {
      $this->service_traits->getMethodUnitDataType('E Unit');
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertStringStartsWith(
      'A multiple data type error occurred while retrieving',
      $exception_message,
      "We expected an exception to be caught when multiple data types for
      one unit is found, but one wasn't thrown.",
    );

    // Test the getPhenoCvTerm() method.
    // Create a reflaction class to access protected method.
    $reflection = new \ReflectionClass($this->service_traits);

    // Set accessible for protected method.
    $method = $reflection->getMethod('getPhenoCvTerm');
    $method->setAccessible(TRUE);

    // Select E unit from cvterm table to get cv_name and cvtem_id.
    $sql = "SELECT * FROM {1:cvterm} AS ct JOIN {1:cv} USING (cv_id) WHERE ct.name = :name;";
    $query = $this->chado_connection->query($sql, [
      ':name' => 'E Unit',
    ])->fetchAll();
    $vocab_name = $query[0]->name;
    $cvterm_id = $query[0]->cvterm_id;

    // Updating the term to make it obsolete.
    $this->chado_connection->update('1:cvterm')
      ->fields(['is_obsolete' => 1])
      ->condition('cvterm_id', $cvterm_id)
      ->execute();

    // Set the vocab_name in $values to be the cv_name we got above.
    $values = ['vocab_name' => $vocab_name, 'term' => ['name' => 'E Unit']];
    $this->createTripalTerm($values, 'chado_id_space', 'chado_vocabulary');

    $exception_message = '';
    try {
      $method->invokeArgs($this->service_traits, ['E Unit', 'unit']);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertStringStartsWith(
      'A duplicate term error occurred while retrieving a trait asset.
          Failed to retrieve $type : $key in cv : ',
      $exception_message,
      "We expected an exception to be caught when duplicate terms are found,
      but one wasn't thrown.",
    );

    // Test the getPhenoCvterm() method with Invalid trait asset type.
    $exception_message = '';
    try {
      $method->invokeArgs($this->service_traits, ['E unit', 'Invalid']);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      'Not a valid trait asset type value provided. Trait
        asset getter expects type to be the string trait, method or unit.',
      $exception_message,
      "We expected an exception to be caught when invalid trait asset type value
       is povided, but one wasn't thrown.",
    );

    // Test the no genus error within the method getPhenoCvTerm().
    $this->container->set('trpcultivate_phenotypes.traits', NULL);
    $this->service_traits = \Drupal::service('trpcultivate_phenotypes.traits');

    $exception_message = '';
    try {
      $this->service_traits->getMethodUnitDataType('E Unit');
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      'No genus has been set. See setting a genus in the
        Traits Service and make sure to use a configured genus. To configure a
        genus or see all configured genus, go to ' .
        Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString(),
      $exception_message,
      "We expected an exception to be caught when no genus is configured,
      but one wasn't thrown.",
    );
  }

  /**
   * Test that we can retrieve a trait that was inserted.
   *
   * Using the trait, method and unit combination, of which each can either be
   * the the id or name.
   */
  public function testTraitsServiceComboGetters() {
    // Set genus to use by the traits service.
    $this->service_traits->setTraitGenus($this->genus);

    // Generate some fake combination.
    $trait = [
      'trait' => 'Trait Name Combo' . uniqid(),
      'method' => 'Method Name Combo' . uniqid(),
      'unit' => 'Unit Name Combo' . uniqid(),
    ];

    $combo = [
      'Trait Name' => $trait['trait'],
      'Trait Description' => 'A trait name combo',
      'Method Short Name' => $trait['method'],
      'Collection Method' => 'A trait method collection method',
      'Unit' => $trait['unit'],
      'Type' => 'Quantitative',
    ];

    $trait_assets = $this->service_traits->insertTrait($combo);

    // Ids.
    $trait_id = $trait_assets['trait'];
    $method_id = $trait_assets['method'];
    $unit_id = $trait_assets['unit'];

    // Invalid parameter error. For all 3 trait asset - trait, method and unit
    // No 0, empty string or negative number.
    $test_missing_param = [
      ['', $method_id, $unit_id],
      [$trait_id, '', $unit_id],
      [$trait_id, $method_id, ''],
      ['', '', ''],
      [0, $method_id, $unit_id],
      [$trait_id, 0, $unit_id],
      [$trait_id, $method_id, 0],
      [0, 0, 0],
      [-1, $method_id, $unit_id],
      [$trait_id, -1, $unit_id],
      [$trait_id, $method_id, -1],
      [-1, -1, -1],
    ];

    foreach ($test_missing_param as $test) {
      $trait_val  = $test[0];
      $method_val = $test[1];
      $unit_val   = $test[2];

      $exception_message = '';
      try {
        $this->service_traits->getTraitMethodUnitCombo($trait_val, $method_val, $unit_val);
      }
      catch (\Exception $e) {
        $exception_message = $e->getMessage();
      }

      $this->assertMatchesRegularExpression('/Not a valid (trait|method|unit) key value provided/', $exception_message, 'Invalid parameter error message does not match expected error.');
    }

    // Not found.
    $test_not_found = [
      ['Not found trait', $method_id, $unit_id],
      [$trait_id, 'Not found method', $unit_id],
      [$trait_id, $method_id, 'Not found unit'],
    ];

    foreach ($test_not_found as $test) {
      $trait_val  = $test[0];
      $method_val = $test[1];
      $unit_val   = $test[2];

      $combo = $this->service_traits->getTraitMethodUnitCombo($trait_val, $method_val, $unit_val);
      $this->assertEquals($combo, NULL, 'The combo getter should have returned null for a non existent combo.');
    }

    unset($combo);
    // All parameters as id number (integer).
    $combo = $this->service_traits->getTraitMethodUnitCombo($trait_id, $method_id, $unit_id);
    $this->assertEquals($combo['trait']->name, $trait['trait'], 'Trait name does not match expected trait name.');
    $this->assertEquals($combo['method']->name, $trait['method'], 'Trait name does not match expected method name.');
    $this->assertEquals($combo['unit']->name, $trait['unit'], 'Trait name does not match expected unit name.');

    // All parameters as name (string).
    $combo = $this->service_traits->getTraitMethodUnitCombo($trait['trait'], $trait['method'], $trait['unit']);
    $this->assertEquals($combo['trait']->name, $trait['trait'], 'Trait name does not match expected trait name.');
    $this->assertEquals($combo['method']->name, $trait['method'], 'Trait name does not match expected method name.');
    $this->assertEquals($combo['unit']->name, $trait['unit'], 'Trait name does not match expected unit name.');

    // Mix type parameters (string and integer).
    $combo = $this->service_traits->getTraitMethodUnitCombo($trait_id, $trait['method'], $unit_id);
    $this->assertEquals($combo['trait']->name, $trait['trait'], 'Trait name does not match expected trait name.');
    $this->assertEquals($combo['method']->name, $trait['method'], 'Trait name does not match expected method name.');
    $this->assertEquals($combo['unit']->name, $trait['unit'], 'Trait name does not match expected unit name.');

    // Check that the unit has extra property data type.
    $this->assertEquals($combo['unit']->data_type, 'Quantitative', 'Unit data_type property value does not match expected type (Quantitative).');

    // All 3 parameters to the method is a unit term.
    // Unit exists but not in the Trait CV the genus is configured.
    $exception_message = '';
    try {
      $this->service_traits->getTraitMethodUnitCombo($unit_id, $unit_id, $unit_id);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }

    $this->assertMatchesRegularExpression('/CV value does not match the CV the genus was configured/',
      $exception_message, 'Combo getter failed parameter (all parameter a unit id) does not match the expected exception error message.');
  }

  /**
   * Test getExperimentTraitMethodUnitCombos().
   */
  public function testGetExperimentTraitMethodUnitCombos() {
    // Generate some fake/unique names.
    $trait_name  = 'TraitABC' . uniqid();
    $method_name = 'MethodABC' . uniqid();
    $unit_name   = 'UnitABC' . uniqid();

    // Now bring these together into the array of values
    // requested by the insertTrait() method.
    $trait = [
      'Trait Name' => $trait_name,
      'Trait Description' => $trait_name . ' Description',
      'Method Short Name' => $method_name . '-SName',
      'Collection Method' => $method_name . ' - Pull from ground',
      'Unit' => $unit_name,
      'Type' => 'Quantitative',
    ];

    // Set genus to use by the traits service.
    $this->service_traits->setTraitGenus($this->genus);

    // Get schema name.
    $schema = $this->chado_connection->getSchemaName();

    // Save the trait.
    $trait_assets = $this->service_traits->insertTrait($trait, $schema);

    $experiment_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => 'Test Project 1'])
      ->execute();

    $k = $this->container->get('database')
      ->insert('trpcultivate_phenocombo')
      ->fields([
        'project_id' => $experiment_id,
        'attr_id' => $trait_assets['trait'],
        'observable_id' => $trait_assets['method'],
        'unit_id' => $trait_assets['unit'],
        'label' => 'Label' . uniqid(),
        'is_archived' => mt_rand(0, 1),
        'is_required' => mt_rand(0, 1),
        'was_shared' => mt_rand(0, 1),
        'was_collected' => mt_rand(0, 1),
        'uid' => $this->container->get('current_user')->id(),
        'timestamp' => time(),
      ])
      ->execute();

      $combo_format = [
        'header' => [
          'combo_id',
          'name',
          'description',
          'type',
        ],
        'component' => [
          'combo_id',
          'name',
          'description',
        ],
        'full' => [
          'combo_id',
          'label',
          'project_id',
          'experiment',
          'attr_id',
          'observable_id',
          'unit_id',
          'is_required',
          'is_archived',
          'was_collected',
          'was_shared',
          'uid',
        ],
      ];

    // Full format option.
    $combos = $this->service_traits->getExperimentTraitMethodUnitCombos($experiment_id);
    $label = array_keys($combos)[0];

    foreach ($combo_format['full'] as $key) {
      $this->assertNotNull(
        $combos[$label][$key],
        'Experiment trait combo is expected to contain key: ' . $key
      );
    }
  }

}
