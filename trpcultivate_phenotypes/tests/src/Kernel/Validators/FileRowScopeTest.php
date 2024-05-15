<?php

/**
 * @file
 * Kernel tests for validator plugins that operate within the scope of "FILE ROW"
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal\Services\TripalLogger;
use Drupal\file\Entity\File;

 /**
  * Tests Tripal Cultivate Phenotypes Validator Plugins that apply to a single row
  * of the input file - the "FILE ROW" scope
  *
  * @group trpcultivate_phenotypes
  * @group validators
  * @group file_row_scope
  */
class FileRowScopePluginValidatorTest extends ChadoTestKernelBase {
  /**
   * Plugin Manager service.
   */
  protected $plugin_manager;

  /**
   * Test Empty Cell Plugin Validator.
   */
  public function testEmptyCellPluginValidator() {

  }

  /**
   * Test Trait Type Column Plugin Validator.
   */
  public function testTraitTypeColumnPluginValidator() {

  }

  /**
   * Test Duplicate Traits Plugin Validator.
   */
  public function testDuplicateTraitsPluginValidator() {

  }
}
