<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentConfigurationForm;

/**
 * Tests associated with PhenoExperimentConfigurationForm class.
 */
class PhenoExperimentConfigurationFormTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'markup',
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
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private $research_experiment_entity;

  /**
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_configuration';

  /**
   * Test genus and trait set.
   *
   * @var array
   */
  private $trait_set = [
    'Lens:culinaris' => [
      [
        'Trait Name' => 'Days to Flower',
        'Trait Description' => 'DTF trait description text',
        'Method Short Name' => 'DTF',
        'Collection Method' => 'DTF trait collection method text',
        'Unit' => 'days',
        'Type' => 'Quantitative',
      ],
      [
        'Trait Name' => 'Plant Height',
        'Trait Description' => 'PH trait description text',
        'Method Short Name' => 'PHT',
        'Collection Method' => 'PH trait collection method text',
        'Unit' => 'cm',
        'Type' => 'Quantitative',
      ],
      [
        'Trait Name' => 'Is Dead',
        'Trait Description' => 'ID trait description text',
        'Method Short Name' => 'IS_D',
        'Collection Method' => 'ID trait collection method text',
        'Unit' => 'text',
        'Type' => 'Qualitative',
      ],
    ],
    'Triticum:durum' => [
      [
        'Trait Name' => 'Green Cotyledon Colour',
        'Trait Description' => 'GCC trait description text',
        'Method Short Name' => 'GCC',
        'Collection Method' => 'GCC trait collection method text',
        'Unit' => 'colour',
        'Type' => 'Qualitative',
      ],
    ],
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
    $this->installSchema('trpcultivate_phenotypes', ['trpcultivate_phenocombo']);

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $this->setTermConfig();

    // Create experiment (project).
    $experiment_name = 'My Research Experiment';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $experiment_name])
      ->execute();

    // Create tripal entity.
    $this->research_experiment_entity = TripalEntity::create([
      'id' => uniqid(),
      'type' => 'research_experiment',
      'label' => $experiment_name,
      'title' => $experiment_name,
    ]);

    $this->research_experiment_entity
      ->set('exp_name', ['record_id' => $project_id, 'value' => $experiment_name])
      ->save();

    // Configure the genus.
    $genus_key = [];
    foreach ($this->trait_set as $organism => $traits) {
      [$genus, $species] = explode(':', $organism);
      $genus_key[$organism] = $genus;

      $this->chado_connection->insert('1:organism')
        ->fields(['genus', 'species', 'type_id'])
        ->values([
          'genus' => $genus,
          'species' => $species,
          'type_id' => 1,
        ])
        ->execute();

      $this->setOntologyConfig($genus);
    }

    // Insert trait-method-unit then add combo to an experiment.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $genus_project_service = $this->container->get('trpcultivate_phenotypes.genus_project');
    $database_service = $this->container->get('database');

    foreach ($genus_key as $organism => $genus) {
      $trait_service->setTraitGenus($genus);

      foreach ($this->trait_set[$organism] as $trait) {
        $ids = $trait_service->insertTrait($trait);

        $database_service->insert('trpcultivate_phenocombo')
          ->fields([
            'project_id',
            'attr_id',
            'observable_id',
            'unit_id',
            'label',
            'is_archived',
            'is_required',
            'was_collected',
            'was_shared',
            'uid',
            'timestamp',
          ])
          ->values([
            $project_id,
            $ids['trait'],
            $ids['method'],
            $ids['unit'],
            $trait['Trait Name'] . ':' . $trait['Method Short Name'],
            mt_rand(0, 1),
            mt_rand(0, 1),
            mt_rand(0, 1),
            mt_rand(0, 1),
            $this->container->get('current_user')->id(),
            time(),
          ])
          ->execute();
      }

      $genus_project_service->setGenusToProject($project_id, $genus);
    }
  }

  /**
   * Test getFormId().
   */
  public function testGetFormId() {

    $this->assertEquals(
      'content_bio_data_research_experiment_configure_form',
      PhenoExperimentConfigurationForm::create($this->container)->getFormId(),
      'The form id returned does not match expected form id of experiment configuration form'
    );
  }

}
