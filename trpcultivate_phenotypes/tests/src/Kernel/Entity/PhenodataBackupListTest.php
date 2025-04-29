<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Entity;

use Drupal\Core\Form\FormState;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with the Phenodata Backup listing.
 *
 * @group trpcultivate_phenotypes
 */
class PhenodataBackupListTest extends ChadoTestKernelBase {

  use TripalCultivateImporterTestTrait;
  use UserCreationTrait;

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
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Users for testing phenodata backup permissions.
   *
   * When testing permissions we want to create a user with each of the
   * permissions defined by Phenodata Backups. These users will be created in
   * the setup and referenced in the data provider and tests.
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
   *   Each element is a scenario to be tested and consists of the following:
   *   - current user (string): a key from the users property of this class.
   *   - expectations (array): indicates the expecations for the current
   *     - auth_level (int): One of 0 (no access), 1 (only their own), or 2 (all).
   *     - headers (array): the headers of the list to expect for this user.
   *     - num_backups (int): the number of backups to expect in the listing.
   *     - has_project_a (int): the number of backups including 'Project A'
   *       that should be present in the listing.
   */
  public static function providePhenodataBackupScenarios() {
    $scenarios = [];

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
   *
   * Checks:
   *  - The user from the scenario has access to the page if they should or
   *    recieve an unautorized http code if they are not.
   *  - Basic check that the page has a backup listed if we expect one.
   *  - @todo we could check that the page content includes the Access Denied
   *   message if 403 is thrown and that there is no listing table on this page.
   *
   * NOTE: more in-depth checks on the page content occur in
   * testPhenodataBackupListForm(). This test focuses on simulating a page
   * request to ensure permissions.
   *
   * @param string $current_user
   *   A key from the users property of this class to indicate the user to login.
   * @param array $expected
   *   An array indicating the expectations for the current scenario.
   *   - auth_level (int): One of 0 (no access), 1 (only their own), or 2 (all).
   *   - headers (array): the headers of the list to expect for this user.
   *   - num_backups (int): the number of backups to expect in the listing.
   *   - has_project_a (int): the number of backups including 'Project A'
   *     that should be present in the listing.
   */
  public function testPhenodataBackupListRequest(string $current_user, array $expected) {

    // Login the current user.
    $current_user = $this->users[$current_user]['object'];
    $this->setCurrentUser($current_user);

    // Request the page.
    $request = REQUEST::create('/admin/structure/phenodata-backup');
    $response = $this->container->get('http_kernel')->handle($request);

    // Transform our authorization level into the expected HTTP code for
    // the request. Also define a matching code label to be used in the
    // assert message.
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

    // Retrieve the rendered content of the requested page.
    $config_entity_list_markup = (string) $response->getContent();

    // Check for a backup row as long as the requested page is expected to be
    // authorized and we are expecting backups.
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
   *
   * NOTE: This test checks both the load() function and the render() method.
   * The render() method returns a render array which is better for testing
   * then string matching the request. This complements the access tests in
   * testPhenodataBackupListRequest().
   *
   * Checks:
   * - The load only returns backups associated with a specific project when
   *   one is indicated.
   * - The load returns all backups when no project is specified.
   * - The load returns all backups when the project is incorrectly specified.
   * - In all the above cases, only projects the specific user has access to
   *   are returned. This is defined in the data provider.
   * - @todo The backups returned by load() are represented in the render array
   *   for the page.
   *
   * @param string $current_user
   *   A key from the users property of this class to indicate the user to login.
   * @param array $expected
   *   An array indicating the expectations for the current scenario.
   *   - auth_level (int): One of 0 (no access), 1 (only their own), or 2 (all).
   *   - headers (array): the headers of the list to expect for this user.
   *   - num_backups (int): the number of backups to expect in the listing.
   *   - has_project_a (int): the number of backups including 'Project A'
   *     that should be present in the listing.
   */
  public function testPhenodataBackupListLoad(string $current_user, array $expected) {

    // Login the current user.
    $current_user = $this->users[$current_user]['object'];
    $this->setCurrentUser($current_user);

    // Get the Listbuilder object through the entity type manager to ensure
    // it is populated correctly.
    $listbuilder = $this->entityTypeManager->getListBuilder('phenodata_backup');

    // Test the load() will properly accept a valid project_id
    // -- set the request query params project_id used in the load().
    //  This is needed because the load grabs it's parameters from the request
    //  directly making this the only way to ensure the parameters are used.
    $request = REQUEST::create(
      '/admin/structure/phenodata-backup',
      'GET',
      ['project_id' => 1]
    );
    $this->container->get('http_kernel')->handle($request);
    // -- now call the load the backups. We prefer this approach rather then
    //  just using the request directly since it provides the backup entities
    //  directly making testing for their presence more reliable then string
    //  matching the rendered output.
    $backups = $listbuilder->load();

    // Ensure we got the number of backups we expected.
    $this->assertCount($expected['has_project_a'], $backups, "We did not get the number of backups we expected when filtering for project A.");

    // Test the load() will give them all when no project is specified.
    // -- reset the request query params to none.
    //  As above, this is needed because the load grabs it's parameters from
    //  the request directly.
    $request = REQUEST::create(
      '/admin/structure/phenodata-backup'
    );
    $this->container->get('http_kernel')->handle($request);
    // -- now call the load the backups.
    $backups = $listbuilder->load();

    // Ensure we got the number of backups we expected.
    $this->assertCount($expected['num_backups'], $backups, "We did not get the number of backups we expected when not filtering at all.");

    // Test the load() will give them all when project is specified incorrectly.
    $request = REQUEST::create(
      '/admin/structure/phenodata-backup',
      'GET',
      ['project_id' => 'project1'],
    );
    $this->container->get('http_kernel')->handle($request);
    $backups = $listbuilder->load();

    // Ensure we got the number of backups we expected. We expect the same
    // number as if no parameters were set.
    $this->assertCount($expected['num_backups'], $backups, "We did not get the number of backups we expected when project was provided incorrectly.");
  }

  /**
   * Tests PhenodataBackupListBuilder::build/vaildate/submitForm().
   *
   * Specifically, this tests both filter criteria form only and only for
   * a single user since access permissions were tested elsewhere.
   */
  public function testPhenodataBackupListForm() {

    // We don't use the data provider but instead focus on a user with all
    // permissions here since the access permissions were checked in
    // testPhenodataBackupListRequest().
    $current_user = 'view_all';

    // Login the current user.
    $current_user = $this->users[$current_user]['object'];
    $this->setCurrentUser($current_user);

    // Get the Listbuilder object through the entity type manager to ensure
    // it is populated correctly.
    $listbuilder = $this->entityTypeManager->getListBuilder('phenodata_backup');

    // Basic test that we can build the listbuilder filter form.
    $form_state = new FormState();
    $form = $listbuilder->buildForm([], $form_state);
    $this->assertIsArray($form,
      "We were not able to build the listbuilder filter form.");

    // Now submit the form without setting any filters.
    // -- validate the form first.
    $listbuilder->validateForm($form, $form_state);
    // -- retrieve form state errors and confirm there were not any.
    $errors = $form_state->getErrors();
    $this->assertCount(0, $errors, "We got errors when we submitting the form without any values.");
    // -- submit the form.
    $listbuilder->submitForm($form, $form_state);
    // -- check the redirect was set properly: returns to current page.
    // Note: the submit only sets the redirect to ensure any filter parameters
    // are in the URL query. The listbuilder load then uses the URL query
    // direclty which is tested in testPhenodataBackupListLoad().
    $redirect_url = $form_state->getRedirect();
    $this->assertInstanceOf(\Drupal\Core\Url::class, $redirect_url, "FormState::getRedirect did not return the type of object we expected.");
    $this->assertEquals('<current>', $redirect_url->getRouteName(), "The redirect route was not what we expected.");
    $this->assertEmpty($redirect_url->getOptions(), "The redirect url should not have any parameters when no fitler criteria were set.");

    // Next submit with a valid project_id.
    // -- always call validate first.
    $form_state->setValue('project_id', '1');
    $listbuilder->validateForm($form, $form_state);
    // -- check that there are no errors.
    $errors = $form_state->getErrors();
    $this->assertCount(0, $errors, "We got errors when we submitting the form with a valid project_id.");
    // -- submit the form.
    $listbuilder->submitForm($form, $form_state);
    // -- check that the URL redirect now includes the project ID.
    $redirect_url = $form_state->getRedirect();
    $this->assertInstanceOf(\Drupal\Core\Url::class, $redirect_url, "FormState::getRedirect did not return the type of object we expected.");
    $this->assertEquals('<current>', $redirect_url->getRouteName(), "The redirect route was not what we expected.");
    $query_params = $redirect_url->getOptions();
    $this->assertNotEmpty($query_params, "The redirect url should have parameters when a valid project_id was set.");
    $this->assertArrayHasKey('query', $query_params, "The query should have been set in the url options.");
    $this->assertArrayHasKey('project_id', $query_params['query'], "The project_id should have been set in the url options['query'].");
    $this->assertEquals(1, $query_params['query']['project_id'], "The project_id set in the url query parameters was not what we expected.");

    // Next submit with a non-existing project_id.
    // -- always call validate first.
    $form_state->setValue('project_id', '999');
    $listbuilder->validateForm($form, $form_state);
    // -- confirm that the user is told this project doesn't exist.
    $errors = $form_state->getErrors();
    $this->assertCount(1, $errors, "We got errors when we submitting the form with a valid project_id.");
    $this->assertArrayHasKey('project_id', $errors, "We expected the project_id to be flagged in errors when a non-existing project_id was submitted.");
    $this->assertStringContainsString('The Research Experiment is not recognized.', (string) $errors['project_id'], "The error did not contain what we expected when a non-existing project_id is supplied.");
  }
}
