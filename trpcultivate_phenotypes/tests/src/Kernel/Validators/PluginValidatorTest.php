<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Test Tripal Cultivate Phenotypes Validator Plugin.
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class PluginValidatorTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration
   *
   * @var config_entity
   */
  private $config;

  /**
   * Import assets.
   */
  private $assets = [
    'project' => '',
    'genus' => '',
    'file' => 0,
    'headers' => ['Header 1', 'Header 2', 'Header 3'],
    'skip' => 0
  ];

  /**
   * Test file ids.
   */
  private $test_files;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'file',
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];


  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Set ontology.term: genus to null (id: 1).
    // This is used as type_id when creating relationship between a project and genus.
    $this->config->set('trpcultivate.phenotypes.ontology.terms.genus', 1);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Prepare by adding test records to genus, project and projectproperty
    // to relate a genus to a project.
    $project = 'Project - ' . uniqid();
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => $project . ' : Description'
      ])
      ->execute();

    $this->assets['project'] = $project;

    $genus = 'Wild Genus ' . uniqid();
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
        'type_id' => 1
      ])
      ->execute();

    $this->assets['genus'] = $genus;

    $this->chado_connection->insert('1:projectprop')
      ->fields([
        'project_id' => $project_id,
        'type_id' => 1,
        'value' => $genus
      ])
      ->execute();

    // Create Genus Ontology configuration.
    // All configuration and database value to null (id: 1).
    $config_name = str_replace(' ', '_', strtolower($genus));
    $genus_ontology_config = [
      'trait' => 1,
      'unit'   => 1,
      'method'  => 1,
      'database' => 1,
      'crop_ontology' => 1
    ];

    $this->config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $genus_ontology_config);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Test files.

    // File schema for FILE validator.
    $this->installEntitySchema('file');

    // Create a test file.
    $test_file  = 'test_data_file';
    $dir_public = 'public://';

    // Column headers - in the importer this is the headers property.
    $column_headers = implode("\t", $this->assets['headers']);

    // Prepare test file for the following extensions.
    // Each extension is set to file id 0 until created.
    $create_files = [
      // A valid file type, default type expected by the importer.
      'file-1' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      // A valid file type, an empty file.
      'file-2' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => '',
        'filesize' => 0
      ],
      // An alternative file type.
      'file-3' => [
        'ext' => 'txt',
        'mime' => 'text/plain',
        'content' => implode("\t", ['Header 1', 'Header 2', 'Header 3'])
      ],
      // Not valid file
      'file-4' => [
        'ext' => 'png',
        'mime' => 'image/png',
        'content' => ''
      ],
      // Pretend tsv file.
      'file-5' => [
        'ext' => 'tsv',
        'mime' => 'application/pdf',
        'file' => 'pdf.txt', // Can be found in the test Fixtures folder.
      ],
      // Test file with the correct headers.
      'file-6' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => $column_headers
      ],
      // Test file with the correct headers but permissions will make it unreadable.
      'file-7' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'content' => $column_headers,
        'permissions' => 'none',
      ],
      // Pretend tsv file that is disguised as a tsv.
      'file-8' => [
        'ext' => 'tsv',
        'mime' => 'text/tab-separated-values',
        'file' => 'pdf.txt', // Can be found in the test Fixtures folder.
      ],
    ];

    // To create an actual empty file with 0 file size:
    // First create the file and write an empty string then
    // create a file entity off this file.
    $empty_file = $dir_public . $test_file . 'file-2.' . $create_files['file-2']['ext'];
    file_put_contents($empty_file, '');

    foreach($create_files as $id => $prop) {
      $filename = $test_file . $id . '.' . $prop['ext'];

      $file = File::create([
        'filename' => $filename,
        'filemime' => $prop['mime'],
        'uri' => $dir_public . $filename,
        'status' => 0,
      ]);

      if (isset($prop['filesize'])) {
        // This is an empty file and to ensure the size is
        // as expected of an empty file = 0;
        $file->setSize(0);
      }

      $file->save();
      // Save id created.
      $create_files[ $id ]['ID'] = $file->id();

      // Write something on file with content key set to a string.
      if (!empty($prop['content'])) {
        $fileuri = $file->getFileUri();
        file_put_contents($fileuri, $prop['content']);
      }

      // If an existing file was specified then we can add that in here.
      if (!empty($prop['file'])) {
        $fileuri = $file->getFileUri();

        $path_to_fixtures = __DIR__ . '/../../Fixtures/';
        $full_path = $path_to_fixtures . $prop['file'];
        $this->assertFileIsReadable($full_path,
          "Unable to setup FILE ". $id . " because cannot access Fixture file at $full_path.");

        copy($full_path, $fileuri);
      }

      // Set file permissions if needed.
      if (!empty($prop['permissions'])) {
        $fileuri = $file->getFileUri();
        if ($prop['permissions'] == 'none') {
          chmod($fileuri, 0000);
        }
      }
    }

    $this->test_files =  $create_files;
  }

  /**
   * Test test records were created.
   */
  public function testRecordsCreated() {
    // Test project.
    $sql_project = "SELECT name FROM {1:project} WHERE name = :name LIMIT 1";
    $project = $this->chado_connection->query($sql_project, [':name' => $this->assets['project']])
      ->fetchField();

    $this->assertNotNull($project, 'Project test record not created.');

    // Test genus.
    $sql_genus = "SELECT genus FROM {1:organism} WHERE genus = :genus LIMIT 1";
    $genus = $this->chado_connection->query($sql_genus, [':genus' => $this->assets['genus']])
      ->fetchField();

    $this->assertNotNull($genus, 'Genus test record not created.');
  }

  /**
   * Test Project Plugin Validator.
   */
  public function testProjectPluginValidator() {
    $scope = 'PROJECT';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // Test a valid project - exists.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Test validator with a project (exists) without genus configured.
    // This will allow (pass) so long as project has no genus set and user
    // can set the genus so further in the importer the project-genus can be created.
    $project_no_genus = 'Project No Genus';
    $this->chado_connection->insert('1:project')
      ->fields([
        'name' => $project_no_genus,
        'description' => $project_no_genus . ' : Description'
      ])
      ->execute();

    $instance->loadAssets($project_no_genus, $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';

    // Test empty value.
    $instance->loadAssets('', $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Test validator plugin with non-existent project.
    $instance->loadAssets('NON-Existent-Project', $assets['genus'], $assets['file'], $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    // Test skip flag to skip this test - set to upcoming validation step.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Test Data file Plugin Validator.
   */
  public function testDataFilePluginValidator() {
    $scope = 'FILE';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // File is tsv, not empty and can be read.
    $file_id = $this->test_files['file-1']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File is txt (alternative file type), not empty and can be read.
    $file_id = $this->test_files['file-3']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';

    // No file attached - the file field did not return any file id.
    $file_id = null;
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Failed to load file id because it does not exist.
    $file_id = 9999;
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File is tsv, can be read but is an empty file - 0 file size.
    $file_id = $this->test_files['file-2']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File is tsv, but permissions mean it cannot be read.
    $file_id = $this->test_files['file-7']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File is pdf but pretending to be tsv.
    // -- case where mime still correctly indicates pdf.
    $file_id = $this->test_files['file-5']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
    // -- case where mime is also tsv but it is not a tsv really.
    $file_id = $this->test_files['file-8']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Not a valid file. Image/PNG.
    $file_id = $this->test_files['file-4']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Test Headers Plugin Validator.
   */
  public function testColumnHeaderPluginValidator() {
    $scope = 'HEADERS';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

    // PASS:
    $status = 'pass';

    // File headers match the expected headers.
    $file_id = $this->test_files['file-6']['ID'];
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // FAIL:
    $status = 'fail';

    // Change the contents of the tsv_file so the headers do not match the headers asset;
    $file = File::load($file_id);
    $file_uri = $file->getFileUri();
    file_put_contents($file_uri, 'NOT THE HEADERS EXPECTED');

    // File headers do not match the expected headers - Extra Headers.
    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // File headers do not match the expected headers - Less/Missing Headers.
    unset($assets['headers'][2]); // Removes Header 3.
    file_put_contents($file_uri, $assets['headers']);

    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);

    // Header row is missing.
    file_put_contents($file_uri, '');

    $instance->loadAssets($assets['project'], $assets['genus'], $file_id, $assets['headers'], $assets['skip']);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);


    // TODO:
    $status = 'todo';

    // Test skip flag to skip this test - set to upcoming validation step.
    $instance->loadAssets($assets['project'], $assets['genus'], $assets['file'], $assets['headers'], 1);
    $validation[ $scope ] = $instance->validate();
    $this->assertEquals($validation[ $scope ]['status'], $status);
  }

  /**
   * Template.
   * Test SCOPE Plugin Validator.
   *//*
  public function testScopePluginValidator() {
    $scope = 'SCOPE';
    $validator = $this->plugin_manager->getValidatorIdWithScope($scope);
    $instance = $this->plugin_manager->createInstance($validator);
    $assets = $this->assets;

     // PASS:
     $status = 'pass';


     // FAIL:
     $status = 'fail';


     // TODO:
     $status = 'todo';
  }
  */
}
