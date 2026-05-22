<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesOntologySettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for TripalCultivatePhenotypesOntologySettingsForm with exceptions.
 */
#[RunTestsInSeparateProcesses]
class ConfigOntologyTermsFormExceptionsTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
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

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig([
      'tripal_chado',
      'trpcultivate_phenotypes',
      'trpcultivate',
    ]);

    // Mock the getGenusOntologyConfigValues method on genus ontology service
    // so it will return a string instead of null or an array.
    $mock_ontology_service = $this->getMockBuilder(TripalCultivatePhenotypesGenusOntologyService::class)
      ->setConstructorArgs([$this->container->get('config.factory'),
        $this->container->get('tripal_chado.database'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['getGenusOntologyConfigValues'])
      ->getMock();

    $mock_ontology_service->method('getGenusOntologyConfigValues')
      ->willReturn('some_values');

    $this->container->set('trpcultivate_phenotypes.genus_ontology', $mock_ontology_service);

    // Mock the defineTerms method of terms service so that it can determine if
    // genus term has been configured.
    $mock_terms_service = $this->getMockBuilder(TripalCultivatePhenotypesTermsService::class)
      ->setConstructorArgs([$this->container->get('config.factory'),
        $this->container->get('tripal_chado.chado_buddy'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['defineTerms'])
      ->getMock();

    $mock_terms_service->method('defineTerms')
      ->willReturn(['genus' => ['config_map' => 'genus']]);

    $this->container->set('trpcultivate_phenotypes.terms', $mock_terms_service);
  }

  /**
   * Tests the buildForm method for the exception cases.
   */
  public function testBuildFormWithExceptions() {

    $messenger_service = $this->container->get('messenger');

    $form = [];
    $form_state = new FormState();

    // Test for the case where the config value is not an array or null since
    // the host Phenotypes module does not have an genus record.
    TripalCultivatePhenotypesOntologySettingsForm::create($this->container)
      ->buildForm($form, $form_state);

    $warnings = $messenger_service->messagesByType('warning');
    $this->assertCount(1, $warnings,
      'We expect a warning message when config value for a genus is not an array, but it was not thrown.');

    $this->assertEquals(
      'Your Tripal site instance contains 0 organism records in Chado organism table.
        Please create/insert an organism.',
      reset($warnings)->__toString(),
      'The warning message does not match expected message for 0 genus record.',
    );

    $messenger_service->deleteAll();

    // Test case if with organism but terms are not configured error.
    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species'])
      ->values([
        'genus' => 'Lens',
        'species' => 'databasica',
      ])
      ->execute();

    TripalCultivatePhenotypesOntologySettingsForm::create($this->container)
      ->buildForm($form, $form_state);

    $warnings = $messenger_service->messagesByType('warning');
    $this->assertCount(1, $warnings,
      'We expect a warning message when no genus record but it was not given.');

    $this->assertStringContainsString(
      'Tripal Cultivate Phenotypes module requires controlled vocabulary terms and genus records
          used for creating terms and genus-ontology module configuration',
      reset($warnings)->__toString(),
      'The warning message does not match expected message for terms not configured.',
    );
  }

}
