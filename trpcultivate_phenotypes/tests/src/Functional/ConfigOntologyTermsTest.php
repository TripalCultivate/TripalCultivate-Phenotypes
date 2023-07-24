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
   * Chado test schema.
   * 
   * @var object
   */
  protected $chado;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup admin user account.
    $this->admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal'
    ]);

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $this->chado = $this->createTestSchema(ChadoTestBrowserBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);
  }

  /**
   * Test Ontology and Terms configuration page.
   */
  public function testForm() {
    // Login admin user.
    $this->drupalLogin($this->admin_user);

    // Access to configuration page prior to execution of Tripal Job relating
    // to creation of Ontology and Terms will show a warning message.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/ontology');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Warning message');

    // Tripal Jobs to create/insert terms and setup genus ontology configuration
    // are created on install of tripalcultivate_phenotypes. The job may execute or not
    // but this block will create them manually.
    $test_insert_genus = [
      'Lens',
      'Cicer'
    ];

    $this->chado->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $test_insert_genus[0],
        'species' => 'culinaris',
        'type_id'  => 1  
      ])
      ->values([
        'genus' => $test_insert_genus[1],
        'species' => 'arietinum',
        'type_id'  => 1 
      ])
      ->execute();

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
    
    // Find cv id in the test schema to be used as test values.
    $cvs = $this->chado->query("SELECT cv_id FROM {1:cv} LIMIT 10")
      ->fetchAllKeyed(0, 0);

    $test_cv_id = array_keys($cvs);  

    // Find db id in test schema to be used as test values.
    $dbs = $this->chado->query("SELECT db_id FROM {1:db} LIMIT 2")
      ->fetchAllKeyed(0, 0);

    $test_db_id = array_keys($dbs);

    $values_genusontology = [];
    
    $j = 0;
    foreach($genus_ontology as $genus => $vars) {
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        // Test if each genus has a trait, unit, method, db and crop ontology field.
        $session->fieldExists($fld_name);

        if ($config == 'database') {
          $set_val = $test_db_id[ $j ];
          $j++;
        }
        else {
          $set_val = $test_cv_id[ $i ];
        }

        $values_genusontology[ $fld_name ] = $set_val;
      }
    }

    // Update default values.
    $this->submitForm($values_genusontology, 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');
    
    $j = 0;
    foreach($genus_ontology as $genus => $vars) {
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        
        if ($config == 'database') {
          $set_val = $test_db_id[ $j ];
          $j++;
        }
        else {
          $set_val = $test_cv_id[ $i ];
        }

        $session->fieldValueEquals($fld_name, $set_val);
      }
    }
    
    // Allow new trait.
    $allow_new = 'allow_new';
    $session->fieldExists($allow_new);
    // Update allow new.
    $this->submitForm([$allow_new => FALSE], 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');
    $session->fieldValueEquals($allow_new, FALSE);

    // Find cvterms in test schema to be used as test values.
    // Terms will accept term values in cvterm name (database:accession) format.
    $cvterms = $this->chado->query("
      SELECT ct.cvterm_id, CONCAT(ct.name, ' (', db.name, ':', dx.accession, ')')
      FROM {1:cvterm} AS ct LEFT JOIN {1:dbxref} AS dx USING(dbxref_id) LEFT JOIN {1:db} USING(db_id)
      LIMIT 12
    ")
      ->fetchAllKeyed(0, 1);

    $test_cvterms = array_values($cvterms);

    // Terms.
    $terms = $service_terms->defineTerms();
    $values_terms = [];
    
    $i = 0;
    foreach($terms as $config => $prop) {
      // Test each term has an autocomplete field.
      $session->fieldExists($config);

      $values_terms[ $config ] = $test_cvterms[ $i ];
      $i++;
    }

    // Update default values.
    $this->submitForm($values_terms, 'Save configuration');
    $session->pageTextContains('The configuration options have been saved.');

    $i = 0;
    foreach($terms as $config => $prop) {
      $session->fieldValueEquals($config, $test_cvterms[ $i ]);
      $i++;
    }
  }
}
