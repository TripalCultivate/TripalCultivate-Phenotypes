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

    // Phenotypes Share Importer page, default to stage 01.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
    $session->pageTextContains('Stage01');

    // Navigate stages.
    $this->chado = $this->createTestSchema(ChadoTestBrowserBase::PREPARE_TEST_CHADO);
    $db = $this->chado->getSchemaName();

    // Stage 1 to Stage 2.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session->statusCodeEquals(200);
    $this->submitForm(['schema_name' => $db], 'Next Stage');
    $session->pageTextContains('Stage02');

    // Stage 2 to Stage 3.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session->statusCodeEquals(200);
    $this->submitForm(['schema_name' => $db], 'Next Stage');
    $this->submitForm(['schema_name' => $db], 'Next Stage');
    $session->pageTextContains('Stage03');

    // Stage 3 back to Stage 1.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session->statusCodeEquals(200);
    $this->submitForm(['schema_name' => $db], 'Next Stage');
    $this->submitForm(['schema_name' => $db], 'Next Stage');
    $this->submitForm(['schema_name' => $db], 'Save');
    $session->pageTextContains('Stage01');
  }
}
