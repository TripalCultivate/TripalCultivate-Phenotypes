<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Form\PhenoExperimentConfigurationForm;

/**
 * Tests associated with PhenoExperimentConfigurationForm class.
 */
class PhenoExperimentConfigurationFormTest extends ChadoTestKernelBase {

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
   * Class instance of experiment configuration form.
   *
   * @var \Drupal\trpcultivate_phenotypes\Form\PhenoExperimentConfigurationForm
   */
  protected $experiment_configuration_form;

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

    $this->container = \Drupal::getContainer();
    $this->experiment_configuration_form = PhenoExperimentConfigurationForm::create($this->container);
  }

  /**
   * Tests the form ID.
   */
  public function testGetFormId() {
    $this->assertEquals('content_bio_data_research_experiment_configure_form', $this->experiment_configuration_form->getFormId());
  }

  /**
   * Tests the buildForm method.
   */
  public function testBuildForm() {

  }

  /**
   * Test validateForm() method.
   */
  public function testValidateForm() {

  }

}
