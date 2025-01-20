<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\user\Entity\User;

/**
 * Test file template generator service.
 *
 * @group trpcultivate_phenotypes
 * @group template_generate
 */
class ServiceTemplateGeneratorTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'file',
    'user',
    'system',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * Configuration entity.
   *
   * @var object
   */
  protected $config;

  /**
   * The TripalCultivatePhenotypes File Template Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesFileTemplateService
   */
  protected $service_FileTemplate;

  /**
   * A test user.
   *
   * @var Drupal\user\Entity\User
   */
  protected $user;

  /**
   * File system interface.
   *
   * @var Drupal\Core\File\FileSystemInterface
   */
  protected $file_system;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Setup file configuration and schema.
    $this->installConfig(['file', 'trpcultivate_phenotypes']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');

    $this->file_system = \Drupal::service('file_system');
    $this->config = \Drupal::service('config.factory');
    $this->service_FileTemplate = \Drupal::service('trpcultivate_phenotypes.template_generator');

    // Create a user.
    $this->user = User::create([
      'name' => 'user-collector',
      'roles' => ['authenticated user'],
    ]);
    $this->user->save();

    \Drupal::currentUser()->setAccount($this->user);
  }

  /**
   * Data Provider: provide various test scenarios of file format and headers.
   *
   * @return array
   *   Each test file scenario is an array with the following values:
   *   - A string, humnan-readable short description of the test scenario.
   *   - A string, the importer plugin id.
   *   - An array, the list of headers that will become the header row in file.
   *   - An array, the 'file_types' plugin annotation definition of importer.
   *   - An array of expected values, with the following keys:
   *     - 'filename': the expected filename of the template file.
   *     - 'extension': the expected file extension of the template file.
   *     - 'delimiter': the expected delimiter used to encode the header row.
   *     - 'header_row': the expected content (header row) of the file.
   */
  public function provideParametersForFileTemplateGenerator() {
    return [
      // #0: A tsv file.
      [
        'a tsv file',
        'my-importer',
        ['Header A', 'Header B', 'Header C'],
        [
          'tsv',
        ],
        [
          'filename' => "my-importer-data-collection-template-file-user-collector.tsv",
          'extension' => 'tsv',
          'delimiter' => "\t",
          'header_row' => implode("\t", ['Header A', 'Header B', 'Header C']),
        ],
      ],

      // #1: A csv file.
      [
        'a csv file',
        'another-importer',
        ['Header E', 'Header F', 'Header G'],
        [
          'csv',
          'tsv',
          'txt',
        ],
        [
          'filename' => "another-importer-data-collection-template-file-user-collector.csv",
          'extension' => 'csv',
          'delimiter' => ",",
          'header_row' => "Header E,Header F,Header G",
        ],
      ],

      // #2: A txt file - multiple items in the 'file_types' definition.
      [
        'a txt file',
        'basic-importer',
        ['Header X', 'Header Y', 'Header Z'],
        [
          'txt',
          'csv',
        ],
        [
          'filename' => "basic-importer-data-collection-template-file-user-collector.txt",
          'extension' => 'txt',
          'delimiter' => "\t",
          'header_row' => implode("\t", ['Header X', 'Header Y', 'Header Z']),
        ],
      ],
    ];
  }

  /**
   * Test file template generator service.
   *
   * @param string $scenario
   *   Humnan-readable short description of the test scenario.
   * @param string $importer_id
   *   The importer plugin id.
   * @param array $column_headers
   *   The list of headers that will become the header row in file.
   * @param array $file_extensions
   *   The 'file_types' plugin annotation definition of the importer.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'filename': the expected filename of the template file.
   *     - 'extension': the expected file extension of the template file.
   *     - 'delimiter': the expected delimiter used to encode the header row.
   *     - 'header_row': the expected content (header row) of the file.
   *
   * @dataProvider provideParametersForFileTemplateGenerator
   */
  public function testTemplateGeneratorService($scenario, $importer_id, $column_headers, $file_extensions, $expected) {

    // Generate the template file.
    $link = $this->service_FileTemplate->generateFile($importer_id, $column_headers, $file_extensions);

    // Assert a link has been created.
    $this->assertNotNull($link, 'Failed to generate template file link in scenario ' . $scenario);

    // Assert that a file has been created in the configured directory
    // for template files.
    $dir_templates = $this->config->get('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.directory.template_file');

    $file_system = $this->file_system->realpath($dir_templates);
    $files_in_dir = array_diff(scandir($file_system), ['..', '.']);
    $template_file = (string) reset($files_in_dir);

    // Filename is the expected file name.
    $this->assertEquals(
      $expected['filename'],
      $template_file,
      'The filename of the template file does not match expected filename in scenario ' . $scenario
    );

    // File is of the expected file extension.
    $this->assertEquals(
      $expected['extension'],
      pathinfo($template_file, PATHINFO_EXTENSION),
      'The file extension of the template file does not match expected file extension in scenario ' . $scenario
    );

    // The filename contains the importer id and username.
    $this->assertStringContainsString(
      $importer_id,
      $template_file,
      'The filename of the template file is expected to contain the importer id in scenario ' . $scenario
    );

    $this->assertStringContainsString(
      $this->user->getAccountName(),
      $template_file,
      'The filename of the template file is expected to contain the username in scenario ' . $scenario
    );

    // The template file has the headers.
    // Assert that the headers were inserted into the file as the header row
    // using the delimiter.
    $file_contents = fopen($file_system . '/' . $template_file, 'r');
    if ($file_contents) {
      $header_row = trim(fgets($file_contents), "\n");
      fclose($file_contents);
    }

    $this->assertEquals(
      $expected['header_row'],
      $header_row,
      'The template file does not contain the expected column headers in scenario ' . $scenario
    );

    // Using the delimiter to separate the header values, the result should
    // match the headers array provided.
    $this->assertEquals(
      $column_headers,
      explode($expected['delimiter'], $header_row),
      'The header row in the template file does not match expected column headers in scenario ' . $scenario
    );
  }

}
