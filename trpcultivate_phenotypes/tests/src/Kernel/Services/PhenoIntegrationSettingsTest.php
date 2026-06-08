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

    $this->container->set('trpcultivate_phenotypes.pheno_integration', $getcontent_mock);

    $this->pheno_integration = $this->container->get('trpcultivate_phenotypes.pheno_integration');
    $this->config_factory = $this->container->get('config.factory')->getEditable('trpcultivate_phenotypes.settings');

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

    $all_configs = $this->pheno_integration->getPhenoIntegratedContentTypes();

    foreach ($this->pheno_integration::INTEGRATION_CONFIG as $integration => $config_name) {
      $this->assertArrayHasKey(
        $config_name,
        $all_configs,
        'Failed to return config name ' . $config_name,
      );

      $this->assertEquals(
        $this->config_set[$integration],
        $all_configs[$config_name],
        'Failed to return the expected content types for ' . $integration . ' integration.',
      );
    }

    $integrations = array_keys($this->pheno_integration::INTEGRATION_CONFIG);

    foreach ($integrations as $integration) {
      $config = $this->pheno_integration->getPhenoIntegratedContentTypes($integration);
      $this->assertEquals(
        $this->config_set[$integration],
        $config,
        'Failed to return the expected content types for ' . $integration . ' integration.',
      );
    }
  }

  /**
   * Test setPhenoIntegratedContentTypes() method.
   */
  public function testSetPhenoIntegratedContentTypes() {

    $integrations = array_keys($this->pheno_integration::INTEGRATION_CONFIG);

    foreach ($integrations as $integration) {
      $test_content_types = array_rand($this->config_set['pheno_combo']);
    }
  }

}
