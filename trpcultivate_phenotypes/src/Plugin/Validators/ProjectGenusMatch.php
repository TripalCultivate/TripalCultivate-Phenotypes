<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\trpcultivate\Service\ImportValidationHelper;
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
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *
   * @todo Move this generic documentation to ValidatorBase in TripalCultivate.
   * This mapping starts with all of the potential cases for this validator,
   * followed by additional tokens which are subsitutable within the message(s)
   * provided to the user when validation fails.
   *
   * For each case, the array keys are the substitutable tokens for the entire
   * case message, and MUST contain the prefix of 'case-', and contain the
   * following key-value pairs:
   * - 'token': the same token (same as the parent key- this can helpful for
   *   code readability). Recall that it must contain the prefex 'case-'.
   * - 'dev-case': The short, developer-focussed string describing the case.
   * - 'default-msg': An informative message that gets displayed to the user
   *   when validation fails for this particular case. This can contain any
   *   number of smaller, non case-specific tokens contained in square brackets.
   * NOTE: The token 'case-valid' is reserved for the valid case for this
   * validator, and does not have a corresponding default message.
   *
   * For all remaining tokens, the array key is the substitutable token, with
   * the following key-pairs:
   * - 'token': the substitutable text in a message. This text would become
   *   flanked by brackets within a message. For eg. [token]
   * - 'default-msg': A string that would substitute the associated token
   *   within a case message.
   *
   *  The following tokens are implemented for this mapping, with the following
   *  descriptions for their 'default-msg' values:
   *  - 'project': the word to use when referring to the project.
   *  - 'contact-admin': the phrase to use when the user needs a privileged
   *    administrator to fix the problem.
   *  - 'case-no-project': the message when a project does not exist.
   *  - 'case-no-paired-genus': the message when a project has no genus set to
   *    it.
   *  - 'case-project-genus-mismatch': the message when the genus selected by
   *    the user is not configured to the selected project.
   */
  protected static array $mapping = [
    'case-no-project' => [
      'token' => 'case-no-project',
      'dev-case' => 'Project does not exist',
      'default-msg' => 'The selected [project] does not exist. Please [contact-admin] to have this added.',
    ],
    'case-no-paired-genus' => [
      'token' => 'case-no-paired-genus',
      'dev-case' => 'Project has no genus set and could not compare with the genus provided',
      'default-msg' => 'The selected [project] does not have a genus paired to it. Please [contact-admin] to have this set up.',
    ],
    'case-project-genus-mismatch' => [
      'token' => 'case-project-genus-mismatch',
      'dev-case' => 'Genus does not match a genus set to the project',
      'default-msg' => 'The selected genus has not been paired to the selected [project]. Please select a paired genus or [contact-admin] if you think one is missing.',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Project exists and project-genus match the genus provided',
    ],
    'project' => [
      'token' => 'project',
      'default-msg' => 'Project',
    ],
    'contact-admin' => [
      'token' => 'contact-admin',
      'default-msg' => 'contact your administrator',
    ],
  ];

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
      $container->get('trpcultivate_phenotypes.genus_project'),
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
        // The project has no genus paired to it.
        $case = 'Project has no genus set and could not compare with the genus provided';
        $valid = FALSE;
        $failed_items = [
          'project_provided' => $project,
          'genus_provided' => $genus,
        ];
      }
      else {
        if ($genus != $project_genus['genus']) {
          // This genus is not paired to the project.
          $case = 'Genus does not match a genus set to the project';
          $valid = FALSE;
          $failed_items = [
            'project_provided' => $project,
            'genus_provided' => $genus,
          ];
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
   *   @see ProjectGenusMatch::$default_tokens
   *   The following tokens can be specfied as keys, with value as the
   *   replacement value for the token. These apply to all failure cases.
   *   - 'project': the word to use when referring to the project.
   *   - 'contact-admin': the phrase to use when the user needs a privileged
   *     administrator to fix the problem.
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *   - 'case-no-project': the message when a project does not exist.
   *   - 'case-no-paired-genus': the message when a project has no genus set
   *     to it.
   *   - 'case-project-genus-mismatch': the message when the genus selected
   *     by the user is not configured to the selected project.
   *
   * @return array
   *   A render array of type "item" used to display feedback to the user about
   *   the validation failure, where:
   *   - The 'title' is a sentence describing the case triggered
   *   - The 'items' are an unordered list of one or more of the following,
   *     provided as context depending on the case that was triggered:
   *     - The project name selected by the user
   *     - The genus selected by the user
   *
   * @throws \Exception
   *   - If the failure parameter was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processSimpleList(array $failure, array $tokens = []) {

    // Check the format of the failure parameter.
    ImportValidationHelper::checkValidationStatusArray($failure, 'ProjectGenusMatch');
    // Grab the default messages for all of our tokens (ones with default-msg).
    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);

    // Check for one of the expected cases. Use the message stored in the
    // provided tokens array if set, otherwise use our default case message.
    if ($failure['case'] == 'Project does not exist') {
      $message = $combined_tokens['case-no-project'];
      $items = [
        '[project]: ' . $failure['failedItems']['project_provided'],
      ];
    }
    elseif ($failure['case'] == 'Project has no genus set and could not compare with the genus provided') {
      $message = $combined_tokens['case-no-paired-genus'];
      $items = [
        '[project]: ' . $failure['failedItems']['project_provided'],
        'Genus: ' . $failure['failedItems']['genus_provided'],
      ];
    }
    elseif ($failure['case'] == 'Genus does not match a genus set to the project') {
      $message = $combined_tokens['case-project-genus-mismatch'];
      $items = [
        '[project]: ' . $failure['failedItems']['project_provided'],
        'Genus: ' . $failure['failedItems']['genus_provided'],
      ];
    }
    elseif ($failure['case'] == 'Project exists and project-genus match the genus provided') {
      throw new \Exception('The case string returned by the ProjectGenusMatch validator implies validation passed, but valid is set to FALSE.');
    }
    else {
      throw new \Exception('The case string returned by the ProjectGenusMatch validator is not recognized as a potential case.');
    }

    // Now replace any tokens that are in our message or items.
    // We use the Tripal Token Parser service to ensure that more complicated
    // tokens are supported.
    // NOTE: Dependency injection is NOT used since this is a static method.
    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    $replaced_message = $service_TripalTokensParser->replaceTokens($message, $combined_tokens);
    $items = $service_TripalTokensParser->replaceTokensArray($items, $combined_tokens);

    // Build the render array.
    $render_array = [
      '#type' => 'item',
      '#title' => $replaced_message,
      '#wrapper_attributes' => [
        'class' => [
          'tcp-project-genus-match-failures',
        ],
      ],
      'items' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => $items,
      ],
    ];

    return $render_array;
  }

}
