<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Validate Genus.
 * 
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_genus",
 *   validator_name = @Translation("Genus Validator"),
 *   validator_scope = "GENUS",
 * )
 */
class Genus extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * Genus Project Service.
   */
  protected $service_genus_project;

  /**
   * Genus Ontology Service;
   */
  protected $service_genus_ontology;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, 
    TripalCultivatePhenotypesGenusProjectService $service_genus_project,
    TripalCultivatePhenotypesGenusOntologyService $service_genus_ontology) { 
    
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    
    $this->service_genus_project = $service_genus_project;
    $this->service_genus_ontology = $service_genus_ontology;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.genus_project'),
      $container->get('trpcultivate_phenotypes.genus_ontology')
    );
  }

  /**
   * Validate items in the phenotypic data upload assets.
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
      'title' => 'Genus Exists and/or Matches the Project/Experiment',
      'status' => 'pass',
      'details' => ''
    ];

    // Genus:
    //   - Is not empty
    //   - Is active, therefore it exits in chado.organism.
    //   - Matches the set genus for the project

    if (empty($this->genus)) {
      $validator_status['status']  = 'fail';
      $validator_status['details'] = 'Genus field is empty. Please enter a value and try again.';
    }
    else {
      // Test if the genus is an active genus. This will at the same time 
      // confirm that it exists in organism table.
      $genus_config = $this->service_genus_ontology->getGenusOntologyConfigValues($this->genus);
      if (!$genus_config) {
        // Genus is not recognized by the module.
        $validator_status['status']  = 'fail';
        $validator_status['details'] = 'Genus does not exist. Please enter a value and try again.';
      }
      else {
        if ($genus_config['trait'] <= 0) {
          // Genus exits, but not configured.
          $validator_status['status']  = 'fail';
          $validator_status['details'] = 'Genus is not configured. Please enter a value and try again.';
        }        
      }

      // Additional check if genus is pared with project, ensure that
      // it is the genus the project is set to.
      if (isset($this->project) && $this->project > 0) {
        // Check if it has a genus set.
        $project_id = ChadoProjectAutocompleteController::getProjectId($this->project);
        $genus = $this->service_genus_project->getGenusOfProject($project_id);

        if ($genus && $this->genus != $genus['genus']) {
          $validator_status['status']  = 'fail';
          $validator_status['details'] = 'Genus entered does not match the genus set for the Project/Experiment. Please enter a value and try again.';
        }
      }
    }
    
    return $validator_status;
  }
}