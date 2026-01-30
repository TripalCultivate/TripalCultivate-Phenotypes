<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Controllers;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Tests associated with PhenoExperiemtPhenoBackupController class.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
class PhenoExperimentPhenoBackupControllerTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'markup',
    'path',
    'path_alias',
    'system',
    'user',
    'views',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

}
