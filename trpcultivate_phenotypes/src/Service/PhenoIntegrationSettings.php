<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tripal\Services\TripalEntityLookup;

/**
 * Handles configuration for phenotypes integration support.
 *
 * This service manages which content types are enabled for phenotype
 * data file backup and setting up of trait-combo integrations.
 */
class PhenoIntegrationSettings {

  /**
   * Phenotypes module configuration.
   *
   * @var string
   */
  public const PHENO_CONFIG = 'trpcultivate_phenotypes.settings';

  /**
   * Base configuration namespace for phenotype-related settings.
   *
   * @var string
   */
  public const BASE_CONFIG = 'trpcultivate.phenotypes';

  /**
   * Maps integration keys to configuration names.
   *
   * @var array
   * @see config/install/schema/trpcultivate_phenotypes.schema.yml
   */
  public const INTEGRATION_CONFIG_MAP = [
    'backup' => 'pheno_backup_support',
    'pheno_combo' => 'pheno_combo_support',
  ];

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $service_EntityTypeManager
   *   Drupal Entity Type Manager service.
   * @param \Drupal\tripal\Services\TripalEntityLookup $tripal_entity_lookup
   *   Tripal entity lookup service.
   */
  public function __construct(
    protected ConfigFactoryInterface $config_factory,
    protected EntityTypeManagerInterface $service_EntityTypeManager,
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
   *   A list of the content types which support the given integration.
   */
  public function setPhenoIntegratedContentTypes(string $integration, array $content_types): void {

    $this->validateIntegration($integration);
    $this->validateContentType($content_types);

    $this->config_factory->getEditable(self::PHENO_CONFIG)
      ->set(self::BASE_CONFIG . '.' . self::INTEGRATION_CONFIG_MAP[$integration], $content_types)
      ->save();
  }

  /**
   * Gets the content types supporting a given integration.
   *
   * @param string|null $integration
   *   @see Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings::setPhenoIntegratedContentTypes()
   *
   * @return array
   *   A list of the content types which support the given integration.
   */
  public function getPhenoIntegratedContentTypes(string $integration): array {

    $this->validateIntegration($integration);

    return $this->config_factory->get(self::PHENO_CONFIG)
      ->get(self::BASE_CONFIG . '.' . self::INTEGRATION_CONFIG_MAP[$integration]);
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
  public function isContentTypePhenoSupported(string $integration, string $content_type): bool {

    $this->validateIntegration($integration);
    $this->validateContentType($content_type);

    return in_array($content_type, $this->getPhenoIntegratedContentTypes($integration));
  }

  /**
   * Get project-base content types.
   *
   * @return array
   *   A list of content types with Chado.project as the base table. Each type
   *   in the array is keyed by the unique machine name and the value is a
   *   human-readable text (the machine name converted from snake_case).
   */
  public function getProjectBasedContentTypes(): array {

    $project_content_types = $this->tripal_entity_lookup->getBundles(
      $base_table_name = 'project'
    );

    $supported_content_types = [];
    foreach ($project_content_types as $content_type) {
      $supported_content_types[$content_type] = Unicode::ucwords(str_replace('_', ' ', $content_type));
    }

    return $supported_content_types;
  }

  /**
   * Validate integration.
   *
   * @param string|null $integration
   *   @see Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings::setPhenoIntegratedContentTypes()
   *
   * @throws \InvalidArgumentException
   *   - If integration requested is unsupported.
   */
  public function validateIntegration(string $integration): void {

    if (!isset(self::INTEGRATION_CONFIG_MAP[$integration])) {
      throw new \InvalidArgumentException(
        sprintf(
          'Unsupported integration error. Use one of [%s] as integration value.',
          implode(', ', array_keys(self::INTEGRATION_CONFIG_MAP))
        )
      );
    }
  }

  /**
   * Validate content type.
   *
   * Checks that content types provided is project-based.
   *
   * @param array|string $content_type
   *   An list of content types (array) or a single content type to
   *   validate (string).
   *
   * @throws \InvalidArgumentException
   *   - If content type provided is not project-based.
   */
  public function validateContentType(array|string $content_type): void {

    $valid_content_types = array_keys($this->getProjectBasedContentTypes());
    $invalid_content_types = array_diff((array) $content_type, $valid_content_types);

    if (!empty($invalid_content_types)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Invalid content type error. The content type provided [%s], is not supported. Use one or more of [%s].',
          implode(', ', $invalid_content_types),
          implode(', ', $valid_content_types)
        )
      );
    }
  }

}
