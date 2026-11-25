<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Plugin\ChadoField\Formatter;

use Drupal\Core\Render\Element;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\tripal_chado\Traits\ChadoFieldTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Chado method unit field formatter.
 */
#[Group('tripal-field')]
#[Group('chado-field')]
#[RunTestsInSeparateProcesses]
class ChadoMethodUnitFormatterCRUDTest extends ChadoTestKernelBase {

  use ChadoFieldTestTrait;
  use PhenotypeImporterTestTrait;

  /**
   * The modules that this test depends on.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'field',
    'datetime',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * The YAML file describing the test field and formatter scenario.
   *
   * @var string
   */
  protected string $yaml_info_file = __DIR__ . '/ChadoMethodUnitFormatterCRUD-TestInfo.yml';

  /**
   * The Chado connection used by the test.
   */
  protected ChadoConnection $chado_connection;

  /**
   * The system under test loaded from YAML.
   */
  protected array $system_under_test;

  /**
   * The test bundle ID.
   */
  protected string $bundle_name;

  /**
   * Trait assets.
   */
  protected array $trait_assets;

  /**
   * The test scenarios loaded from YAML.
   */
  protected array $scenarios;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    [$this->system_under_test, $this->scenarios] = $this->getTestInfoFromYaml($this->yaml_info_file);
    $this->bundle_name = $this->system_under_test['bundle']['id'];
    $this->chado_connection = $this->getTestSchema(
      ChadoTestKernelBase::PREPARE_TEST_CHADO,
      $this->system_under_test['chado_version']
    );

    $this->installConfig(['trpcultivate_phenotypes']);
    $this->installSchema('trpcultivate_phenotypes', ['trpcultivate_phenocombo']);

    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => 'Lens',
        'species' => 'culinaris',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id, 'We were not able to create an organism for testing.');

    $this->setTermConfig();
    $this->setupChadoEntityFieldTestEnvironment($this->system_under_test);
    $this->installSchema('tripal_chado', ['tripal_custom_tables', 'tripal_mviews']);
    $this->container->get('tripal_chado.terms_init')->installTerms();
    $this->container->get('trpcultivate_phenotypes.terms')->loadTerms($this->chado_connection->getSchemaName());
    $this->setOntologyConfig('Lens');

    $traits = $this->container->get('trpcultivate_phenotypes.traits');
    $traits->setTraitGenus('Lens');
    $this->trait_assets = $traits->insertTrait([
      'Trait Name' => 'Plant Height',
      'Trait Description' => 'A plant height trait is the vertical dimension of a plant service as a significant morphological characteristic',
      'Method Short Name' => 'PLHT',
      'Collection Method' => "Place a measuring tape, ruler, or meter stick at the soil surface or the base of the pot and extend it to the highest point of the plant's canopy or apical bud. Record the measurement, along with the date, to track growth over time.",
      'Unit' => 'cm',
      'Type' => 'Quantitative',
    ]);
  }

  /**
   * Data Provider: works with the YAML to provide scenarios for testing.
   *
   * @return array
   *   List of scenarios to test where each one matches a key and label in the
   *   associated YAML scenarios.
   */
  public static function provideScenarios() {
    $scenarios = [];

    $scenarios[] = [
      0,
      "Method with unit",
    ];

    return $scenarios;
  }

  /**
   * Retrieves the current scenario based on the data provider.
   *
   * NOTE: Also ensures the type_ids match what is currently in the database.
   *
   * @param int $current_scenario_key
   *   The key of the scenario in the YAML.
   * @param string $current_scenario_label
   *   The label of the scenario in the YAML.
   *
   * @return array
   *   The scenario to be tested as defined in the YAML.
   */
  public function retrieveCurrentScenario(int $current_scenario_key, string $current_scenario_label) {

    // Retrieve the correct scenario.
    $current_scenario = $this->scenarios[$current_scenario_key];

    $this->assertEquals($current_scenario_label, $current_scenario['label'], "We may not have retrieved the expected scenario as the labels did not match.");

    return $current_scenario;
  }

  /**
   * Tests the method summary and its associated units are rendered.
   *
   * @param int $current_scenario_key
   *   The key of the scenario in the YAML.
   * @param string $current_scenario_label
   *   The label of the scenario in the YAML.
   *
   * @dataProvider provideScenarios
   */
  #[DataProvider('provideScenarios')]
  public function testFormatterRendersMethodAndUnits(int $current_scenario_key, string $current_scenario_label): void {
    $current_scenario = $this->retrieveCurrentScenario($current_scenario_key, $current_scenario_label);

    $trait_cvterm_id = (int) $this->trait_assets['trait'];
    $trait_cv_id = $this->chado_connection->query('SELECT cv_id FROM {1:cvterm} WHERE cvterm_id = :id', [':id' => $trait_cvterm_id])->fetchField();

    $current_scenario['user_input']['trait_cv'][0]['value'] = $trait_cv_id;
    $current_scenario['user_input']['trait_method'][0]['cvterm_id'] = (int) $this->trait_assets['method'];

    // Create the entity with that value set.
    $entity = TripalEntity::create([
      'title' => $this->randomString(),
      'type' => $this->bundle_name,
    ] + $current_scenario['user_input']);

    $this->assertInstanceOf(TripalEntity::class, $entity, "We were not able to create a piece of tripal content to test our " . $current_scenario['label'] . " scenario.");
    $status = $entity->save();
    $this->assertEquals(SAVED_NEW, $status, "We expected to have saved a new entity for our " . $current_scenario['label'] . " scenario.");

    // Render the field directly to ensure the formatter's render array is
    // returned for inspection.
    $build = $entity->get('trait_method')->view();

    // Retrieve only the children of the render array that is produced by
    // the formatter.
    $formatter_children = Element::children($build);

    // First we want to confirm we have an item list with one element.
    $this->assertCount(1, $formatter_children, 'Formatter should produce one element containing the method and its associated units.');

    $method_build = $build[0];
    $this->assertSame('details', $method_build['#type'], "The method summary should be rendered as a details element.");
    $this->assertSame($current_scenario['expected']['method_name'], $method_build['#title'], "The method name was not rendered correctly.");
    $this->assertSame(
      $current_scenario['expected']['method_definition'],
      $method_build['definition']['#context']['definition'],
      "The method definition was not rendered correctly."
    );

    $this->assertArrayHasKey('units', $method_build, "The method summary should contain a units element.");

    $this->assertSame($current_scenario['expected']['unit_names'], $method_build['units']['#context']['units'], "The unit names were not rendered correctly.");
  }

}
