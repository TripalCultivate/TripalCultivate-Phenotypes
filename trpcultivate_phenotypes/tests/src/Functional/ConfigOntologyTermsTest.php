<?php

/**
 * @file
 * Functional test of Ontology and Terms configuration page.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Functional;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Tests\tripal_chado\Functional\ChadoTestBrowserBase;

 /**
  *  Class definition ConfigOntologyTermsTest.
  */
class ConfigOntologyTermsTest extends ChadoTestBrowserBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';

  protected $defaultTheme = 'stark';

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
   * Test Ontology and Terms configuration page.
   */
  public function testForm() {
    // Setup admin user account.
    $this->admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal'
    ]);

    // Login admin user.
    $this->drupalLogin($this->admin_user);

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Access to configuration page prior to execution of Tripal Job relating
    // to creation of Ontology and Terms will show a warning message.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/ontology');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Warning message');

    // Tripal Jobs to create/insert terms and setup genus ontology configuration
    // are created on install of tripalcultivate_phenotypes. The job may execute or not
    // but this block will create them manually.
    $chado = $this->createTestSchema(ChadoTestBrowserBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $chado);

    $test_insert_genus = [
      'Lens',
      'Cicer'
    ];

    $ins_genus = "
      INSERT INTO {1:organism} (genus, species, type_id)
      VALUES
        ('$test_insert_genus[0]', 'culinaris', 1),
        ('$test_insert_genus[1]', 'arientinum', 1)
    ";

    $chado->query($ins_genus);

    // Load genus ontology.
    $service_genusontology = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
    $service_genusontology->loadGenusOntology();

    // Install all default terms.
    $service_terms = \Drupal::service('trpcultivate_phenotypes.terms');
    $service_terms->loadTerms();

    // Access Ontology and Terms configuration page.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/ontology');
    $session = $this->assertSession();

    $session->statusCodeEquals(200);
    $session->pageTextContains('Configure Tripal Cultivate Phenotypes: Ontology Terms');

    $genus_ontology = $service_genusontology->defineGenusOntology();
    
    $null_value = 1;
    $values_genusontology = [];
    
    foreach($genus_ontology as $genus => $vars) {
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        // Test if each genus has a trait, unit, method, db and crop ontology field.
        $session->fieldExists($fld_name);

        // set to Null (id: 1) all genus ontology configuration.
        $values_genusontology[ $fld_name ] = $null_value;
      }
    }

    // Update default values.
    $this->submitForm($values_genusontology, 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');
    
    foreach(array_keys($values_genusontology) as $fld) {
      // If all config got null set as the value.
      $session->fieldValueEquals($fld, $null_value);
    }
    
    // Allow new trait.
    $allow_new = 'allow_new';
    $session->fieldExists($allow_new);
    // Update allow new.
    $this->submitForm([$allow_new => FALSE], 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');
    $session->fieldValueEquals($allow_new, FALSE);

    // Terms.
    $null_value = 'null (null:local:null)';
    $terms = $service_terms->defineTerms();
    $values_terms = [];

    foreach($terms as $config => $prop) {
      // Test each term has an autocomplete field.
      $session->fieldExists($config);

      $values_terms[ $config ] = $null_value;
    }

    // Update default values.
    $this->submitForm($values_terms, 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');

    foreach(array_keys($values_terms) as $fld) {
      // If all config term got null set as the value.
      $session->fieldValueEquals($fld, $null_value);
    }
  }
}
