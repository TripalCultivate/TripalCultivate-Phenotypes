<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Handles configuration for phenotypes integration support.
 *
 * This service manages which Tripal entity bundles are enabled for phenotype
 * integrations such as backup and setting up of trait combos.
 */
class PhenoIntegrationSettings {

  /**
   * Base configuration namespace for phenotype-related settings.
   *
   * @var string
   */
  public const BASE_CONFIG = 'trpcultivate.phenotypes';

  /**
   * Integrations to configuration name mapping.
   *
   * @var array
   */
  public const INTEGRATION_CONFIG = [
    'backup' => 'pheno_backup_support',
    'pheno_combo' => 'pheno_combo_support',
  ];

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory interface.
   * @param \Drupal\tripal\Services\TripalEntityLookup $tripal_entity_lookup
   *   Tripal entity lookup service.
   */
  public function __construct(
    protected ConfigFactoryInterface $config_factory,
    protected TripalEntityLookup $tripal_entity_lookup,
  ) {
  }

  /**
   * Sets the content types supporting a specific phenotype integration.
   *
   * @param string $integration
   *   The type of integration content types support.
   *   Must be one of:
   *   - backup: associate a phenotypic data file with a project-based
   *     content type.
   *   - pheno_combo: configure trait-method-unit combinations with a
   *     a project-based content type (e.g. experiment).
   * @param array $content_types
   *   A list of the content_types which support the given integration.
   *
   * @throws \InvalidArgumentException
   *   - If a content type provided is not supported.
   */
  public function setPhenoIntegratedContentTypes(string $integration, array $content_types): void {

    $invalid_types = array_diff($content_types, $bundles = $this->getProjectBasedContentTypes());
    if (!empty($invalid_types)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid content type error. The content type provided [%s], is not supported. Use one or more of [%s].',
          implode(', ', $invalid_types),
          implode(', ', $bundles)
        )
      );
    }

    $this->getPhenoIntegrationEditableConfig($integration)
      ->set(self::BASE_CONFIG . '.' . self::INTEGRATION_CONFIG[$integration], $content_types)
      ->save();
  }

  /**
   * Gets the content types supporting a given integration.
   *
   * @param string|null $integration
   *   @see Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings::setPhenoIntegratedContentTypes()
   *   Default to NULL - return all phenotypes integration configuration values.
   *
   * @return array
   *   List of the content types which support the given integration.
   *   - If a specific integration is provided, the retuned array contains only
   *     the content types that support that integration.
   *   - If the integration not provided (NULL), all integrations are returned,
   *     keyed by their configuration entity names.
   */
  public function getPhenoIntegratedContentTypes(string|null $integration = NULL): array {

    $content_types = [];

    foreach (self::INTEGRATION_CONFIG as $integration_key => $integration_config) {
      if (!is_null($integration) && $integration_key != $integration) {
        continue;
      }

      $content_types[$integration_config] = $this->getPhenoIntegrationEditableConfig($integration_key)
        ->get(self::BASE_CONFIG . '.' . self::INTEGRATION_CONFIG[$integration_key]) ?? [];
    }

    return is_null($integration) ? $content_types : reset($content_types);
  }

  /**
   * Check if a content type supports a specific integration.
   *
   * @param string $integration
   *   @see Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings::setPhenoIntegratedContentTypes()
   * @param string $content_type
   *   The machine name of the tripal_entity_type you want to check for
   *   phenotype integration.
   *
   * @return bool
   *   TRUE if the content type supports this integration and FALSE otherwise.
   */
  public function isBundleNamePhenoSupported(string $integration, string $content_type): bool {

    return in_array($content_type, $this->getPhenoIntegratedContentTypes($integration));
  }

  /**
   * Get project-base Tripal entity content types.
   *
   * @return array
   *   Tripal entity bundle names with Chado.project as the base table.
   */
  public function getProjectBasedContentTypes(): array {

    $base_table_name = 'project';
    return $this->tripal_entity_lookup->getBundles($base_table_name);
  }

  /**
   * Get configuration.
   *
   * @param string $integration
   *   The integration configuration to reference.
   *
   * @return \Drupal\Core\Config\Config
   *   Drupal configuration object.
   *
   * @throws \InvalidArgumentException
   *   - If integration requested is invalid.
   */
  protected function getPhenoIntegrationEditableConfig(string $integration): Config {

    if (!array_key_exists($integration, self::INTEGRATION_CONFIG)) {
      throw new \InvalidArgumentException(
        'Unsupported integration error. Use ' . implode(', ', array_keys(self::INTEGRATION_CONFIG))
      );
    }

    return $this->config_factory->getEditable('trpcultivate_phenotypes.settings');
  }

}
