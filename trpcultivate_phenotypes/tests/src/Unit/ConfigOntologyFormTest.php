<?php

/**
 * @file
 * Unit test of Ontology configuration page.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Unit;

use Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesOntologySettingsForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;


use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesVocabularyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesDatabaseService;

use Drupal\Core\Form;
/**
  *  Class definition ConfigOntologyFormTest.
  *
  * @coversDefaultClass Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesOntologySettingsForm
  * @group trpcultivate_phenotypes
  */
class ConfigOntologyFormTest extends UnitTestCase {
  
  /**
   * Initialization of container, configurations, service 
   * and service class required by the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock config since the Ontology form extends configFormBase and expects
    // a configuration settings. Called in validateForm().
    $ontology_config_mock = $this->prophesize(Config::class);
    $ontology_config_mock->get('trpcultivate.phenotypes.cvdbon')
      ->willReturn(null);
   
    // When ontology form rebuilds calling the module settings, return
    // only the ontology configuration settings above exclude other config.
    $all_config_mock = $this->prophesize(ConfigFactoryInterface::class);
    $all_config_mock->getEditable('trpcultivate_phenotypes.settings')
      ->willReturn($ontology_config_mock);

    // Isolated configuration for ontology configuration.
    $ontology_config = $all_config_mock->reveal();

    // Translation requirement of the container
    $translation_mock = $this->prophesize(TranslationInterface::class);
    $translation = $translation_mock->reveal();

    // Services:
    $srv_database = $this->prophesize(TripalCultivatePhenotypesDatabaseService::class)->reveal();    
    $srv_ontology = $this->prophesize(TripalCultivatePhenotypesOntologyService::class)->reveal();
    $srv_terms = $this->prophesize(TripalCultivatePhenotypesTermsService::class)->reveal();
    $srv_vocabulary = $this->prophesize(TripalCultivatePhenotypesVocabularyService::class)->reveal();

    $ontology_form = new TripalCultivatePhenotypesOntologySettingsForm($ontology_config, $srv_database, $srv_ontology, $srv_terms, $srv_vocabulary);
    $ontology_form->setStringTranslation($translation);

    $container = new ContainerBuilder();
    $container->set('config.factory', $ontology_config);
    $container->set('trpcultivate_phenotypes.database', '');
    $container->set('trpcultivate_phenotypes.ontology', '');
    $container->set('trpcultivate_phenotypes.terms', '');
    $container->set('trpcultivate_phenotypes.vocabulary', '');
    $container->set('ontology_form', $ontology_form);
    \Drupal::setContainer($container);
  }

  /**
   * Test validate functionality of OntologyForm class.
   */  
  public function testValidateForm() {
    $ontology_form = \Drupal::service('ontology_form');
    // Class created.
    $this->assertNotNull($ontology_form);
    // Test if it is the ontology config form, using the form id.
    $this->assertEquals('trpcultivate_phenotypes_ontology_settings_form', $ontology_form->getFormId());
  } 
}