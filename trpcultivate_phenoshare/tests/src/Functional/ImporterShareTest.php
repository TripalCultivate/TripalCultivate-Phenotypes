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
    
    
    // Test cacheing of stage number.
    // Test stage is correctly styled (tcp-current-stage class).

    // On page load, test that it is stage 1. Subsequent tests will
    // be second stage then the last stage.
    $page_content = $this->getSession()->getPage()->getContent();
    // Important field that holds the current stage.
    preg_match('/<input id="tcp-current-stage" .+ value="([1-9])" \/>/', $page_content, $matches);
    $current_stage = $matches[1];
    $this->assertEquals($current_stage, 1);

    // All stages (with class tcp-stage), but less one stage since first stage has been verified.
    preg_match_all('/tcp-stage/', $page_content, $matches);
    unset($matches[0][ count($matches[0]) - 1 ]);

    foreach($matches[0] as $i => $stage) {
      $this->submitForm([], 'Next Stage');
      $page_content = $this->getSession()->getPage()->getContent();
      preg_match('/<input id="tcp-current-stage" .+ value="([1-9])" \/>/', $page_content, $matches);
      $current_stage = $matches[1];

      $this->assertEquals($current_stage, $i + 2);

      preg_match_all('/(tcp-stage\s*.*)/', $page_content, $matches);
      //print_r($matches);
    }
  }
}