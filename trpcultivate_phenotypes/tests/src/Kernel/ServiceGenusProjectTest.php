<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Test Tripal Cultivate Phenotypes Genus Project service.
 *
 * @group trpcultivate_phenotypes
 */
class ServiceGenusProjectTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Term Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService
   */
  protected $service_PhenoGenusProject;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

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
   * Configuration terms.genus.
   *
   * @var string
   */
  private $sysvar_genus;

  /**
   * A set of configured and not configured genus as test genus input value.
   *
   * @var array
   */
  private array $genus = [];

  /**
   * A test project id number.
   *
   * The project id number is obtained after creating a project record.
   *
   * @var int
   */
  private int $project;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Set terms used to create relations.
    $terms = $this->setTermConfig();
    $this->sysvar_genus = $terms['genus'];

    // Create test genus.
    $ins_genus = [
      'configured' => [
        'Genus1',
        'Genus2',
        'Genus3',
        'Genus4',
        'Genus5',
      ],
      'not-configured' => [
        'Genus6',
      ],
    ];

    foreach ($ins_genus as $type => $genus_set) {
      foreach ($genus_set as $i => $genus) {
        $organism_id = $this->chado_connection->insert('1:organism')
          ->fields([
            'genus' => $genus,
            'species' => 'species:' . $i,
          ])
          ->execute();

        $this->assertIsNumeric($organism_id, 'Unable to insert genus: ' . $genus);

        $this->genus[$type][$organism_id] = $genus;

        if ($type == 'configured') {
          $this->setOntologyConfig($genus);
        }
      }
    }

    // Create test project.
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => 'This is a test project',
        'description' => 'A project description',
      ])
      ->execute();

    $this->assertIsNumeric($project_id, 'Unable to create project');
    $this->project = $project_id;

    $container = \Drupal::getContainer();
    $this->service_PhenoGenusProject = $container->get('trpcultivate_phenotypes.genus_project');
  }

  /**
   * Data Provider: provides genus and project to test genus project service.
   *
   * @return array
   *   Each genus-project test scenario is an array witht the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An integer indicating the number of genus to set to the project.
   *     paired with the project. Keyed by 'genus' and 'species'.
   *   - An array of expected values, with the following keys:
   *     - 'project_genus': the expected genus returned by the method
   *     getGenusOfProject().
   */
  public function provideGenusProjectForGenusProjectService() {
    return [
      // #0: A project with one configured genus.
      [
        'A project with a configured genus',
        1,
        [
          'project_genus' => ['Genus1'],
        ],
      ],

      // #1: A project with two configured genus.
      [
        'A project with two configured genus',
        2,
        [
          'project_genus' => [
            'Genus1',
            'Genus2',
          ],
        ],
      ],

      // #3: A project with 5 configured genus.
      [
        'A project with configured genus',
        5,
        [
          'project_genus' => [
            'Genus1',
            'Genus2',
            'Genus3',
            'Genus4',
            'Genus5',
          ],
        ],
      ],
    ];
  }

  /**
   * Test genus project service.
   *
   * @param string $scenario
   *   Human-readable text description of the test scenario.
   * @param int $genus_count
   *   An integer indicating the number of genus to set to the project.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'project_genus': the expected genus returned by the method
   *     getGenusOfProject().
   *
   * @dataProvider provideGenusProjectForGenusProjectService
   */
  public function testGenusProjectService($scenario, $genus_count, $expected) {

    $project_genus = [];
    for ($i = 0; $i < $genus_count; $i++) {
      $project_genus[] = 'Genus' . ($i + 1);
    }

    foreach ($project_genus as $genus) {
      $is_set = $this->service_PhenoGenusProject->setGenusToProject($this->project, $genus);
      $this->assertTrue($is_set, 'Project Genus Service failed to set a genus to project in scenario: ' . $scenario);
    }

    $set_genus = $this->service_PhenoGenusProject->getGenusOfProject($this->project);

    $this->assertEquals(
      count($set_genus),
      count($expected['project_genus']),
      'The number of genus set does no match expected genus count in scenario: ' . $scenario
    );

    $this->assertEquals(
      $set_genus,
      $expected['project_genus'],
      'The genus set does no match expected genus in scenario: ' . $scenario
    );

    // Test the rank assigned is the order of the genus as it appears in the
    // expected genus array (plus 1 - since zero-based index and rank starts 1).
    $project_genus = $this->chado_connection->select('1:projectprop', 'pp')
      ->fields('pp', ['value', 'rank'])
      ->condition('pp.project_id', $this->project, '=')
      ->condition('pp.type_id', $this->sysvar_genus, '=')
      ->orderBy('rank', 'ASC')
      ->execute()
      ->fetchAll();

    foreach ($project_genus as $row) {
      $key = array_search($row->value, $expected['project_genus']);

      $this->assertEquals(
        $key + 1,
        $row->rank,
        'The rank assigned to the genus does not match expected rank value in scenario: ' . $scenario
      );
    }
  }

}
