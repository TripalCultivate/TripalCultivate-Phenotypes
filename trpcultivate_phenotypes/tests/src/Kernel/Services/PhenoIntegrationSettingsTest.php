<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Services;

use Drupal\Core\Config\Config;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
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
   * Test pheno integration configuration content types.
   *
   * @var array
   */
  private array $config_set = [
    'backup' => [
      'research_experiment',
      'research_study',
      'project',
    ],
    'pheno_combo' => [
      'research_experiment',
      'trials',
    ],
  ];

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
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Mock getProjectBasedContentTypes() call to protected helper method.
    $getcontent_mock = $this->getMockBuilder(PhenoIntegrationSettings::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('tripal.tripal_entity.lookup'),
      ])
      ->onlyMethods(['getProjectBasedContentTypes'])
      ->getMock();

    $getcontent_mock->method('getProjectBasedContentTypes')->willReturn(
      array_unique(array_merge($this->config_set['backup'], $this->config_set['pheno_combo']))
    );

    $this->container->set($service = 'trpcultivate_phenotypes.pheno_integration', $getcontent_mock);
    $this->pheno_integration = $this->container->get($service);

    // Set test configuration values for every integration.
    $this->config_factory = $this->container->get('config.factory')
      ->getEditable('trpcultivate_phenotypes.settings');

    foreach ($this->config_set as $integration => $content_types) {
      $this->config_factory
        ->set(
          $this->pheno_integration::BASE_CONFIG . '.' . $this->pheno_integration::INTEGRATION_CONFIG[$integration],
          $content_types
        )
        ->save();
    }
  }

  /**
   * Test getPhenoIntegratedContentTypes() method.
   */
  public function testGetPhenoIntegratedContentTypes() {

    // Get all content types for every integration keyed by configuration name.
    $integration_content_types = $this->pheno_integration->getPhenoIntegratedContentTypes();

    foreach ($this->pheno_integration::INTEGRATION_CONFIG as $integration => $config_name) {
      $this->assertArrayHasKey(
        $config_name,
        $integration_content_types,
        'The content types returned by getPhenoIntegratedContentTypes() method failed to contain the configuration key ' . $config_name,
      );

      $this->assertEquals(
        $this->config_set[$integration],
        $integration_content_types[$config_name],
        'The content types returned by getPhenoIntegratedContentTypes() method failed to contain the expected content types.',
      );
    }

    // Get content types in a specific integration.
    foreach (array_keys($this->pheno_integration::INTEGRATION_CONFIG) as $integration) {
      $integration_content_types = $this->pheno_integration->getPhenoIntegratedContentTypes($integration);

      $this->assertEquals(
        $this->config_set[$integration],
        $integration_content_types,
        'Failed to return the expected content types for ' . $integration . ' integration.',
      );
    }
  }

  /**
   * Test setPhenoIntegratedContentTypes() method.
   */
  public function testSetPhenoIntegratedContentTypes() {

    $all_content_types = $this->pheno_integration->getProjectBasedContentTypes();

    $integrations = array_keys($this->pheno_integration::INTEGRATION_CONFIG);
    foreach ($integrations as $integration) {
      // Create a subset of all content types as argument to setter method.
      $test_content_types = array_slice(
        $all_content_types,
        mt_rand(0, $count_types = count($all_content_types) - 1),
        mt_rand(1, $count_types)
      );

      $this->pheno_integration->setPhenoIntegratedContentTypes($integration, $test_content_types);

      $this->assertEquals(
        $test_content_types,
        $this->pheno_integration->getPhenoIntegratedContentTypes($integration),
        'Failed to set the expected content types for integration ' . $integration,
      );
    }
  }

  /**
   * Test isBundleNamePhenoSupported() method.
   */
  public function testIsBundleNamePhenoSupported() {

    $this->assertFalse(
      $this->pheno_integration->isBundleNamePhenoSupported('backup', 'Spurious Content Type'),
      'Unsupported content types is expected to return FALSE by isBundleNamePhenoSupported() method.',
    );

    foreach ($this->config_set as $integration => $content_types) {
      foreach ($content_types as $test_content_type) {
        $this->assertTrue(
          $this->pheno_integration->isBundleNamePhenoSupported($integration, $test_content_type),
          'Supported content types is expected to return TRUE by isBundleNamePhenoSupported() method.',
        );
      }
    }
  }

  /**
   * Test getProjectBasedContentTypes() method.
   */
  public function testGetProjectBasedContentTypes() {

    $this->assertEquals(
      $this->pheno_integration->getProjectBasedContentTypes(),
      array_unique(array_merge($this->config_set['backup'], $this->config_set['pheno_combo']))
    );
  }

  /**
   * Test with exceptions.
   */
  public function testWithExceptions() {

    try {
      $this->pheno_integration->getPhenoIntegratedContentTypes('spurious_integration');
    }
    catch (\Exception $e) {
      $this->assertStringContainsString(
        'Unsupported integration string value error',
        $e->getMessage(),
        'Unsupported integration is expected to trigger an exception.',
      );
    }
  }

}
