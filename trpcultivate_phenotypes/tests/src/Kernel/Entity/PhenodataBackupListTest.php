<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Entity;

use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests associated with the Phenodata Backup listing.
 *
 * @group trpcultivate_phenotypes
 */
class PhenodataBackupListTest extends ChadoTestKernelBase {

  use TripalCultivateImporterTestTrait;
  use UserCreationTrait;

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Config entity input values.
   *
   * @var array
   */
  private $entity_input_values;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Users for testing phenodata backup permissions.
   *
   * When testing permissions we want to create a user with each of the
   * permissions defined by this functionality. These will be created in the
   * setup and referenced in the data provider and tests.
   *
   * @var array
   *   Each element of this array is keyed by a unique identifier and defines
   *   the parameters to pass when creating the user. All parameters supported
   *   by UserCreationTrait::createUser() can be provided.
   */
  protected array $users = [
    // 1. Can't view any backups, not even the listing.
    'other' => [
      'name' => 'Unauthorized',
      'permissions' => [],
    ],
    // 2. Can only view their own backups.
    'view_own_0' => [
      'name' => 'Researcher with no backups',
      'permissions' => ['view_own phenodata_backup'],
    ],
    'view_own_3' => [
      'name' => 'Researcher with 3 backups',
      'permissions' => ['view_own phenodata_backup'],
    ],
    // 3. Can view all backups.
    'view_all' => [
      'name' => 'PI',
      'permissions' => ['view_all phenodata_backup'],
    ],
    // 4. Can administer backups (and thus also see them).
    'admin' => [
      'name' => 'Administrator',
      'permissions' => ['administer phenodata_backup'],
    ],
  ];

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
    $this->entityTypeManager = $container->get('entity_type.manager');

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

    // Create users.
    // Create first UID1 so, the other users are not super-admin.
    $this->createUser([], NULL, FALSE, ['uid' => 1,]);
    // Now create the rest of the users.
    foreach ($this->users as $user_key => $user_defn) {
      $this->users[$user_key]['object'] = $this->createUser(
        $user_defn['permissions'],
        $user_defn['name']
      );
      $this->assertNotFalse($this->users[$user_key]['object'], "We were unable to create a user for testing key:$user_key, params:" . print_r($user_defn, TRUE));

      $this->users[$user_key]['object']->save();
      $this->users[$user_key]['id'] = $this->users[$user_key]['object']->id();
    }

    // Create backups for testing.
    $entity_storage = $this->entityTypeManager->getStorage('phenodata_backup');

    // 3 backups for view_own_3.
    for ($i=0; $i < 3; $i++) {
      $values = [
        'id' => uniqid(),
        'file_id' => $file_id,
        'project_id' => $project_a_id,
        'comments' => 'This is a comment for $i',
        'backup_date' => date('Y-M-d H:i:s'),
        'user_id' => $this->users['view_own_3']['id'],
      ];
      // If this is the second one then use a different project.
      if ($i === 2) {
        $values['project_id'] = $project_b_id;
      }
      // Now save it.
      $entity_storage->create($values)
        ->save();
    }

    // 1 for view_all.
    $values = [
      'id' => uniqid(),
      'file_id' => $file_id,
      'project_id' => $project_a_id,
      'comments' => 'This is a comment for $i',
      'backup_date' => date('Y-M-d H:i:s'),
      'user_id' => $this->users['view_all']['id'],
    ];
    $entity_storage->create($values)
      ->save();

