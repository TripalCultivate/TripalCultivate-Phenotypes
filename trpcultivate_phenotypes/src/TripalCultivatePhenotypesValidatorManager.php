<?php

/**
 * @file
 * Contains Tripal Cultivate Phenotypes Validator Plugin Manager.
 */

namespace Drupal\trpcultivate_phenotypes;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Validator Plugin Manager.
 */
class TripalCultivatePhenotypesValidatorManager extends DefaultPluginManager {
  /**
   *  Constructs Validator Plugin Manager.
   * 
   *  @param \Traversable $namespaces
   *    An object that implements \Traversable which contains the root paths
   *    keyed by the corresponding namespace to look for plugin implementations.
   *  @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *    Cache backend instance to use.
   *  @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *    The module handler to invoke the alter hook.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Validators', 
      $namespaces, 
      $module_handler, 
      'Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorInterface', 
      'Drupal\trpcultivate_phenotypes\Annotation\TripalCultivatePhenotypesValidator'
    );

    // NOTES:
    // Instance of validator in Drupal/trpcultivate_phenotypes/Plugin/Validator.
    // Each instance is an implementation of Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorInterface.
    // Use annotations defined by Drupal\trpcultivate_phenotypes\Annotation\TripalCultivatePhenotypesValidator.

    // This is the hook name to alter information in this plugin.
    $this->alterInfo('trpcultivate_phenotypes_validators_info');
    $this->setCacheBackend($cache_backend, 'tripalcultivate_phenotypes_validators');
  }

  /**
   * Retrieve validator implementation with a specific scope.
   * 
   * @param string $scope
   *   The validator_scope you are interested in.
   * 
   * @return string
   *   The id of the validator with that scope based on it's annotation.
   */
  public function getValidatorIdWithScope($scope) {
    $plugins = $this->getDefinitions();
    $plugin_definitions = array_values($plugins);

    $plugin_key = array_search(
      $scope, 
      array_column($plugin_definitions, 'validator_scope')
    );

    return $plugin_definitions[ $plugin_key ]['id'];
  }
}