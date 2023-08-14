<?php

/**
 * @file
 * Functional test of Phenotypes Share Importer.
 */

namespace Drupal\Tests\trpcultivate_phenoshare\Functional;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Tests\tripal_chado\Functional\ChadoTestBrowserBase;

 /**
  *  Class definition ImporterShareTest.
  */
class ImporterShareTest extends ChadoTestBrowserBase {
  protected $defaultTheme = 'stark';

  /**
   * Tripal DBX Chado Connection object
   *
   * @var ChadoConnection
   */
  protected $chado;

  /**
   * Modules to enabled
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
    'trpcultivate_phenoshare'
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    
    // Create a test schema.
    $this->chado = $this->createTestSchema(ChadoTestBrowserBase::PREPARE_TEST_CHADO);
  }

  /**
   * Test Phenotypes Share Importer.
   */
  public function testImportShareForm() {
    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal',
      'allow tripal import'
    ]);
    $this->drupalLogin($admin);

    // Assert custom Phenotypes Share importer is an item in
    // admin/tripal/loaders page.
    // Link titled - Tripal Cultivate: Open Science Phenotypic Data
    $this->drupalGet('admin/tripal/loaders/');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
  }
}