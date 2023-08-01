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

    // Phenotypes Share Importer page, default to stage 01.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
    $session->pageTextContains('Stage01');

    $fld_schema = 'schema_name';
    $fld = $this->getSession()->getPage()->findField($fld_schema);
    $fld_name = $fld->getAttribute('name');
  
    $db = $this->chado->getSchemaName();
    $page_text = $this->getSession()->getPage()->getText();
    $has_schema_option = (str_contains($page_text, $db)) ? TRUE : FALSE;
        
    if ($fld_name == $fld_schema && $has_schema_option) {
      // Perform test of each stage if field has schema field select
      // in advance options of the form.

      // Could not test if schema field has no schema to select from.
      $schema = (empty($db)) ? '' : $db;
      $args =  [$fld_schema => $schema];

      // Stage 1 to Stage 2.
      $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
      $session->statusCodeEquals(200);    
      $this->submitForm($args, 'Next Stage');
      $session->pageTextContains('Stage02');

      // Stage 2 to Stage 3.
      $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
      $session->statusCodeEquals(200);
      $this->submitForm($args, 'Next Stage');
      $this->submitForm($args, 'Next Stage');
      $session->pageTextContains('Stage03');

      // Stage 3 back to Stage 1.
      $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
      $session->statusCodeEquals(200);
      $this->submitForm($args, 'Next Stage');
      $this->submitForm($args, 'Next Stage');
      $this->submitForm($args, 'Save');
      $session->pageTextContains('Stage01');
    }
  }
}
