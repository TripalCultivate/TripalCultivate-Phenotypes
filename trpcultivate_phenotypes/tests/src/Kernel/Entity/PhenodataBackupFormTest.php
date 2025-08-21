<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Entity;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Form\FormState;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;
use Drupal\tripal\Entity\TripalEntityType;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Entity\PhenodataBackup;

/**
 * Tests associated with the Phenodata Backup create/edit form.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
class PhenodataBackupFormTest extends ChadoTestKernelBase {

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
    // 1. Can't create/edit any backups, not even their own.
    'other' => [
      'name' => 'Unauthorized',
      'permissions' => [],
    ],
    // 2. Can create backups but only edit their own.
    'view_own' => [
      'name' => 'Researcher',
      'permissions' => ['view_own phenodata_backup'],
    ],
    // 3. Can create backups and edit any backup.
    'view_all' => [
      'name' => 'PI',
      'permissions' => ['view_all phenodata_backup'],
    ],
    // 4. Can administer backups (and thus also create/edit them).
    'admin' => [
      'name' => 'Administrator',
      'permissions' => ['administer phenodata_backup'],
    ],
  ];

  /**
   * Backups created during setup for testing access.
   *
   * @var array
   *   Keyed by the user key and the value is the id of the backup.
   */
  protected array $backups = [];

  /**
   * An existing test file created during setUp().
   *
   * @var int
   */
  protected int $fid;

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
    $this->prepareEnvironment(['TripalTerm']);

    $container = \Drupal::getContainer();
    $this->entityTypeManager = $container->get('entity_type.manager');

    // Create Research Experiment Content type.
    // -- term.
    $term_values = [
      'id_space_name' => 'SIO',
      'term' => [
        'accession' => '000994',
        'name' => 'Experiment',
      ],
    ];
    $term = $this->createTripalTerm($term_values, 'tripal_default_id_space', 'tripal_default_vocabulary');
    $this->assertIsObject($term,
      'We were unable to create a tripal term during test setup');
    // -- content type.
    $entityType = TripalEntityType::create([
      'id' => 'research_experiment',
      'label' => 'Research Experiment',
      'term' => $term,
      'help_text' => 'You are on your own here',
      'category' => 'Research Management',
      'title_format' => '[TripalEntityType__term_label] Entity #[TripalEntity__entity_id]',
      'url_format' => '/[TripalEntityType__term_namespace]/[TripalEntityType__term_accession]/[TripalEntity__entity_id]',
      'hide_empty_field' => '',
      'ajax_field' => '',
    ]);
    $this->assertIsObject($entityType,
      'We were unable to create our Tripal Entity type during test setup');
    $entityType->save();

    // Create a test file.
    // This will be reused for all the backups.
    $file_obj = $this->createTestFile([
      'filename' => 'backup_data_file.tsv',
      'mime' => 'text/tab-separated-values',
      'content' => [
        'string' => implode("\t", ['Header 1', 'Header 2', 'Header 3']),
      ],
    ]);
    $file_id = $file_obj->id();
    $this->fid = $file_id;

    // Create a 2 projects.
    $project_a_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => 'Project A'])
      ->execute();
    $this->chado_connection->insert('1:project')
      ->fields(['name' => 'Project B'])
      ->execute();

    // Create users.
    // -- Create first UID1 so, the other users are not super-admin.
    $this->createUser([], NULL, FALSE, ['uid' => 1]);
    // Now create the rest of the users.
    foreach ($this->users as $user_key => $user_defn) {
      $this->users[$user_key]['object'] = $this->createUser(
        $user_defn['permissions'],
        $user_defn['name']
      );
      $this->assertNotFalse($this->users[$user_key]['object'],
        "We were unable to create a user for testing key:$user_key, params:" . print_r($user_defn, TRUE));

      $this->users[$user_key]['object']->save();
      $this->users[$user_key]['id'] = $this->users[$user_key]['object']->id();
    }

    // Create two backups to test the edit page.
    // 1. By admin.
    $values = [
      'id' => uniqid(),
      'file_id' => $file_id,
      'project_id' => $project_a_id,
      'comments' => 'This is a comment by admin',
      'backup_date' => date('Y-M-d H:i:s'),
      'user_id' => $this->users['admin']['id'],
    ];
    $entity = PhenodataBackup::create($values);
    $entity->save();
    $this->backups['admin'] = $entity->id();
    // 2. By view_all.
    $values = [
      'id' => uniqid(),
      'file_id' => $file_id,
      'project_id' => $project_a_id,
      'comments' => 'This is a comment by PI',
      'backup_date' => date('Y-M-d H:i:s'),
      'user_id' => $this->users['view_all']['id'],
    ];
    $entity = PhenodataBackup::create($values);
    $entity->save();
    $this->backups['view_all'] = $entity->id();
    // 3. By view_own.
    $values = [
      'id' => uniqid(),
      'file_id' => $file_id,
      'project_id' => $project_a_id,
      'comments' => 'This is a comment by researcher',
      'backup_date' => date('Y-M-d H:i:s'),
      'user_id' => $this->users['view_own']['id'],
    ];
    $entity = PhenodataBackup::create($values);
    $entity->save();
    $this->backups['view_own'] = $entity->id();
  }

  /**
   * Data Provider: Provides a variety of users/backups to test.
   *
   * @return array
   *   Each element is a scenario to be tested and consists of the following:
   *   - current user (string): a key from the users property of this class.
   *   - test backups (array): two keys (own and other) indicate the users
   *     property key to use to fetch the backup for testing these cases.
   *   - expected (array): keys create, edit_own and edit_other with values of
   *     TRUE to indicate this user should be able to access this page and
   *     FALSE if not.
   */
  public static function providePhenodataBackupScenarios() {
    $scenarios = [];

    // User who does not have any phenodata backup permissions.
    $scenarios[] = [
      'other',
      // Backups.
      [
        'own' => FALSE,
        'other' => 'admin',
      ],
      // Access.
      [
        'create' => FALSE,
        'edit_own' => FALSE,
        'edit_others' => FALSE,
      ],
    ];

    // User who can only edit their own backups.
    $scenarios[] = [
      'view_own',
      // Backups.
      [
        'own' => 'view_own',
        'other' => 'view_all',
      ],
      // Access.
      [
        'create' => TRUE,
        'edit_own' => TRUE,
        'edit_others' => FALSE,
      ],
    ];

    // User who can edit all backups.
    $scenarios[] = [
      'view_all',
      // Backups.
      [
        'own' => 'view_all',
        'other' => 'view_own',
      ],
      // Access.
      [
        'create' => TRUE,
        'edit_own' => TRUE,
        'edit_others' => TRUE,
      ],
    ];

    // User who can administer backups.
    $scenarios[] = [
      'admin',
      // Backups.
      [
        'own' => 'admin',
        'other' => 'view_all',
      ],
      // Access.
      [
        'create' => TRUE,
        'edit_own' => TRUE,
        'edit_others' => TRUE,
      ],
    ];

    return $scenarios;
  }

  /**
   * Test Phenodata Backup form.
   *
   * @param string $current_user
   *   A key from the users property to indicate the user to login.
   * @param array $test_backups
   *   Indicates the users property key to use to fetch each backup for testing.
   *   The key 'own' indicates a backup owned by the current user and
   *   'other' indicates a backup owned by a different user. The backup
   *   is looked up using the backups property populated during setUp().
   * @param array $expected
   *   Indicates whether the current user should have access to specific pages.
   *   A value of TRUE means this user should be able to access this page and
   *   FALSE means AccessDenied should be thrown.
   *   - 'create': the create form.
   *   - 'edit_own': the edit form for the $test_backups['own'] backup.
   *   - 'edit_other':the edit form for the $test_backups['other'] backup.
   *
   * @dataProvider providePhenodataBackupScenarios
   */
  #[DataProvider('providePhenodataBackupScenarios')]
  public function testPhenodataBackupFormAccess(string $current_user, array $test_backups, array $expected) {

    // Login the current user.
    $current_user = $this->users[$current_user]['object'];
    $this->setCurrentUser($current_user);

    // Create Form.
    // Note: no need to check the exception message since the exception class
    // is specific to AccessDenied.
    $exception_caught = FALSE;
    $expected_code_label = ($expected['create']) ? "200 (ok)" : "403 (Unauthorized)";
    try {
      $entity = PhenodataBackup::create();
      $form = \Drupal::service('entity.form_builder')->getForm($entity, 'add');
    }
    catch (AccessDeniedHttpException $e) {
      $exception_caught = TRUE;
    }
    $access_granted = !$exception_caught;
    $this->assertEquals($expected['create'], $access_granted, "We did not get the access code of '$expected_code_label' we expected when creating a backup.");
    if ($access_granted) {
      $this->assertPhenodataBackupFormMatches($entity, $form, "The create form did not match what we expected.");
    }

    // Edit Own Backup.
    // Note: no need to check the exception message since the exception class
    // is specific to AccessDenied.
    if ($test_backups['own'] !== FALSE) {
      $exception_caught = FALSE;
      $expected_code_label = ($expected['edit_own']) ? "200 (ok)" : "403 (Unauthorized)";
      try {
        $backup_id = $this->backups[$test_backups['own']];
        $entity = $this->container->get('entity_type.manager')
          ->getStorage('phenodata_backup')
          ->load($backup_id);
        $form = \Drupal::service('entity.form_builder')->getForm($entity, 'edit');
      }
      catch (AccessDeniedHttpException $e) {
        $exception_caught = TRUE;
      }
      $access_granted = !$exception_caught;
      $this->assertEquals($expected['edit_own'], $access_granted, "We did not get the access code of '$expected_code_label' we expected when editing our own backup.");
      if ($access_granted) {
        $this->assertPhenodataBackupFormMatches($entity, $form, "The create form did not match what we expected.");
      }
    }

    // Edit someone elses Backup.
    // Note: no need to check the exception message since the exception class
    // is specific to AccessDenied.
    try {
      $exception_caught = FALSE;
      $expected_code_label = ($expected['edit_others']) ? "200 (ok)" : "403 (Unauthorized)";
      $backup_id = $this->backups[$test_backups['other']];
      $entity = $this->container->get('entity_type.manager')
        ->getStorage('phenodata_backup')
        ->load($backup_id);
      $form = \Drupal::service('entity.form_builder')->getForm($entity, 'edit');
    }
    catch (AccessDeniedHttpException $e) {
      $exception_caught = TRUE;
    }
    $access_granted = !$exception_caught;
    $this->assertEquals($expected['edit_others'], $access_granted, "We did not get the access code of '$expected_code_label' we expected when editing someone elses backup.");
    if ($access_granted) {
      $this->assertPhenodataBackupFormMatches($entity, $form, "The create form did not match what we expected.");
    }
  }

  /**
   * Provides values and expectations for the phenodata backup form.
   *
   * Scenarios:
   *   - Valid data for create and no changed on edit.
   *   - @todo Valid data for create and valid changed on edit.
   *   - @todo Project name is not of the right format.
   *   - @todo Project name is the right format but doesn't exist.
   *
   * Note: this data provider is designed with the above todo scenarios in mind.
   * Right now only valid data submitted on create is being tested and the test
   * will need to be updated when the additional scenarios are added.
   *
   * @return array
   *   Each element is a scenario to be tested and consists of the following:
   *   - create_input: an array with the keys id, backup_file, project_name and
   *     comments. These map to the form element keys.
   *   - create_expectations: an array of expectations indicating what we expect
   *     when the create form is submitted with the create_input values.
   *   - edit_input: an array with the keys id, backup_file, project_name and
   *     comments. These map to the form element keys.
   *   - edit_expectations: an array of expectations indicating what we expect
   *     when the edit form is submitted with the edit_input values.
   */
  public static function providePhenodataBackupValues(): array {
    $scenarios = [];

    // Valid data, no changes on edit.
    $scenarios[] = [
      [
        'id' => '12345',
        'backup_file' => TRUE,
        'project_name' => 'Project A (1)',
        'comments' => 'This data is all valid.',
      ],
      [],
      [],
      [],
    ];

    return $scenarios;
  }

  /**
   * Test Phenodata Backup form validate/submit.
   *
   * @param array $create_input
   *   An array with the keys id, backup_file, project_name and comments.
   *   These map to the form element keys.
   * @param array $create_expectations
   *   An array of expectations indicating what we expect when the create form
   *   is submitted with the create_input values.
   * @param array $edit_input
   *   An array with the keys id, backup_file, project_name and comments.
   *   These map to the form element keys.
   * @param array $edit_expectations
   *   An array of expectations indicating what we expect when the edit form
   *   is submitted with the edit_input values.
   *
   *   Checks:
   *   - The create form can be validated with the input data.
   *   - The create form can be submitted if there are no validation errors.
   *   - @todo The new entity contains the values expected.
   *   - @todo The edit form can be changed + validated.
   *   - @todo The edit form can be submitted if there are no validation errors.
   *   - @todo The updated entity contains the values expected.
   *   - @todo The file is renamed appropriately.
   *
   * @dataProvider providePhenodataBackupValues
   */
  #[DataProvider('providePhenodataBackupValues')]
  public function testPhenodataBackupFormSubmit(array $create_input, array $create_expectations, array $edit_input, array $edit_expectations) {

    // Login the current user.
    $current_user = $this->users['view_all']['object'];
    $this->setCurrentUser($current_user);

    // Set the fid in the input.
    if ($create_input['backup_file'] === TRUE) {
      $create_input['backup_file'] = [$this->fid];
    }

    // Create Form.
    $entity = PhenodataBackup::create();
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('phenodata_backup', 'add');
    $form_object->setEntity($entity);
    $form_state = new FormState();
    $form = \Drupal::service('entity.form_builder')->getForm($entity, 'add');
    // -- set the form values.
    foreach ($create_input as $key => $value) {
      $form_state->setValue($key, $value);
    }
    // -- now validate it with the data provided.
    $form_object->validateForm($form, $form_state);
    $errors = $form_state->getErrors();
    // @todo use the expectations to check for the errors.
    $this->assertCount(0, $errors, "We got errors when we submitting the form the supplied values.");
    // -- if there are no errors then submit the form.
    // @todo use the expectations to check the backup was created correctly.
    if (count($errors) === 0) {
      $form_object->submitForm($form, $form_state);
      $form_object->save($form, $form_state);
    }
  }

  /**
   * Check the form matches our expectations.
   *
   * @todo add a lot more checks to ensure the form matches expectations
   * @todo specifically check the default value!
   *
   * @param \Drupal\trpcultivate_phenotypes\Entity\PhenodataBackup $entity
   *   The phenodata backup entity we used to build the form.
   * @param array $form
   *   The form built to be checked for consistency with expectations.
   * @param string $message
   *   The message to add to the assert statements to provide context.
   */
  public function assertPhenodataBackupFormMatches(PhenodataBackup $entity, array $form, string $message) {

    // CHECK: Form should be an array.
    $this->assertIsArray($form, "FORM NOT ARRAY " . $message);

    // CHECK: Form elements expected should exist.
    $expected_keys = [
      '#form_id',
      'id',
      'backup_file',
      'project_name',
      'comments',
    ];
    foreach ($expected_keys as $expected_key) {
      $code = "MISSING ELEMENT " . $expected_key;
      $this->assertArrayHasKey($expected_key, $form, $code . ' ' . $message);
    }
  }

}
