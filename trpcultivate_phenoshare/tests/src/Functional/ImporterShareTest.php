<?php

/**
 * @file
 * Functional test of Phenotypes Share Importer.
 */

namespace Drupal\Tests\trpcultivate_phenoshare\Functional;

use Drupal\Tests\BrowserTestBase;

 /**
  *  Class definition ImporterShareTest.
  */
class ImporterShareTest extends BrowserTestBase {
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
    // Link titled - Phenotypes Share - Data Importer
    $this->drupalGet('admin/tripal/loaders/');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Phenotypes Share - Data Importer');

    // Phenotypes Share Importer page, default to stage 01.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Phenotypes Share - Data Importer');
    $session->pageTextContains('Stage 1');

    // Navigate stages.
    // Stage 1 to Stage 2.
    $this->submitForm([], t('Next Stage'));
    $session->pageTextContains('Stage 2');

    // Stage 2 to Stage 3.
    $this->submitForm([], t('Next Stage'));
    $session->pageTextContains('Stage 3');

    // Stage 3 back to Stage 1.
    $this->submitForm([], t('Save'));
    $session->pageTextContains('Stage 1');
  }
}
