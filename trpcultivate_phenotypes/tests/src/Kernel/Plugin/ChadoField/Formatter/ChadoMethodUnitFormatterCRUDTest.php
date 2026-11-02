<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Plugin\ChadoField\Formatter;

use Drupal\Core\Render\Element;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\tripal_chado\Traits\ChadoFieldTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\Group;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    [$this->system_under_test, $scenarios] = $this->getTestInfoFromYaml($this->yaml_info_file);
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
    $this->assertIsNumeric($organism_id);

    $this->setTermConfig();
    $this->setupChadoEntityFieldTestEnvironment($this->system_under_test);
    $this->installSchema('tripal_chado', ['tripal_custom_tables', 'tripal_mviews']);
    $this->container->get('tripal_chado.terms_init')->installTerms();
    $this->container->get('trpcultivate_phenotypes.terms')->loadTerms($this->chado_connection->getSchemaName());
    $this->setOntologyConfig('Lens');

    $traits = $this->container->get('trpcultivate_phenotypes.traits');
    $traits->setTraitGenus('Lens');
    $trait_assets = $traits->insertTrait([
      'Trait Name' => 'Plant Height',
      'Trait Description' => 'A plant height trait is the vertical dimension of a plant service as a significant morphological characteristic',
      'Method Short Name' => 'PLHT',
      'Collection Method' => "Place a measuring tape, ruler, or meter stick at the soil surface or the base of the pot and extend it to the highest point of the plant's canopy or apical bud. Record the measurement, along with the date, to track growth over time.",
      'Unit' => 'cm',
      'Type' => 'Quantitative',
    ]);

    $this->scenarios = $scenarios;
    $this->scenarios[0]['user_input']['trait_method'][0]['cvterm_id'] = (int) $trait_assets['method'];
  }

  /**
   * The YAML scenarios for the formatter test.
   */
  protected array $scenarios = [];

  /**
   * Tests the method summary and its associated units are rendered.
   */
  public function testFormatterRendersMethodAndUnits(): void {
    $scenario = $this->scenarios[0];
    $entity = TripalEntity::create([
      'title' => $this->randomString(),
      'type' => $this->bundle_name,
    ] + $scenario['user_input']);
    $this->assertInstanceOf(TripalEntity::class, $entity);
    $this->assertEquals(SAVED_NEW, $entity->save());

    $entity = TripalEntity::load($entity->id());
    $build = $entity->get('trait_method')->view();
    $children = Element::children($build);

    $this->assertCount(1, $children);
    $method_build = $build[$children[0]];
    $this->assertSame('details', $method_build['#type']);
    $this->assertSame($scenario['expected']['method_name'], $method_build['#title']);
    $this->assertSame(
      $scenario['expected']['method_definition'],
      $method_build['definition']['#context']['definition']
    );
    $this->assertSame(
      $scenario['expected']['unit_names'],
      $method_build['units']['#context']['units']
    );
  }

}
