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
    // There is a link to the the importer with this link description.
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');

    // Importer share page.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
    

    // Stage indicator shows Stage 1 of total number of pages on
    // initial load of the importer page.
    $page_text = $this->getSession()->getPage()->getText();
    preg_match('/Stage [1-9] of ([1-9])/', $page_text, $matches);
    $total_stages = trim($matches[1]);

    // Each stage in accordion has a submit button that will
    // move to next stage and stage progress indicator will indicate
    // the current stage.
    for($i = 1; $i == $total_stages; $i++) {
      $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
      $this->submitForm([], 'Next Stage');
      $session->pageTextContains('Upload Stage ' . $i . ' of ' . $total_stages);

      // Ensure that stage accordion shows the correct stage - that is
      // current stage is expanded whereas the others are collapsed and
      // the title bar for each stage is mark by an * symbol.
      $session->pageTextContains('* Stage ' . $i . ':');
    }
  }
}