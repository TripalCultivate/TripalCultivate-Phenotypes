<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that project exits and project-genus match the genus provided.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_project_genus_match",
 *   validator_name = @Translation("Project Exists and Genus Match Validator"),
 *   input_types = {"metadata"}
 * )
 */
class projectGenusMatch extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * Genus Project Service.
   *
   * @var TripalCultivatePhenotypesGenusProjectService
   */
  protected TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, TripalCultivatePhenotypesGenusProjectService $service_genusproject) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Genus project service.
    $this->service_PhenoGenusProject = $service_genusproject;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, string $plugin_id, array $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.genus_project')
    );
  }

  /**
   * Validate that project provided exists and the project-genus set match
   * the genus provided in the genus field.
   *
   * @param array $form_values
   *   The values entered to any form field elements implemented by the importer.
   *   Each form element value can be accessed using the field element key
   *   ie. field name/key project - $form_values['project']
   *       field name/key genus - $form_values['genus']
   *
   *   This array is the result of calling $form_state->getValues().
   *
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the project+genus value is valid or not.
   *     - failedItems: an array of "items" that failed to be used in the message to the user. This is an empty array if the metadata input was valid.
   */
  public function validateMetadata(array $form_values) {
    // This project genus match validator assumes that fields with name/key project and genus were
    // implemented in the Importer form.
    $expected_field_key = [
      'fld_project' => 'project',
      'fld_genus' => 'genus'
    ];

    // Parameter passed to the method is not an array.
    if (!is_array($form_values)) {
      $type = gettype($form_values);
      throw new \Exception('Unexpected ' . $type . ' type was passed as parameter to projectGenusMatch validator.');
    }

    // Failed to locate the project and genus field element.
    foreach($expected_field_key as $field) {
      if (!array_key_exists($field, $form_values)) {
        throw new \Exception('Failed to locate ' . $field . ' field element. projectGenusMatch validator expects a form field element name ' . $field . '.');
      }
    }

    // Validator response values for a valid project+genus value.
    $case = 'Project exists and project-genus match the genus provided';
    $valid = TRUE;
    $failed_items = [];

    // Project.
    $project = trim($form_values[ $expected_field_key['fld_project'] ]);
    // Genus.
    $genus = trim($form_values[ $expected_field_key['fld_genus'] ]);


    // Determine what was provided to the project field: project id or name.
    if (is_numeric($project)) {
      // Value is integer. Project id was provided.
      // Test project by looking up the id to retrieve the project name.
      $project_rec = ChadoProjectAutocompleteController::getProjectName((int) $project);
      $project_id = $project;
    }
    else {
      // Value is string. Project name was provided.
      // Test project by looking up the name to retrieve the project id.
      $project_rec = ChadoProjectAutocompleteController::getProjectId($project);
      $project_id = $project_rec;
    }

    if ($project_rec <= 0 || empty($project_rec)) {
      // The project provided whether the name or project id, does not exist.
      $case = 'Project does not exist';
      $valid = FALSE;
      $failed_items = ['project_provided' => $project];
    }
    else {
      // Inspect the set genus to the project and see if it matches
      // the genus provided in the genus field.
      $project_genus = $this->service_PhenoGenusProject->getGenusOfProject($project_id);

      if (!isset($project_genus['genus'])) {
        // Genus does not match the genus paired to the project.
        $case = 'Project has no genus set and could not compare with the genus provided';
        $valid = FALSE;
        $failed_items = ['genus_provided' => $genus];
      }
      else {
        if ($genus != $project_genus['genus']) {
          // Genus does not match the genus paired to the project.
          $case = 'Genus does not match the genus set to the project';
          $valid = FALSE;
          $failed_items = ['genus_provided' => $genus];
        }
      }
    }

    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}
