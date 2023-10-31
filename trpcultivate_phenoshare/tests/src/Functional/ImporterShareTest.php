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
  // Project.
  private $project;
  private $project_id;

  // Genus.
  private $genus;
  // Holds genus - ontology config names.
  private $genus_ontology;
  // Admin user created. 
  private $admin_user;

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
  
    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test schema.
    $this->chado = $this->createTestSchema(ChadoTestBrowserBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado);

    // Prepare by adding test records to genus and project.
    $project = 'Project - ' . uniqid();
    $this->project = $project;

    $project_id = $this->chado->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => $project . ' : Description'   
      ])
      ->execute();

    $this->project_id = $project_id;  

    $genus = 'Wild Genus ' . uniqid();
    $this->genus = $genus;
    $this->chado->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => 'Wild Species',
        'type_id' => 1 
      ])
      ->execute();
    
    // Install all default terms.
    $service_terms = \Drupal::service('trpcultivate_phenotypes.terms');
    $service_terms->loadTerms();

    // Define a genus ontology configuration value.
    $service_genusontology = \Drupal::service('trpcultivate_phenotypes.genus_ontology');  
    $service_genusontology->loadGenusOntology();
    $this->genus_ontology = $service_genusontology->defineGenusOntology();

    // Pair the project with the genus.
    $service_genusproject = \Drupal::service('trpcultivate_phenotypes.genus_project');
    $service_genusproject->setGenusToProject($this->project_id, $this->genus, $replace = FALSE);
  }

  /**
   * Test Phenotypes Share Importer.
   */
  public function testImportShareForm() {
    // Setup admin user account.
    $this->admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal',
      'allow tripal import'
    ]);

    // Login admin user.
    $this->drupalLogin($this->admin_user);
    
    foreach($this->genus_ontology as $genus => $vars) {
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        $values_genus_ontology[ $fld_name ] = 1;
      }
    }
    
    // Setup genus ontology configuration through the interface.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/ontology');
    $this->submitForm($values_genus_ontology, 'Save configuration');

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
      if ($i == 2) break; // Skip last stage 3, review stage as the submit will be the import button.
      $next = ($i == 0) ? 'Validate Data File' : 'Check Values';

      if ($i == 0) {
        // Stage 1 prefill project field with the project created in setup.
        $form = ['project' => $this->project];
      }
      else {
        // No form elements to set here.
        $form = [];
      }

      $next = ($i == 0) ? 'Validate Data File' : 'Check Values';
      // Submit form with stage form field values.
      $this->submitForm($form, $next);
      // Get the page content.
      $page_content = $this->getSession()->getPage()->getContent();
    }
    

    // Test if template file generator created a file. This is the link value
    // of the href attribute of the download a template file link in the header notes of the importer.
    
    // Inspect the directory configured for template files in the settings.
    // @see config install and schema.
    $config = \Drupal::config('trpcultivate_phenotypes.settings');
    $dir_template_file = $config->get('trpcultivate.phenotypes.directory.template_file'); 
    $dir_uri = \Drupal::service('file_system')->realpath($dir_template_file);

    // Scan the directory for tsv file.
    // tsv file, parent dir (..) then current dir (.).
    $template_file = scandir($dir_uri, SCANDIR_SORT_DESCENDING)[0];
    
    // Template file is generated.
    $is_file = file_exists($dir_uri . '/' . $template_file);
    $this->assertTrue($is_file, 'Template generator failed to create a file.');

    // Template is not empty file.
    $this->assertGreaterThanOrEqual(1, filesize($dir_uri . '/' . $template_file), 'The template file generated is empty.');
    
    // Template file has header row.
    $file_content = file_get_contents($dir_uri . '/' . $template_file);    
    $this->assertNotNull($file_content, 'Template generator failed to add the header row.');

    $this->drupalLogout();
  }
}