    // 1 for admin.
    $values = [
      'id' => uniqid(),
      'file_id' => $file_id,
      'project_id' => $project_a_id,
      'comments' => 'This is a comment for $i',
      'backup_date' => date('Y-M-d H:i:s'),
      'user_id' => $this->users['admin']['id'],
    ];
    $entity_storage->create($values)
      ->save();

  }

  /**
   * Data Provider: Provides a variety of users/backups to test.
   *
   * @return array
   *
   */
  public static function providePhenodataBackupScenarios() {
    $scenarios = [];

    // For all scenarios we want to test with 3 kinds of users.
    // Users are defined in a property at the top of this class and their
    // unique keys are used in the scenarios.
    // @see $users property.

    // Headers expected for users that can only see their own backups.
    $user_specific_headers = ['project_id', 'comments', 'backup_date', 'file_id'];

    // Headers expected for users that can see all backups.
    $admin_headers = ['project_id', 'comments', 'backup_date', 'file_id', 'user_id'];

    // User who does not have permission to see backups at all.
    $scenarios[] = [
      'other',
      [
        'auth_level' => 0,
        'headers' => [],
        'num_backups' => 0,
        'has_project_a' => 0,
      ],
    ];

    // User who can only view their own and has 3 backups.
    $scenarios[] = [
      'view_own_3',
      [
        'auth_level' => 1,
        'headers' => $user_specific_headers,
        'num_backups' => 3,
        'has_project_a' => 2,
      ],
    ];

    // User who can only view their own but does not have any backups.
    $scenarios[] = [
      'view_own_0',
      [
        'auth_level' => 1,
        'headers' => $user_specific_headers,
        'num_backups' => 0,
        'has_project_a' => 0,
      ],
    ];

    // User who can view all backups and has 1 of their own.
    $scenarios[] = [
      'view_all',
      [
        'auth_level' => 2,
        'headers' => $admin_headers,
        'num_backups' => 5,
        'has_project_a' => 4,
      ],
    ];

    // User who can administer backups and has 1 of their own.
    $scenarios[] = [
      'admin',
      [
        'auth_level' => 2,
        'headers' => $admin_headers,
        'num_backups' => 5,
        'has_project_a' => 4,
      ],
    ];

    return $scenarios;
  }

  /**
   * Test Phenodata Backup list by HTTP request.
   *
   * @dataProvider providePhenodataBackupScenarios
   */
  public function testPhenodataBackupListRequest(string $current_user, array $expected) {

    // Login the current user.
    $current_user = $this->users[$current_user]['object'];
    $this->setCurrentUser($current_user);

    // Request the page.
    $request = REQUEST::create('/admin/structure/phenodata-backup');
    $response = $this->container->get('http_kernel')->handle($request);

    $expected_code = 200;
    $code_label = "200 (ok)";
    if ($expected['auth_level'] === 0) {
      $expected_code = 403;
      $code_label = "403 (unauthorized)";
    }

    $this->assertEquals(
      $expected_code,
      $response->getStatusCode(),
      "The Phenodata Backup listing Http request status code does not match expected of $code_label."
    );

    $config_entity_list_markup = (string) $response->getContent();

    // Check for a backup row.
    if (($expected['auth_level'] > 0) AND ($expected['num_backups'] > 0)) {
      $this->assertStringContainsString(
        'Project A',
        $config_entity_list_markup,
        'The project name was not found in the page listing.'
      );

      $this->assertStringContainsString(
        'This is a comment',
        $config_entity_list_markup,
        'The comments string was not found in the page listing.'
      );

      $this->assertStringContainsString(
        'Download',
        $config_entity_list_markup,
        'The comments string was not found in the page listing.'
      );
    }
  }

  /**
   * Tests PhenodataBackupListBuilder::load().
   *
   * @dataProvider providePhenodataBackupScenarios
   */
  public function testPhenodataBackupListLoad(string $current_user, array $expected) {

    // Login the current user.
    $current_user = $this->users[$current_user]['object'];
    $this->setCurrentUser($current_user);

    $listbuilder = $this->entityTypeManager->getListBuilder('phenodata_backup');

    // Test the load() will properly accept a valid project_id
    // -- set the request query params project_id used in the load().
    $request = REQUEST::create(
      '/admin/structure/phenodata-backup',
      'GET',
      ['project_id' => 1]
    );
    $this->container->get('http_kernel')->handle($request);
    // -- now call the load the backups.
    $backups = $listbuilder->load();

    // Ensure we got the number of backups we expected.
    $this->assertCount($expected['has_project_a'], $backups, "We did not get the number of backups we expected when filtering for project A.");

    // Test the load() will give them all when no project is specified
    $request = REQUEST::create(
      '/admin/structure/phenodata-backup'
    );
    $this->container->get('http_kernel')->handle($request);
    $backups = $listbuilder->load();

    $this->assertCount($expected['num_backups'], $backups, "We did not get the number of backups we expected when not filtering at all.");

    // Test the load() will give them all when project is specified incorrectly.
    $request = REQUEST::create(
      '/admin/structure/phenodata-backup',
      'GET',
      ['project_id' => 'project1'],
    );
    $this->container->get('http_kernel')->handle($request);
    $backups = $listbuilder->load();

    $this->assertCount($expected['num_backups'], $backups, "We did not get the number of backups we expected when project was provided incorrectly.");
  }
}
