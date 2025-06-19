<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Controllers;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate_phenotypes\Controller\TripalCultivatePhenotypesSettingsController;

/**
 * Tests associated with TripalCultivatePhenotypesSettingsController class.
 */
class ConfigSettingsControllerTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

  }

  /**
   * Tests that the settings page loads.
   */
  public function testLoadPage() {
    // Create a controller instance.
    $controller = TripalCultivatePhenotypesSettingsController::create($this->container);

    // Call the loadPage method to get the array.
    $render_arr = $controller->loadPage();

    // Check that the render array is an array with the expected keys.
    $this->assertArrayHasKey('rrules', $render_arr, 'Render array should have a rrules key.');
    $this->assertArrayHasKey('watermarking', $render_arr, 'Render array should have a watermarking key.');
    $this->assertArrayHasKey('ontologies', $render_arr, 'Render array should have an ontologies key.');
  }

}
