<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Validate Project.
 * 
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_project",
 *   validator_name = @Translation("Project Validator"),
 *   validator_scope = "PROJECT",
 * )
 */
class Project extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * Genus Project Service.
   */
  protected $service_genus_project;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TripalCultivatePhenotypesGenusProjectService $service_genus_project) { 
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    
    // DI project-related service.
    $this->service_genus_project = $service_genus_project;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.genus_project')
    );
  }

  /**
   * Validate items in the phenotypic data upload assets.
   * 
   * @TODO Structure:
   *    1. Determine service you need + initialize this. Should be added via DI.
   *    2. Massage values from importer to match needs of service.
   *    3. Call service with parameters to get our answer (is valid?)
   *    4. Interpret response and return valid or not.
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validate() {
    // Validate ...
    $validator_status = [
      'title' => 'Project/Experiment Exists',
      'status' => 'pass',
      'details' => ''
    ];

    // Project:
    //   - Is not empty
    //   - Exists in chado.project
    //   - Has genus

    if (empty($this->project)) {
      $validator_status['status']  = 'fail';
      $validator_status['details'] = 'Project/Experiment field is empty. Please enter a value and try again.';
    }
    else {
      // Has a project, check if it existed in chado.projects table.
      $project_id = ChadoProjectAutocompleteController::getProjectId($this->project);
      
      if (!$project_id) {
        $validator_status['status']  = 'fail';
        $validator_status['details'] = 'Project/Experiment does not exist. Please enter a value and try again.';
      }
      else {
        // Check if it has a genus set.
        $genus = $this->service_genus_project->getGenusOfProject($project_id);

        if (!$genus) {
          $validator_status['status']  = 'fail';
          $validator_status['details'] = 'Project/Experiment entered does not have a genus set in the configuration. Please enter a value and try again.';
        }
      }
    }

    return $validator_status;
  }
}