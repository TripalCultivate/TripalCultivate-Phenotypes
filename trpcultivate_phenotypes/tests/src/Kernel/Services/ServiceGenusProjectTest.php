<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal\Services\TripalLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test Tripal Cultivate Phenotypes Genus Project service.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
class ServiceGenusProjectTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * A genus that is not configured.
   *
   * @var string
   */
  private const UNCONFIGURED_GENUS = 'UnconfiguredGenus';

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
   * A set of configured genus as test genus input value.
   *
   * @var array
   */
  private array $genus = [];

  /**
   * A test project id number.
   *
   * The project id number obtained after creating a project record.
   *
   * @var int
   */
  private int $project;

  /**
   * Tripal Logger log message.
   *
   * @var string
   */
  private string $log_message;

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

    // Create test genus - Genus1, Genus2, Genus3, Genus4 and Genus5.
    for ($i = 1; $i < 6; $i++) {
      $genus = 'Genus' . $i;
      $organism_id = $this->chado_connection->insert('1:organism')
        ->fields([
          'genus' => $genus,
          'species' => 'species:' . $i,
        ])
        ->execute();

      $this->assertIsNumeric($organism_id, 'Unable to insert genus: ' . $genus);

      $this->genus[] = $genus;
      $this->setOntologyConfig($genus);
    }

    // This is not a configured genus.
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => self::UNCONFIGURED_GENUS,
        'species' => 'unconfigured species',
      ])
      ->execute();

    // Create test project.
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => 'This is a test project',
        'description' => 'A project description',
      ])
      ->execute();

    $this->assertIsNumeric($project_id, 'Unable to create project');
    $this->project = $project_id;

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
    $this->service_PhenoGenusProject = $container->get('trpcultivate_phenotypes.genus_project');
  }

  /**
   * Data Provider: provides genus and project to test genus project service.
   *
   * @return array
   *   Each genus-project test scenario is an array witht the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An integer indicating the number of genus to set to the project.
   *   - An array of expected values, with the following keys:
   *     - 'project_genus': the expected genus returned by the method
   *     getGenusOfProject().
   */
  public static function provideGenusProjectForGenusProjectService() {
    return [
      // #0: A project with one genus.
      [
        'A project with a genus',
        1,
        [
          'project_genus' => ['Genus1'],
        ],
      ],

      // #1: A project with two genus.
      [
        'A project with two genus',
        2,
        [
          'project_genus' => [
            'Genus1',
            'Genus2',
          ],
        ],
      ],

      // #2: A project with 5 genus.
      [
        'A project with five genus',
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
  #[DataProvider('provideGenusProjectForGenusProjectService')]
  public function testGenusProjectService($scenario, $genus_count, $expected) {

    $rand_i = mt_rand(0, $genus_count - 1);
    $re_set_genus = '';

    for ($i = 0; $i < $genus_count; $i++) {
      $genus = 'Genus' . ($i + 1);

      $is_set = $this->service_PhenoGenusProject->setGenusToProject($this->project, $genus);
      $this->assertTrue($is_set, 'Project Genus Service failed to set a genus to project in scenario: ' . $scenario);

      if ($i == $rand_i) {
        $re_set_genus = $genus;
      }
    }

    // A randomly selected genus in the scenario is re-set to test that it will
    // not alter the expected list of genus.
    $is_set = $this->service_PhenoGenusProject->setGenusToProject($this->project, $re_set_genus);
    $this->assertTrue(
      $is_set,
      'setGenusToProject() method failed to return the expected value TRUE when re-setting a genus in scenario: ' . $scenario
    );

    $set_genus = $this->service_PhenoGenusProject->getGenusOfProject($this->project);

    $this->assertCount(
      $genus_count,
      $set_genus,
      'The number of genus set does no match expected genus count in scenario: ' . $scenario
    );

    $this->assertEquals(
      $set_genus,
      $expected['project_genus'],
      'The genus set does no match expected genus in scenario: ' . $scenario
    );

    // Test the rank assigned is the order of each genus as it appears in the
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

  /**
   * Data Provider: provides invalid genus and project as test input values.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - An array keyed by project and genus to represent project and genus
   *     input values, respectively.
   *   - An array of expected values, with the following keys:
   *     - 'is_set': a boolean value returned by setGenusToProject() method to
   *       indicate the success or failure of the set genus to project request.
   *     - 'log_message': the Tripal log error message about the failed value.
   */
  public static function provideInvalidValuesToGenusProjectService() {
    return [
      // #0: An empty string value as project input value.
      [
        'Empty string as project',
        [
          'project' => '',
          'genus' => 'Genus1',
        ],
        [
          'is_set' => FALSE,
          'log_message' => 'Error, Project id is empty string, 0 or not a positive number. Could not replace genus.',
        ],
      ],

      // #1: Project ID is the value 0.
      [
        'Project ID is 0',
        [
          'project' => 0,
          'genus' => 'Genus1',
        ],
        [
          'is_set' => FALSE,
          'log_message' => 'Error, Project id is empty string, 0 or not a positive number. Could not replace genus.',
        ],
      ],

      // #2: Genus is empty string value.
      [
        'Empty string as genus',
        [
          'project' => 1,
          'genus' => '',
        ],
        [
          'is_set' => FALSE,
          'log_message' => 'Error, Genus is an empty string. Could not replace genus.',
        ],
      ],

      // #3: Genus is not configured.
      [
        'Empty string as genus',
        [
          'project' => 1,
          'genus' => self::UNCONFIGURED_GENUS,
        ],
        [
          'is_set' => FALSE,
          'log_message' => 'Error, Genus is not configured. Could not replace genus.',
        ],
      ],
    ];
  }

  /**
   * Test setGenusToProject() method with invalid values.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param array $input_value
   *   An array keyed by project and genus to represent project and genus
   *   input values, respectively.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'is_set': a boolean value returned by setGenusToProject() method to
   *       indicate the success or failure of the set genus to project request.
   *     - 'log_message': the Tripal log error message about the failed value.
   *
   * @dataProvider provideInvalidValuesToGenusProjectService
   */
  #[DataProvider('provideInvalidValuesToGenusProjectService')]
  public function testGenusProjectServiceWithInvalidValues($scenario, $input_value, $expected) {

    $has_genus = $this->chado_connection->select('1:projectprop', 'pp')
      ->fields('pp', ['projectprop_id'])
      ->condition('pp.type_id', $this->sysvar_genus, '=')
      ->condition('pp.value', $input_value['genus'], '=')
      ->execute()
      ->fetchCol();

    $this->assertEmpty(
      $has_genus,
      'Could not test the genus input value with existing project-genus properties entry in scenario: ' . $scenario
    );

    $is_set = $this->service_PhenoGenusProject->setGenusToProject($input_value['project'], $input_value['genus']);

    $this->assertEquals(
      $is_set,
      $expected['is_set'],
      'The setGenusToProject() method is expected to return false when project or genus is an invalid value in scenario: ' . $scenario
    );

    $this->assertEquals(
      $expected['log_message'],
      $this->log_message,
      'The log messaged returned by setGenusToProject() with invalid value does not match expected log message in scenario: ' . $scenario
    );
  }

  /**
   * Test getGenusOfProject() method.
   */
  public function testGetGenusOfProject() {

    // Unconfigured genus.
    foreach ($this->genus as $configured_genus) {
      $this->service_PhenoGenusProject->setGenusToProject($this->project, $configured_genus);
    }

    $genus_project_ins = $this->chado_connection->insert('1:projectprop')
      ->fields([
        'project_id' => $this->project,
        'type_id' => $this->sysvar_genus,
        'value' => self::UNCONFIGURED_GENUS,
        'rank' => 10,
      ])
      ->execute();

    $this->assertIsNumeric($genus_project_ins, 'Unable to create genus-project (unconfigured genus) entry.');

    $project_genus = $this->service_PhenoGenusProject->getGenusOfProject($this->project);

    $this->assertNotContains(
      self::UNCONFIGURED_GENUS,
      $project_genus,
      'Unconfigured genus set to a project is not returned by the getGenusOfProject() method.'
    );

    // Invalid project.
    foreach (['', 0, 9999] as $project) {
      $project_genus = $this->service_PhenoGenusProject->getGenusOfProject($project);

      $this->assertEmpty(
        $project_genus,
        'The return value of getGenusOfProject() method does not match expected value of empty array when project is invalid.'
      );
    }

    // Not set with genus.
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => 'This is another test project',
        'description' => 'A project description',
      ])
      ->execute();

    $project_genus = $this->service_PhenoGenusProject->getGenusOfProject($project_id);

    $this->assertEmpty(
      $project_genus,
      'The return value of getGenusOfProject() method does not match expected value of empty array when project has no set genus.'
    );
  }

}
