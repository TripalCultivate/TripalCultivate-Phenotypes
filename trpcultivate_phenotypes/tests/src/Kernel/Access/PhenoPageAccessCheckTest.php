<?php

namespace Drupal\trpcultivate_phenotyes\Kernel\Access;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Test associated with page access check.
 *
 * @group trpcultivate_phenotypes
 * @group page_access_check
 */
#[Group('trpcultivate_phenotypes')]
#[Group('page_access_check')]
#[RunTestsInSeparateProcesses]
class PhenoPageAccessCheckTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'path',
    'path_alias',
    'system',
    'user',
    'views',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Test Tripal entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  protected TripalEntity $exp_entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig([
      'tripal_chado',
      'trpcultivate_phenotypes',
      'trpcultivate',
    ]);
    $this->installEntitySchema('user');

    $this->container->get('trpcultivate.setup_module_service')
      ->installTerms();

    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    $this->container->get('trpcultivate.setup_module_service')
      ->importContenttypes();

    // Creaate research experiment entity.
    $project = 'Project Awesome';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $project])
      ->execute();

    $this->exp_entity = TripalEntity::create([
      'type' => 'research_experiment',
      'exp_name' => [
        'record_id' => $project_id,
        'name' => $project,
      ],
    ]);
  }

  /**
   * Test access() method.
   */
  public function testAccess() {

    $access = $this->container
      ->get('trpcultivate_phenotypes.pheno_experiment_configuration_access_check');

    // Unsupported integration in route requirements.
    $route = new Route(
      '/integration',
      [],
      [
        '_pheno_experiment_configuration_access_check' => TRUE,
        'integration' => 'spurious_integration',
      ]
    );

    $access_check = $access->access($this->exp_entity, $route);

    $this->assertFalse($access_check->isAllowed(), 'isAllowed() check is FALSE with failed access check.');
    $this->assertTrue(
      $access_check->isForbidden(),
      'Page access check with unsupported integration results in Forbidden access.',
    );

    // Test access with integration content types.
    $pheno_integration = $this->container->get('trpcultivate_phenotypes.pheno_integration');

    foreach ($pheno_integration::INTEGRATION_CONFIG_MAP as $integration => $_) {
      $pheno_integration
        ->setPhenoIntegratedContentTypes($integration, ['research_study']);

      $route = new Route(
        '/integration/backup',
        [],
        [
          '_pheno_experiment_configuration_access_check' => TRUE,
          'integration' => $integration,
        ]
      );

      $access_check = $access->access($this->exp_entity, $route);

      $this->assertFalse($access_check->isAllowed(), 'isAllowed() check is FALSE with failed access check.');
      $this->assertTrue(
        $access_check->isForbidden(),
        'Page access check with unsupported integration content type results in Forbidden access.',
      );

      // Allow the contetnt type in the integration.
      $pheno_integration
        ->setPhenoIntegratedContentTypes($integration, ['research_study', $this->exp_entity->bundle()]);

      $access_check = $access->access($this->exp_entity, $route);

      $this->assertTrue(
        $access_check->isAllowed(),
        'Page access check with supported integration content type results in Allowed access.',
      );
      $this->assertFalse($access_check->isForbidden(), 'isForbidden() check is FALSE with allowed access check.');
    }
  }

}
