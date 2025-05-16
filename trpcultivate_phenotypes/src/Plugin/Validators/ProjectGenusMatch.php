<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that project exits and project-genus match the genus provided.
 *
 * @TripalCultivateValidator(
 *   id = "project_genus_match",
 *   validator_name = @Translation("Project Exists and Genus Match Validator"),
 *   input_types = {"metadata"}
 * )
 */
class ProjectGenusMatch extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * Genus Project Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService
   */
  protected TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject;

  /**
   * Constructs an instance of the ProjectGenusMatch validator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   The genus project service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Genus project service.
    $this->service_PhenoGenusProject = $service_PhenoGenusProject;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.genus_project')
    );
  }

  /**
   * Validate that project provided exists and the project-genus set match.
   *
   * @param array $form_values
   *   An array of values from the submitted form where each key maps to a form
   *   element and the value is what the user entered.
   *   Each form element value can be accessed using the field element key
   *   ie. field name/key project - $form_values['project']
   *       field name/key genus - $form_values['genus']
   *
   *   This array is the result of calling $form_state->getValues().
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the project+genus value is valid, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with one or more of the
   *     following keys. This is an empty array if the metadata input was valid.
   *     - 'genus_provided': The name of the genus provided.
   *     - 'project_provided': The name of the project provided.
   */
  public function validateMetadata(array $form_values) {
    // This validator assumes that fields with name/key project and genus were
    // implemented in the Importer form.
    $expected_field_key = [
      'fld_project' => 'project',
      'fld_genus' => 'genus',
    ];

    // Failed to locate the project and genus field element.
    foreach ($expected_field_key as $field) {
      if (!array_key_exists($field, $form_values)) {
        throw new \Exception('Failed to locate ' . $field . ' field element. ProjectGenusMatch validator expects a form field element name ' . $field . '.');
      }
    }

    // Validator response values for a valid project+genus value.
    $case = 'Project exists and project-genus match the genus provided';
    $valid = TRUE;
    $failed_items = [];

    $project = trim($form_values[$expected_field_key['fld_project']]);
    $genus = trim($form_values[$expected_field_key['fld_genus']]);

    // Determine what was provided to the project field: project id or name.
    if (is_numeric($project)) {
      // Value is integer, thus project id was provided.
      // Test project by looking up the id to retrieve the project name.
      $project_rec = ChadoProjectAutocompleteController::getProjectName((int) $project);
      $project_id = $project;
    }
    else {
      // Value is string, thus project name was provided.
      // Test project by looking up the name to retrieve the project id.
      $project_rec = ChadoProjectAutocompleteController::getProjectId($project);
      $project_id = $project_rec;
    }

    if ($project_rec <= 0 || empty($project_rec)) {
      // The project provided, whether the name or project id, does not exist.
      $case = 'Project does not exist';
      $valid = FALSE;
      $failed_items = ['project_provided' => $project];
    }
    else {
      // Inspect which genus is set to the project and see if it matches
      // the genus provided in the genus field.
      $project_genus = $this->service_PhenoGenusProject->getGenusOfProject($project_id);

      if (empty($project_genus)) {
        // Genus does not match the genus paired to the project.
        $case = 'Project has no genus set and could not compare with the genus provided';
        $valid = FALSE;
        $failed_items = ['genus_provided' => $genus];
      }
      else {
        $genus_match = FALSE;

        foreach ($project_genus as $value) {
          $g = (is_array($value)) ? $value['genus'] : $value;

          if ($g == $genus) {
            $genus_match = TRUE;
            break;
          }
        }

        if (!$genus_match) {
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
      'failedItems' => $failed_items,
    ];
  }

}
