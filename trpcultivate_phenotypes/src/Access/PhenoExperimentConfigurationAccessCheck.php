<?php

namespace Drupal\trpcultivate_phenotypes\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tripal\Entity\TripalEntity;

/**
 * Checks that only allowed_content_type gets the Phenotypes tab.
 */
class PhenoExperimentConfigurationAccessCheck implements AccessInterface {

  /**
   * Array of Tripal content type bundle name to show Phenotypes tab element.
   *
   * @var array
   */
  private const ALLOWED_CONTENT_TYPE = [
    'research_experiment',
  ];

  /**
   * Access check.
   *
   * @param \Drupal\tripal\Entity\TripalEntity $tripal_entity
   *   Tripal entity.
   * @param Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account. This is the user requesting access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(TripalEntity $tripal_entity, AccountInterface $account) {

    return (in_array($tripal_entity->bundle(), self::ALLOWED_CONTENT_TYPE))
      ? AccessResult::allowed()
      : AccessResult::forbidden();
  }

}
