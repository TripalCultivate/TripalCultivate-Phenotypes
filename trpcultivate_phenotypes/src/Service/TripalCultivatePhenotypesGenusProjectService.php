<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Services\TripalLogger;

/**
 * Phenotypes genus-project service.
 */
class TripalCultivatePhenotypesGenusProjectService {
  /**
   * Configuration terms.genus.
   *
   * @var string
   */
  private $sysvar_genus;

  /**
   * Configuration genus.ontology.
   *
   * @var string
   */
  private $sysvar_genusontology;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Tripal logger service.
   *
   * @var Drupal\tripal\Services\TripalLogger
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ChadoConnection $chado, TripalLogger $logger) {
    // Module Configuration variables.
    $module_settings = 'trpcultivate_phenotypes.settings';
    $config = $config_factory->getEditable($module_settings);

    // Configuration terms.genus.
    $this->sysvar_genus = $config->get('trpcultivate.phenotypes.ontology.terms.genus');
    // Configuration genus.ontology.
    $this->sysvar_genusontology = $config->get('trpcultivate.phenotypes.ontology.cvdbon');

    // Chado database.
    $this->chado_connection = $chado;

    // Tripal Logger service.
    $this->logger = $logger;
  }

  /**
   * Assign a genus to an experiment/project.
   *
   * Each genus-project relationship is an entry in projectprop table.
   *
   * @param int $project
   *   Project (project id number) the parameter $genus will be assigned to.
   * @param string $genus
   *   Genus name/title.
   *
   * @return bool
   *   True, genus was set successfully or false on error/fail.
   */
  public function setGenusToProject($project, $genus) {
    $error = 0;

    if (empty($project) || $project <= 0) {
      $error = 1;
      $this->logger->error('Error, Project id is empty string, 0 or not a positive number. Could not replace genus.');
    }
    elseif (empty($genus)) {
      $error = 1;
      $this->logger->error('Error, Genus is an empty string. Could not replace genus.');
    }
    else {
      // Ensure that only active genus are paired with a project.
      $g = strtolower(str_replace(' ', '_', $genus));
      $is_active_genus = (in_array($g, array_keys($this->sysvar_genusontology))) ? TRUE : FALSE;

      if ($is_active_genus) {
        // Pull all genus assigned to the project.
        $project_genus = $this->chado_connection->select('1:projectprop', 'pp')
          ->fields('pp', ['value', 'rank'])
          ->condition('pp.project_id', $project, '=')
          ->condition('pp.type_id', $this->sysvar_genus, '=')
          ->orderBy('rank', 'DESC')
          ->execute()
          ->fetchAll();

        $project_has_genus = FALSE;

        // Determine if the project already had the genus.
        foreach ($project_genus as $row) {
          if ($row->value == $genus) {
            $project_has_genus = TRUE;
            $break;
          }
        }

        if (!$project_has_genus) {
          $this->chado_connection->insert('1:projectprop')
            ->fields([
              'project_id' => $project,
              'type_id' => $this->sysvar_genus,
              'value' => $genus,
              'rank' => (isset($project_genus[0])) ? $project_genus[0]->rank + 1 : 1,
            ])
            ->execute();
        }
      }
      else {
        $error = 1;
        $this->logger->error('Error, Genus is not configured. Could not replace genus.');
      }
    }

    return ($error) ? FALSE : TRUE;
  }

  /**
   * Get all genus assigned to a project.
   *
   * @param int $project
   *   Project (project_id number) to search.
   *
   * @return array
   *   An array of all the genus assigned to a project.
   */
  public function getGenusOfProject($project) {
    $genus_project = [];

    if ($project > 0) {
      $sysvar_genus = array_keys($this->sysvar_genusontology);
      $active_genus = array_map(function ($g) {
        return strtolower(str_replace('_', ' ', $g));
      }, $sysvar_genus);

      // Fetch genus paired to a project. If multiple genus have been set prior,
      // restrict search to genus that are active/configured using this module.
      $result = $this->chado_connection->select('1:projectprop', 'pp')
        ->fields('pp', ['value'])
        ->condition('pp.project_id', $project, '=')
        ->condition('pp.type_id', $this->sysvar_genus, '=')
        ->where('LOWER(pp.value) IN (:active_genus[])', [':active_genus[]' => $active_genus])
        ->orderBy('value', 'ASC')
        ->execute();

      $genus_project = $result->fetchCol();
    }

    return ($genus_project) ? $genus_project : [];
  }

}
