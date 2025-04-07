<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Entity;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with the Phenodata Backup listing.
 *
 * @group trpcultivate_phenotypes
 */
class PhenodataBackupTest extends ChadoTestKernelBase {

  use TripalCultivateImporterTestTrait;

  /**
   * HTTP route request.
   *
   * @var Symphony\Component\HttpFoundation\Request
   */
  protected $http_request;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'system',
    'user',
    'file',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Open connection to Chado.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');

    $container = \Drupal::getContainer();
    $entity_type_manager = $container->get('entity_type.manager');
    $this->http_request = $container->get('http_kernel');

    // Create a test file.
    $file_obj = $this->createTestFile([
      'filename' => 'backup_data_file.tsv',
      'mime' => 'text/tab-separated-values',
      'content' => [
        'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
      ],
    ]);

    $file_id = $file_obj->id();

    // Create a 2 projects.
    $project_a_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => 'Project A'])
      ->execute();

    $project_b_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => 'Project B'])
      ->execute();

    // Create a user.
    $user = User::create([
      'name' => 'a_user',
      'roles' => ['administrator'],
    ]);

    $user->save();

    \Drupal::currentUser()
      ->setAccount($user);

    // Create config entity list entries for both projects.
    $config_entities = [
      [
        uniqid(),
        $file_id,
        $project_a_id,
        'This is a comment',
        date('Y-M-d H:i:s'),
        $user->id(),
      ],
      [
        uniqid(),
        $file_id,
        $project_b_id,
        'This is another comment',
        date('Y-M-d H:i:s'),
        $user->id(),
      ],
    ];

    $entity_storage = $entity_type_manager->getStorage('phenodata_backup');

    foreach ($config_entities as $entity) {
      $entity_storage->create($entity);
    }
  }

  /**
   * Test Phenodata Backup list.
   */
  public function testPhenodataBackupList() {

    $request = REQUEST::create('/admin/structure/phenodata-backup');
    $response = $this->http_request->handle($request);

    $this->assertEquals(
      200,
      $response->getStatusCode(),
      'The Phenodata Backup listing Http request status code does not match expected of 200'
    );
  }

}
