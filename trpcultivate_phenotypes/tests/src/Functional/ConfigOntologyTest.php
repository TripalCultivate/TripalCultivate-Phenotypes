<?php

/**
 * @file
 * Functional test of Ontology/Terms configuration page.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Functional;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Class definition ConfigOntologyTest.
 *
 * @group trpcultivate_phenotypes_config_ontology
 */
class ConfigOntologyTest extends BrowserTestBase {
  /**
   * Modules to enabled
   * 
   * @var array
   */
  protected static $modules = ['trpcultivate_phenotypes'];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with admin privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin_user;

  //protected $strictConfigSchema = FALSE;
  /**
   * Test watermark configuration page.
   * 
   * NOTE: unable to test file upload field, This test only when
   * choosing not to watermark any charts.
   * 
   * @see unit test and kernel test of this form where file field
   * and upload are tested.
   */
  public function testForm() {
    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $srv_terms = \Drupal::service('trpcultivate_phenotypes.terms');
    $srv_ontology = \Drupal::service('trpcultivate_phenotypes.ontology');

    // Setup admin user account.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal'
    ]);
    
    // Login admin user.
    $this->drupalLogin($admin_user);

    // Access ontology configuration page.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/ontology');
    $session = $this->assertSession();

    $session->statusCodeEquals(200);
    $session->pageTextContains('Configure Tripal Cultivate Phenotypes: Ontology Terms');
    
    // TERMS:
    // Install sequence set all terms used by this module to default 
    // term values. This test will set all said values to null.
    // null has cvterm_id of 1.
    $null_value = 1;

    $terms = $srv_terms->mapDefaultTermToConfig();
    $term_form_values = [];

    // Imitate value returned by autocomplete field in the following
    // format Term name (Db name:Dbxref accession).
    $null_term = $srv_terms->getTerm($null_value);
    $format_null = $null_term['format'];
    
    // Set the fields.
    foreach($terms as $term => $config) {
      $term_form_values[ $config ] = $format_null;
    }

    $this->submitForm($term_form_values, 'Save configuration');    
    
    // Test if all terms got null-ed.
    foreach($terms as $term => $config) {
      $this->assertSession()->fieldValueEquals($config, $format_null);
    }

    // ALLOW NEW TRAIT:
    // Allow new traits to be added during the upload process.
    // By default, allow new trait is set to TRUE - allow.
    $allow_new = 'allow_new';
    $this->submitForm([$allow_new => FALSE], 'Save configuration');    
    $this->assertSession()->fieldValueEquals($allow_new, FALSE);
    

    // GENUS-ONTOLOGY:
    // Create a genus and set the cv for trait, method and unit to null,
    // database to null and finally, crop ontology to null as well.
    
    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);
    $chado = \Drupal::service('tripal_chado.database');
    
    // Lens culinaris, null type.
    $insert = "INSERT INTO {1:organism} (genus, species, type_id) 
      VALUES ('Lens', 'culinaris', 1)";
    
    $chado->query($insert);

    $lens = $chado->query("SELECT organism_id FROM {1:organism} WHERE genus = 'Lens' LIMIT 1")
      ->fetchField();
    
    // Install this organism - prepare a genus-ontology configuration.
    $srv_ontology->loadGenusOntology();

    // Inspect if genus-ontology configuration was set.
    $genus_ontology = $srv_ontology->getGenusOntologyConfigValue('Lens');
    unset($config);
    foreach($genus_ontology as $config => $value) {
      $this->assertEquals($value, 0);
    }

    // Set the value to some cvs and some dbs of genus - Lens.
    $ontology_form_values = [
      'lens_trait' => $null_value,         // Null, no vocabulary cv
      'lens_method' => $null_value,        // Null, no vocabulary cv
      'lens_unit'    =>  $null_value,      // Null, no vocabulary cv
      'lens_database' => 1,                // Null, no database
      'lens_crop_ontology' => $null_value  // Null, no vocabulary cv
    ];

    $this->submitForm($ontology_form_values, 'Save configuration');    
    
    unset($config);
    foreach($genus_ontology as $config => $value) {
      // All genus ontology config set to Null cv/db.
      $this->assertSession()->fieldValueEquals('lens_' . $config, $null_value);
    }

    $this->drupalLogout();
  }
}