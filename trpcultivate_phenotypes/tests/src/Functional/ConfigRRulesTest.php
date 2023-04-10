<?php

/**
 * @file
 * Functional test of R Transfomration rules configuration page.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Functional;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Tests\tripal_chado\Functional\ChadoTestBrowserBase;

 /**
  *  Class definition ConfigRRulesTest.
  */
class ConfigRRulesTest extends ChadoTestBrowserBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';

  /**
   * Modules to enabled
   * 
   * @var array
   */
  protected static $modules = ['trpcultivate_phenotypes'];

  /**
   * Admin user with admin privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin_user;

  /**
   * Test R transformation rules configuration page.
   */
  public function testForm() {
    // Setup admin user account.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal'
    ]);
    
    // Login admin user.
    $this->drupalLogin($admin_user);
    
    // Get default configuration for R rules set in config/install.
    $config = $this->config(static::SETTINGS);
    $config_r = 'trpcultivate.phenotypes.r_config.';
    $r_rules = [
      $config_r . 'words' => $config->get($config_r . 'words'),
      $config_r . 'chars'  => $config->get($config_r . 'chars'),
      $config_r . 'replace' => $config->get($config_r . 'replace'),
    ];

    // Update configuration settings.
    // Updating R rules by appending ok to words rule, # sign to characters rule
    // and ok = okay match and replace rule
    $update_r_rules = [
      'words' => $r_rules[ $config_r . 'words' ] . ',ok',
      'chars'  => $r_rules[ $config_r . 'chars' ] . ',#',
      'replace' => $r_rules[ $config_r . 'replace' ] . ',ok = okay', 
    ];

    // Access R rules configuration page.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/r-rules');
    $this->assertSession()->statusCodeEquals(200);
    
    // Submit form.
    $this->submitForm($update_r_rules, 'Save configuration');

    // Assert configuration saved.
    $this->assertRaw('The configuration options have been saved.');

    // Assert Fields reflect the updated configuration.
    $this->assertSession()->fieldValueEquals('words', $update_r_rules['words']);
    $this->assertSession()->fieldValueEquals('chars', $update_r_rules['chars']);
    $this->assertSession()->fieldValueEquals('replace', $update_r_rules['replace']);
  }
}