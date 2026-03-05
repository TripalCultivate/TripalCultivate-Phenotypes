<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesOntologySettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
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
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * Class instance of ontology settings form.
   *
   * @var \Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesOntologySettingsForm
   */
  protected $ontology_form;

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

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

    // Insert a genus to the database.
    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species'])
      ->values([
        'genus' => 'Lens',
        'species' => 'databasica',
      ])
      ->execute();

    $this->container = \Drupal::getContainer();

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
      ->willReturn('someValue');

    $this->container->set('trpcultivate_phenotypes.genus_ontology', $mock_ontology_service);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create new instance of the TripalCultivatePhenotypesOntologySettingsForm.
    $this->ontology_form = TripalCultivatePhenotypesOntologySettingsForm::create($this->container);
  }

  /**
   * Tests the buildForm method for the exception cases.
   */
  public function testBuildFormWithExceptions() {
    $form = [];
    $form_state = new FormState();

    $service_terms = $this->container->get('trpcultivate_phenotypes.terms');
    $service_terms->loadTerms();

    // Test for the case where the config value is not an array or null.
    $this->ontology_form->buildForm($form, $form_state);

    $errors = \Drupal::messenger()->messagesByType('error');
    $this->assertCount(1, $errors,
      'We expect an error message when config value for a genus is not an array, but it was not thrown.');

    // Test the case with no genus set.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);
    $this->container = \Drupal::getContainer();
    $this->ontology_form = TripalCultivatePhenotypesOntologySettingsForm::create($this->container);
    $this->ontology_form->buildForm($form, $form_state);

    $warnings = \Drupal::messenger()->messagesByType('warning');
    $this->assertCount(2, $warnings,
      'We expect a warning when no genus is set but it was not given.');
  }

}
