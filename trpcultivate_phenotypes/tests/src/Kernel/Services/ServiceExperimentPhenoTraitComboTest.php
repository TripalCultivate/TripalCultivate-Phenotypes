<?php

namespace Drupal\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests associated with the Experiment pheno trait combo Service.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class ServiceExperimentPhenoTraitComboTest extends ChadoTestKernelBase {

  use UserCreationTrait;
  use TripalTestTrait;

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
   * A test plant trait combo.
   *
   * @var array
   */
  protected array $test_phenotrait_combo = [
    'Trait Name' => 'Plant Height',
    'Trait Description' => 'Total vertical height of the vegetative part of the plant',
    'Method Short Name' => 'PLANT_HEIGHT',
    'Collection Method' => 'Measure the height',
    'Unit' => 'meters',
    'Type' => 'Quantitative',
  ];

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
    $this->installSchema(
      'trpcultivate_phenotypes',
      [$this->container->get('trpcultivate_phenotypes.pheno_combo')::PHENO_COMBO_TABLE]
    );
    $this->installEntitySchema('user');

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $config_terms = $this->setTermConfig();

    $genus = 'Lens';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'databasica',
      ])
      ->execute();

    $this->container('trpcultivate_phenotypes.traits')
      ->setTraitGenus($genus);

    $this->service_traits->insertTrait($combo);
  }

  /**
   * Test setter method.
   */
  public function testSetExperiment() {
  }

}
