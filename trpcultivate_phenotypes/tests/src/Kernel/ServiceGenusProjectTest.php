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
   * Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * A genus used as an alternative genus to the genus a project has been set.
   *
   * @var string
   */
  private $alt_genus = 'AlternativeGenus';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Fetch module settings.
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Set terms used to create relations.
    $this->setTermConfig();

    // Create and configure a genus to be used to switch a project genus to
    // using this genus.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $this->alt_genus,
        'species' => 'some species',
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'Unable to create alternative genus');

    // Configure the genus.
    $this->setOntologyConfig($this->alt_genus);
  }

  /**
   * Data Provider: provides genus and project to test genus project service.
   *
   * @return array
   *   Each genus-project test scenario is an array witht the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - A string, the name or title of a project.
   *   - An array, the genus and species of the organism that will be
   *     paired with the project. Keyed by 'genus' and 'species'.
   *   - An array of expected values, with the following keys:
   *     - 'project_genus': the expected genus returned by the method
   *     getGenusOfProject().
   *     - 'alternative_genus': a genus returned after setting a genus.
   */
  public function provideGenusProjectForGenusProjectService() {
    return [
      // #0: A project with configured genus.
      [
        'A project with configured genus',
        'Project - Plant Breeding',
        [
          'genus' => 'a genus',
          'species' => 'a species',
        ],
        [
          'project_genus' => 'a genus',
          'alternative_genus' => 'AlternativeGenus',
        ],
      ],
    ];
  }

  /**
   * Test genus project service.
   *
   * @param string $scenario
   *   Human-readable text description of the test scenario.
   * @param string $project_name
   *   A string, the name or title of a project.
   * @param array $project_genus
   *   An array, the genus and species of the organism that will be
   *   paired with the project. Keyed by 'genus' and 'species'.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'project_genus': the expected genus returned by the method
   *     getGenusOfProject().
   *     - 'alternative_genus': a genus returned after setting a genus.
   *
   * @dataProvider provideGenusProjectForGenusProjectService
   */
  public function testGenusProjectService($scenario, $project_name, $project_genus, $expected) {
    // Create the project record.
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => $project_name,
        'description' => 'A project description',
      ])
      ->execute();

    $this->assertIsNumeric($project_id, 'Unable to create project in scenario ' . $scenario);

    // Create the genus record.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $project_genus['genus'],
        'species' => $project_genus['species'],
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'Unable to create project genus in scenario ' . $scenario);

    // Configure the genus.
    $this->setOntologyConfig($project_genus['genus']);

    // Genus Project Service.
    $this->service_PhenoGenusProject = \Drupal::service('trpcultivate_phenotypes.genus_project');
    $this->assertNotNull($this->service_PhenoGenusProject, 'Failed to instantiate Genus Project Service in scenario ' . $scenario);

    // Test setGenusToProject().
    $is_set = $this->service_PhenoGenusProject->setGenusToProject($project_id, $project_genus['genus'], TRUE);
    $this->assertTrue($is_set, 'Project Genus Service failed to set a genus to project in scenario ' . $scenario);

    // Keep the same project genus by setting the replace flag to FALSE.
    $is_set = $this->service_PhenoGenusProject->setGenusToProject($project_id, $project_genus['genus'], FALSE);
    $this->assertTrue($is_set, 'Project Genus Service failed to set a genus to project in scenario ' . $scenario);

    // Test getGenusOfProject().
    $genus_of_project = $this->service_PhenoGenusProject->getGenusOfProject($project_id);
    $this->assertEquals(
      $expected['project_genus'],
      $genus_of_project['genus'],
      'The genus of project does not match expected genus in scenario ' . $scenario
    );

    // Replace the genus with the alternative genus.
    $is_alt_genus_set = $this->service_PhenoGenusProject->setGenusToProject($project_id, $this->alt_genus, TRUE);
    $this->assertTrue($is_alt_genus_set, 'Project Genus Service failed to set alternate genus to project in scenario ' . $scenario);

    $new_genus_of_project = $this->service_PhenoGenusProject->getGenusOfProject($project_id);
    $this->assertEquals(
      $expected['alternative_genus'],
      $new_genus_of_project['genus'],
      'The genus of project does not match expected alternative genus in scenario ' . $scenario
    );
  }

}
