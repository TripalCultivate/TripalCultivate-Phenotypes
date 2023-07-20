<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Genus Project service definition.
 */

 namespace Drupal\trpcultivate_phenotypes\Service;

 use \Drupal\Core\Config\ConfigFactoryInterface;
 use \Drupal\tripal_chado\Database\ChadoConnection;
 use \Drupal\tripal\Services\TripalLogger;
 
 /**
 * Class TripalCultivatePhenotypesGenusProjectService.
 */
class TripalCultivatePhenotypesGenusProjectService {
  /**
   * Configuration terms.genus.
   */
  private $sysvar_genus;

  /**
   * Configuration genus.ontology.
   */
  private $sysvar_genusontology;

  /**
   * Chado database and Tripal logger.
   */
  protected $chado;
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
    $this->chado = $chado;

    // Tripal Logger service.
    $this->logger = $logger;
  }
  
  /**
   * Assign a genus to an experiment/project.
   * 
   * @param int $project
   *   Project (project id number) the parameter $genus will be assigned to.
   * @param string $genus
   *   Genus name/title.
   * @param boolean $replace
   *   True to replace existing genus of a project with a different genus.
   *   Default to False.
   *
   * @return boolean
   *   True, genus was set successfully or false on error/fail.
   */
  function setGenusToProject($project, $genus, $replace = FALSE) {
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
        $result = $this->chado->query("
          SELECT projectprop_id AS id FROM {1:projectprop} 
          WHERE project_id = :project_id AND type_id = :type_id LIMIT 1
        ", [':project_id' => $project, ':type_id' => $this->sysvar_genus]);
        
        $projectprop_id = $result->fetchField();

        if ($projectprop_id > 0) {
          // Has a genus.
          if ($replace) {
            // And wishes to replace with another genus.
            $this->chado->query("
              UPDATE {1:projectprop} SET value = :new_genus WHERE projectprop_id = :id
            ", [':new_genus' => $genus, ':id' => $projectprop_id]);
          }

          // Do nothing if maintain the same genus.
        } 
        else {
          // Not set yet, no record in projectprop.
          // Create a relationship regardless to replace or not.
          $sql = "INSERT INTO {1:projectprop} (project_id, type_id, value) VALUES (:project, :config_genus, :genus)";
          $this->chado->query($sql, [':project' => $project, ':config_genus' => $this->sysvar_genus, ':genus' => $genus]);
        }
      }
      else {
        $error = 1;
        $this->logger->error('Error, Genus is not configured. Could not replace genus.' . $g);
      }
    }

    return ($error) ? FALSE : TRUE;
  }

  /**
   * Get genus of an experiment/project.
   * 
   * @param int $project
   *   Project (project_id number) to search.
   *
   * @return array
   *   Key is genus/organism id number and value is the genus name/title.    
   */
  public function getGenusOfProject($project) {
    $genus_project = 0;

    if ($project > 0) {
      $result = $this->chado->query("
        SELECT organism_id AS id, genus FROM {1:organism} 
        WHERE genus = (SELECT value::VARCHAR FROM {1:projectprop} 
          WHERE project_id = :project_id AND type_id = :type_id LIMIT 1)
        LIMIT 1
      ", [':project_id' => $project, ':type_id' => $this->sysvar_genus]);

      $genus_project = $result->fetchObject();
    }

    return ($genus_project) ? ['id' => $genus_project->id, 'genus' => $genus_project->genus] : 0;
  }

  /**
   * Get all genus that have been configured (traits, unit, method, database and crop ontology).
   *
   * @return array
   *   An array of genus names, sorted alphabetically.
   */
  public function getActiveGenus() {
    $genus = [];

    if ($this->sysvar_genusontology) {
      $genus_keys = array_keys($this->sysvar_genusontology);

      foreach($genus_keys as $active_genus) {
        // Each genus-ontology configuration variable name was
        // formatted where all spaces were replaced by underscore.
        // Reconstruct original value.
        $genus[] = ucwords(str_replace('_', ' ', $active_genus));
      }
    }

    return $genus;
  }
}