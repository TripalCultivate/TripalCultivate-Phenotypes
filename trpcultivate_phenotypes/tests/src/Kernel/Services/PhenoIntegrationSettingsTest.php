<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\Config;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test PhenoIntegrationSettings service.
 *
 * @group trpcultivate_phenotypes
 */
#[Group('trpcultivate_phenotypes')]
#[RunTestsInSeparateProcesses]
class PhenoIntegrationSettingsTest extends ChadoTestKernelBase {

  /**
   * The Phenotypes Integration service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings
   */
  protected PhenoIntegrationSettings $pheno_integration;

  /**
   * Drupal configuration factory service.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config_factory;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Phenotypes integration configuration content type test values.
   *
   * @var array
   */
  protected array $integration_test_values = [
    'backup' => [
      'research_experiment',
      'grant_section',
      'research_grant',
    ],
    'pheno_combo' => [
      'research_experiment',
      'research_study',
    ],
  ];

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
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig(['tripal_chado', 'trpcultivate', 'trpcultivate_phenotypes']);

    $this->container->get('trpcultivate.setup_module_service')
      ->installTerms();

    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    $this->container->get('trpcultivate.setup_module_service')
      ->importContenttypes();

    $this->pheno_integration = $this->container
      ->get('trpcultivate_phenotypes.pheno_integration');

    // Set test configuration values for every integration.
    $this->config_factory = $this->container->get('config.factory')
      ->getEditable($this->pheno_integration::PHENO_CONFIG);

    foreach ($this->integration_test_values as $integration => $content_types) {
      $this->config_factory
        ->set(
          $this->pheno_integration::BASE_CONFIG . '.' . $this->pheno_integration::INTEGRATION_CONFIG_MAP[$integration],
          $content_types
        );
    }

    $this->config_factory->save();
  }

  /**
   * Test getPhenoIntegratedContentTypes() method.
   */
  public function testGetPhenoIntegratedContentTypes() {

    // Get content types of a each integration.
    foreach ($this->pheno_integration->getPhenoIntegrations() as $integration) {
      $integration_content_types = $this->pheno_integration
        ->getPhenoIntegratedContentTypes($integration);

      $this->assertEquals(
        $this->integration_test_values[$integration],
        $integration_content_types,
        'The method getPhenoIntegratedContentTypes() failed to return expected content types for integration ' . $integration,
      );
    }
  }

  /**
   * Test setPhenoIntegratedContentTypes() method.
   */
  public function testSetPhenoIntegratedContentTypes() {

    $supported_content_types = array_keys(
      $this->pheno_integration->getProjectBasedContentTypes()
    );

    foreach ($this->pheno_integration->getPhenoIntegrations() as $integration) {
      // Create a subset of all content types as argument to the setter method.
      $test_content_type = array_slice(
        $supported_content_types,
        mt_rand(0, $count_types = count($supported_content_types) - 1),
        mt_rand(1, $count_types)
      );

      $this->pheno_integration->setPhenoIntegratedContentTypes($integration, $test_content_type);

      $this->assertEquals(
        $test_content_type,
        $this->pheno_integration->getPhenoIntegratedContentTypes($integration),
        'The method setPhenoIntegratedContentTypes() failed to set the expected content types for integration ' . $integration,
      );
    }
  }

  /**
   * Test isIsContentTypePhenoSupported() method.
   */
  public function testIsContentTypePhenoSupported() {

    foreach ($this->integration_test_values as $integration => $content_types) {
      foreach ($content_types as $test_content_type) {
        $this->assertTrue(
          $this->pheno_integration->isContentTypePhenoSupported($integration, $test_content_type),
          'Supported content types is expected to return TRUE by isContentTypePhenoSupported() method.',
        );
      }
    }

    // Content type research_study is not in the list of backup integrations.
    $this->assertFalse(
      $this->pheno_integration->isContentTypePhenoSupported('backup', 'research_study'),
      'Unsupported content types is expected to return FALSE by isContentTypePhenoSupported() method.',
    );
  }

  /**
   * Test getProjectBasedContentTypes() method.
   */
  public function testGetProjectBasedContentTypes() {

    $project_content_types = $this->container->get('tripal.tripal_entity.lookup')
      ->getBundles('project');

    $service_content_types = $this->pheno_integration->getProjectBasedContentTypes();

    $this->assertEquals(
      $project_content_types,
      array_keys($service_content_types),
      'The list of project-based bundles returned by getProjectBasedContentTypes() does not match expected list of content types',
    );

    // Assertions related to machine name converted to human-readable text.
    foreach ($project_content_types as $content_type) {
      $this->assertStringNotContainsString(
        '_',
        $service_content_types[$content_type],
        'The human-readable string does not match expected string of content type ' . $content_type,
      );
    }
  }

  /**
   * Test integration validator.
   */
  public function testIntegrationValidator() {

    $pheno_integrations = $this->pheno_integration->getPhenoIntegrations();
    array_push($pheno_integrations, 'spurious_integration');

    foreach ($pheno_integrations as $integration) {
      try {
        $this->pheno_integration->validateIntegration($integration);
        $this->assertTrue(TRUE, 'No exception thrown for valid integration ' . $integration);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Unsupported integration error',
          $e->getMessage(),
          'Unsupported integration is expected to trigger an exception in ' . $integration,
        );
      }
    }
  }

  /**
   * Test content type validator.
   */
  public function testContentTypeValidator() {

    $test_content_types = array_keys($this->pheno_integration->getProjectBasedContentTypes());
    // Include single value and an array of values.
    array_push($test_content_types, 'spurious_content_type', ['bad_type', 'wrong_type']);

    foreach ($test_content_types as $content_type) {
      try {
        $this->pheno_integration->validateContentType($content_type);
      }
      catch (\Exception $e) {
        $this->assertStringContainsString(
          'Invalid content type error',
          $e->getMessage(),
          'Unsupported content type is expected to trigger an exception.',
        );
      }
    }
  }

  /**
   * Test getPhenoIntegrations() method.
   */
  public function testGetPhenoIntegrations() {

    $this->assertEquals(
      array_keys($this->pheno_integration::INTEGRATION_CONFIG_MAP),
      $this->pheno_integration->getPhenoIntegrations(),
      'getPhenoIntegrations() failed to return the expected list of phenotypes integrations.',
    );
  }

}
