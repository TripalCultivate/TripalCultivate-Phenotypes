<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Controllers;

use Drupal\Core\Url;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\tripal\Entity\TripalEntity;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with PhenoExperiemtPhenoBackupController class.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
class PhenoExperimentPhenoBackupControllerTest extends ChadoTestKernelBase {

  use TripalCultivateImporterTestTrait;
  use PhenotypeImporterTestTrait;
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
    'file',
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
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $research_experiment_entity;

  /**
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_phenobackup';

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

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $this->setTermConfig();

    // Create Research Experiment Tripal content.
    $project_name = 'Test Project';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $project_name])
      ->execute();

    $entity = TripalEntity::create([
      'id' => 1,
      'type' => 'research_experiment',
      'label' => $project_name,
    ]);

    $entity
      ->set('exp_name', ['record_id' => $project_id, 'value' => $project_name])
      ->save();

    $this->research_experiment_entity = $entity;

    // Create admin user.
    $this->createUser([], NULL, FALSE, ['uid' => 1]);

    // Create a test file.
    $phenobackup_storage = $this->container->get('entity_type.manager')
      ->getStorage('phenodata_backup');

    foreach (['file-2', 'file-1'] as $i => $test_file) {
      $file_obj = $this->createTestFile([
        'filename' => $test_file . '.tsv',
        'mime' => 'text/tab-separated-values',
        'content' => [
          'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
        ],
      ]);

      $phenobackup_storage->create([
        'id' => uniqid(),
        'file_id' => $file_obj->id(),
        'project_id' => $project_id,
        'comments' => $i == 0 ? '' : 'This is a comment for backup ' . $i,
        'backup_date' => $i == 0 ? '2026-Jan-31 03:35:31' : '2026-Jan-1 03:35:31',
        'user_id' => 1,
      ])
        ->save();
    }
  }

  /**
   * Test loadBackups() method.
   */
  public function testLoadBackups() {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $this->setCurrentUser(
      $this->createUser([$route->getRequirements()['_permission']])
    );

    $page_url = Url::fromRoute(self::ROUTE_NAME, [
      'tripal_entity' => $this->research_experiment_entity->id(),
    ])
      ->toString();

    $request = Request::create($page_url);
    $page = $this->container->get('http_kernel')->handle($request)
      ->getContent();

    $this->setRawContent($page);
    $table = $this->cssSelect('table');

    // Test headers text and order of placement.
    $headers = $this->cssSelect('thead tr th', $table[0]);

    foreach (['Date Created', 'Notes/Comments', 'Created By', 'Data File'] as $i => $header) {
      $this->assertStringContainsString(
        $header,
        (string) $headers[$i]->asXML(),
        'The backup summary table does contain expected header - ' . $header,
      );
    }

    ['record_id' => $exp_id, 'value' => $exp_name] = $this->research_experiment_entity->get('exp_name')
      ->getValue()[0];

    // Test data row and order of items by date created (most recent first).
    $backups = $this->cssSelect('tbody tr', $table);

    // Test page title.
    $this->assertStringContainsString(
      'Phenotypes Backup for ' . $exp_name,
      (string) $page,
      'The page does not contain the expected page title containing the experiment name.'
    );

    $entity_manager = $this->container->get('entity_type.manager');

    $phenobackup_storage = $entity_manager->getStorage('phenodata_backup');

    $backup_ids = $phenobackup_storage
      ->getQuery()
      ->condition('project_id', $exp_id, '=')
      ->sort('backup_date', 'DESC')
      ->execute();

    $i = 0;
    foreach ($phenobackup_storage->loadMultiple($backup_ids) as $backup) {
      $this->assertStringContainsString(
        $backup->backup_date,
        (string) $backups[$i]->asXML(),
        'The order of backup item does not match expected order (most recent first).',
      );

      $this->assertStringContainsString(
        $backup->comments ?: 'No notes/comments placed on this file',
        (string) $backups[$i]->asXML(),
        'The file backup comments does not match expected comment.',
      );

      $created_by = $entity_manager->getStorage('user')
        ->load($backup->user_id)
        ->getAccountName();

      $this->assertStringContainsString(
        $created_by,
        (string) $backups[$i]->asXML(),
        'The user who created the backup file does match expected user.',
      );

      $file_obj = $entity_manager->getStorage('file')
        ->load($backup->file_id);

      $this->assertStringContainsString(
        $file_obj->createFileUrl(),
        (string) $backups[$i]->asXML(),
        'The backup file download path does not match expected file path.',
      );

      $i++;
    }
  }

}
