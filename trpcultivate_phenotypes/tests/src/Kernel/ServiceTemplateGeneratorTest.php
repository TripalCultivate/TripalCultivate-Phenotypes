<?php

/**
 * @file
 * Kernel test of Template Generator service.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Tests associated with the template file generator service.
 *
 * @group trpcultivate_phenotypes
 */
class ServiceTemplateGeneratorTest extends ChadoTestKernelBase {
  /**
   * Modules to enable.
   */
  protected static $modules = [
    'user',
    'file',
    'system',
    'trpcultivate_phenotypes'
  ];

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
  }

  public function testTemplateGeneratorService() {
    // This is a bare-bones test of the service as it is sooo complicated
    // to setup a test environment for reading and writing a file.
    // @TODO: revisit when with good understanding of Drupal file system in a Kernel test.

    // As is, this automated test, tests that the template generator creates a file
    // and that the generated file has some content in it by inspecting the file size.
    $template_generator = \Drupal::service('trpcultivate_phenotypes.template_generator');

    // Generate a template file.
    $plugin_id = 'doesnt-nned-to-be-real';
    $column_headers = ['Header A', 'Header B', 'Header C'];
    $link = $template_generator->generateFile($plugin_id, $column_headers);

    // Assert a link has been created.
    $this->assertNotNull($link, 'Failed to generate template file link.');

    // Works locally but fails in actions. Importer share functional test implements the same file size check.
    // $this->assertGreaterThanOrEqual(1, filesize($link), 'The template file generated is empty.');
  }
}
