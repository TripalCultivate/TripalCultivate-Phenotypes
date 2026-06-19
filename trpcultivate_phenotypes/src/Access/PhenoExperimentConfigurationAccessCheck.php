<?php

namespace Drupal\trpcultivate_phenotypes\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings;
use Symfony\Component\Routing\Route;

/**
 * Checks that only allowed_content_type gets the Phenotypes tab.
 */
class PhenoExperimentConfigurationAccessCheck implements AccessInterface {

  /**
   * Class constructor.
   *
   * @param \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings $service_PhenoIntegration
   *   Phenotypes Integration Settings service.
   */
  public function __construct(protected PhenoIntegrationSettings $service_PhenoIntegration) {

  }

  /**
   * Phenotypes integration page access check.
   *
   * Validates access to a project-based Tripal entity page based on the
   * integration defined by the route. In each integration - 'backup' or
   * 'pheno_combo', defines a list of supported content types. The route
   * specifies which integration applies and access check ensures that the
   * entity being viewed belongs to one of the content types supported for that
   * integration.
   *
   * Access is only granted when:
   *   1. The route defines _pheno_experiment_configuration_access_check: true
   *   2. The route indicates an integration of either 'backup' or 'pheno_combo'
   *      using the 'integration' custom key in route 'requirements' property.
   *   3. Tripal entity bundle is a supported content type of an integration.
   *
   * For example, the route configuration below invokes permission check and
   * verifies the page entity content type against 'backup' integration
   * supported content types.
   *
   * @code
   * # routing.yml
   * example.route:
   *   path: '/phenotypes/...'
   *   requirements:
   *     _pheno_experiment_configuration_access_check: true
   *     integration: 'backup'
   * @endcode
   *
   * @param \Drupal\tripal\Entity\TripalEntity $tripal_entity
   *   Tripal entity.
   * @param \Symfony\Component\Routing\Route $route
   *   The routing definition.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(TripalEntity $tripal_entity, Route $route) {

    // Reference the integtation key in the route requirements property.
    $route_integration = $route->getRequirement('integration');

    $valid_integrations = array_keys($this->service_PhenoIntegration::INTEGRATION_CONFIG_MAP);
    // The integration defined in the route requirements is not supported.
    if (!in_array($route_integration, $valid_integrations)) {
      return AccessResult::forbidden();
    }

    // Get the integration configured supported content types.
    $integration_content_types = $this->service_PhenoIntegration
      ->getPhenoIntegratedContentTypes($route_integration);

    // The entity content type is not configured for phentypes functionality.
    if (!in_array($tripal_entity->bundle(), $integration_content_types)) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
