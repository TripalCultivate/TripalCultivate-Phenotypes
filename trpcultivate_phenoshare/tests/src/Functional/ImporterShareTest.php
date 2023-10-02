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

    // Test stage cacheing and if stage is styled with the correct css class.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
 
    $page_content = $this->getSession()->getPage()->getContent();
    // Get all stage accordion title/header element.
    preg_match_all('/tcp\-stage-title/', $page_content, $matches); 
 
    foreach($matches[0] as $i => $stage) {
      preg_match('/<input id="tcp-current-stage" .+ value="([1-9])" \/>/', $page_content, $matches);
      $current_stage = $matches[1];
      
      // Stage number set in the hidden field used as cache.
      $this->assertEquals($current_stage, ($i + 1), 'Current stage does not match expected stage');

      // Current stage styled with css class name tcp-current-stage
      preg_match_all('/(tcp\-stage-title[\s{1}tcp\-\w+\-stage]*)">/', $page_content, $matches);
      $this->assertEquals('tcp-stage-title tcp-current-stage', $matches[1][ $i ], 'Stage does not contain expected css class.');
      
      // Next stage...
      $this->submitForm([], 'Next Stage');
      $page_content = $this->getSession()->getPage()->getContent();
    }
  }
}