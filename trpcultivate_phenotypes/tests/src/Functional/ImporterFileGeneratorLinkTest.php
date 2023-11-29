<?php

/**
 * @file
 * Functional test of Tripal Cultivate Phenotypes Importer 
 * template file generator download link.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Functional;

use Drupal\Tests\tripal_chado\Functional\ChadoTestBrowserBase;

/**
 *  Class definition ImporterFileGeneratorLinkTest.
 */
class ImporterFileGeneratorLinkTest extends ChadoTestBrowserBase {  
  protected $defaultTheme = 'stark';

  // Holds genus - ontology config names.
  private $genus_ontology;

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
    $project_id = $this->chado->insert('1:project')
      ->fields([
        'name' => $project,
        'description' => $project . ' : Description'   
      ])
      ->execute();

    $genus = 'Wild Genus ' . uniqid();
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
    $service_genusproject->setGenusToProject($project_id, $genus, $replace = FALSE);
  }

  /**
   * Test download link.
   */
  public function testImporterFileGeneratorLink() {
    // Setup admin user account.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer tripal',
      'allow tripal import'
    ]);

    // Login admin user.
    $this->drupalLogin($admin_user);
    
    // Assert custom Phenotypes Share importer is an item in
    // admin/tripal/loaders page.
    // Link titled - Tripal Cultivate: Open Science Phenotypic Data
    $this->drupalGet('admin/tripal/loaders/');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    // There is a link to the the importer with this link description.
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
    
    // Configure genus ontology.
    foreach($this->genus_ontology as $genus => $vars) {
      foreach($vars as $i => $config) {
        $fld_name = $genus . '_' . $config;
        $values_genus_ontology[ $fld_name ] = 1;
      }
    }
    
    // Setup genus ontology configuration through the interface.
    $this->drupalGet('admin/tripal/extension/tripal-cultivate/phenotypes/ontology');
    $this->submitForm($values_genus_ontology, 'Save configuration');
    
    // Access Phenotypes Importer Share - this will create a template file.
    $this->drupalGet('admin/tripal/loaders/trpcultivate-phenotypes-share');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->pageTextContains('Tripal Cultivate: Open Science Phenotypic Data');
     
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
    $template_file_uri = $dir_uri . '/' . $template_file;
    
    // Template file is generated.
    $is_file = file_exists($template_file_uri);
    $this->assertTrue($is_file, 'Template generator failed to create a file.');

    // Template is not empty file.
    $this->assertGreaterThanOrEqual(1, filesize($template_file_uri), 'The template file generated is empty.');
    
    // Template file has header row.
    $file_content = file_get_contents($template_file_uri);    
    $this->assertNotNull($file_content, 'Template generator failed to add the header row.');
    
    $this->drupalLogout();
  }
}