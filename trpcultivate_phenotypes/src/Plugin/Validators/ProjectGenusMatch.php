<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal\Services\TripalTokenParser;
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
   * Tripal Token Parser.
   *
   * @var Drupal\tripal\Services\TripalTokenParser
   */
  protected TripalTokenParser $service_TripalTokenParser;

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
   * @param Drupal\tripal\Services\TripalTokenParser $service_TripalTokenParser
   *   The Tripal token parser service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalTokenParser $service_TripalTokenParser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Genus project service.
    $this->service_PhenoGenusProject = $service_PhenoGenusProject;

    // Tripal token parser service.
    $this->service_TripalTokenParser = $service_TripalTokenParser;
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
      'failedItems' => $failed_items,
    ];
  }

  /**
   * Process failed validation from ProjectGenusMatch into a render array.
   *
   * @param array $failure
   *   An associative array that was returned by the ProjectGenusMatch validator
   *   in the event of failed validation. It contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'project_provided': The name of the project provided.
   *     - 'genus_provided': The name of the genus provided.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   The following tokens can be specfied as keys, with value as the
   *   replacement value for the token. These apply to all failure cases.
   *   - 'project': replaces the word "project".
   *   - 'contact-admin': replaces the phrase
   *     "Please contact your administrator to have this added."
   *   The following token keys will substitute the entire existing case message
   *   with the value of that token.
   *   - 'case-message-1': "Project does not exist"
   *   - 'case-message-2': "Project has no genus set and could not compare with
   *     the genus provided"
   *   - 'case-message-3': "Genus does not match the genus set to the project".
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. Each item in the list contains either the project name or the
   *   genus, whichever one caused the failure.
   *
   * @throws \Exception
   *   - If the validation_result parameter was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processSimpleList(array $failure, array $tokens = []) {

    // @todo Re-add this check when the method is moved to its own Trait
    // Check the format of the validation_result parameter.
    // $this->checkValidationStatusArray($validation_result, 'ProjectGenusMatch');
    // Check for one of the expected cases.
    if ($validation_result['case'] == 'Project does not exist') {
      $message = 'The @project provided does not exist. Please contact your administrator to have this added.';
      $item = $validation_result['failedItems']['project_provided'];
    }
    elseif ($validation_result['case'] == 'Project has no genus set and could not compare with the genus provided') {
      $message = 'The project provided does not have a genus paired to it. Please contact your administrator to have this setup.';
      $item = $validation_result['failedItems']['genus_provided'];
    }
    elseif ($validation_result['case'] == 'Genus does not match the genus set to the project') {
      $message = 'The genus selected does not match the genus set to the project. Please contact your administrator to have this set up.';
      $item = $validation_result['failedItems']['genus_provided'];
    }
    elseif ($validation_result['case'] == 'Project exists and project-genus match the genus provided') {
      throw new \Exception('The case string returned by the ProjectGenusMatch validator implies validation passed, but valid is set to FALSE.');
    }
    else {
      throw new \Exception('The case string returned by the ProjectGenusMatch validator is not recognized as a potential case.');
    }

    // Build the render array.
    $render_array = [
      '#type' => 'item',
      '#title' => $message,
      '#wrapper_attributes' => [
        'class' => [
          'tcp-project-genus-match-failures',
        ],
      ],
      'items' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => [
          [
            '#markup' => $item,
          ],
        ],
      ],
    ];

    return $render_array;
  }

}
