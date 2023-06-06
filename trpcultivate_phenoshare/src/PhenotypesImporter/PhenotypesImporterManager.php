<?php

namespace Drupal\trpcultivate_phenoshare\PhenotypesImporter;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides Tripal Cultivate Phenotypes Plugin Manager.
 */
class PhenotypesImporterManager extends DefaultPluginManager {
  public function __construct(
    \Traversable $namespaces
    ,CacheBackendInterface $cache_backend
    ,ModuleHandlerInterface $module_handler) {

    parent::__construct(
      'Plugin/PhenotypesImporter',
      $namespaces,
      $module_handler,
      'Drupal\trpcultivate_phenoshare\PhenotypesImporter\PhenotypesImporterInterface',
      'Drupla\trpcultivate_phenoshare\Annotation\PhenotypesImporter'
    );
    
    $this->alterInfo('phenotypes_importer_info');
    $this->setCacheBackend($cache_backend, 'phenotypes_importer_plugins');
  }

  public function setParameters(array $options) {
  }
}