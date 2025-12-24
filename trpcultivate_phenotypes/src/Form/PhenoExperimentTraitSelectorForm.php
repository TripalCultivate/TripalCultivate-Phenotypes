<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class definition of Experiment Add Trait.
 */
class PhenoExperimentTraitSelectorForm extends FormBase {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database_connection;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Genus-Ontotology service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultiavtePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * Genus-Project service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService
   */
  protected TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject;

  /**
   * Traits service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits;

  /**
   * The form element name attribute that wraps all fields.
   *
   * @var string
   */
  private const FORM_WRAPPER = 'dialog_wrapper';

  /**
   * The table name.
   *
   * @var string
   */
  private const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database_connection
   *   Drupal database connection.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultiavtePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   TripalCultivate Phenotypes Genus-Ontology.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   TripalCultivate Phenotypes Genus-Project service.
   * @param \Drupal\trpcultivate_phenotypes\Service\ripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   */
  public function __construct(
    Connection $database_connection,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->database_connection = $database_connection;
    $this->chado_connection = $chado_connection;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
    $this->service_PhenoGenusProject = $service_PhenoGenusProject;
    $this->service_PhenoTraits = $service_PhenoTraits;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('database'),
      $container->get('tripal_chado.database'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.genus_project'),
      $container->get('trpcultivate_phenotypes.traits'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'pheno_experiment_trait_selector_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tripal_entity = $this->getRouteMatch()->getParameter('tripal_entity');

    $experiment = $tripal_entity->get('exp_name')->getValue();
    if (!$experiment) {
      // Research experiment entity does not exist.
      $this->tripal_logger->error('Research experiment Tripal entity does not exist.');
      throw new NotFoundHttpException();
    }

    ['record_id' => $experiment_id, 'value' => $experiment_name] = $experiment[0];

    // Update the title to show which reseach experiment is being configured.
    $form['#title'] = 'Add traits to ' . $experiment_name;

    // Listen for request to set genus or search trait.
    $trigger_el = $form_state->getTriggeringElement() ?? 0;

    if (isset($trigger_el['#name']) && $trigger_el['#name'] == 'genus') {
      $form_state->set('genus', $form_state->getValue('genus'));
    }

    $genus = $form_state->get('genus');
    $exp_phenogenus = $this->service_PhenoGenusProject->getGenusOfProject((int) $experiment_id);

    if (!$genus) {
      $genus = $this->getRouteMatch()->getParameter('genus') ?: 0;

      if ($genus == 0 && count($exp_phenogenus) == 1) {
        $genus = $exp_phenogenus[0];
      }

      $form_state->set('genus', $genus);
    }

    // Default to null cv (cv id 1) instead of 0. This field will be disabled.
    $genus_config = 1;
    if ($genus) {
      $genus_config = $this->service_PhenoGenusOntology
        ->getGenusOntologyConfigValues($genus)['trait'];
    }

    // Exclude other messages in the session that AJAX tends to repost.
    $this->messenger()->deleteAll();

    $form['reminder'] = [
      '#theme' => 'status_messages',
      '#prefix' => '<br />',
      '#message_list' => [
        'warning' => [
          'Read trait details carefully to ensure you are selecting the correct trait, as some traits may appear similar or have subtle differences.',
        ],
      ],
      '#status_headings' => [
        'warning' => 'Warning message',
      ],
    ];

    // Reference this wrapper class name in AJAX wrapper render property.
    $dialog_wrapper = 'tcp-dialog-wrapper';

    // The main AJAX response wrapper/container.
    $form_dialog_wrapper = self::FORM_WRAPPER;
    $form[$form_dialog_wrapper] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $dialog_wrapper,
      ],
    ];

    $flex_container = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'display: flex;',
      ],
    ];

    $build['flex_container'] = $flex_container;
    $build['flex_container']['#children'] = [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => 'Search Trait',

      ],
      'links' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '<a href="">Suggest a Trait</a> / <a href="#">Show all Traits</a>',
        '#attributes' => [
          'style' => 'margin-left: auto; font-weight: 300;',
        ],
      ],
    ];

    $form[$form_dialog_wrapper]['search_fieldset'] = [
      '#type' => 'details',
      '#title' => [
        'title' => $build['flex_container'],
      ],
      '#open' => TRUE,
    ];

    $form[$form_dialog_wrapper]['search_fieldset']['flex_container'] = $flex_container;
    $form[$form_dialog_wrapper]['search_fieldset']['flex_container']['genus'] = [
      '#type' => 'select',
      '#options' => array_combine($exp_phenogenus, $exp_phenogenus),
      '#empty_option' => 'Select Genus',
      '#empty_value' => 0,
      '#default_value' => $genus,
      '#ajax' => [
        'callback' => '::setGenus',
        'event' => 'change',
        'wrapper' => $dialog_wrapper,
        'progress' => [
          'type' => 'fullscreen',
          'message' => '',
        ],
      ],
      '#states' => [
        'disabled' => [
          ':input[name="genus"]' => ['!value' => 0],
        ],
      ],
    ];

    // Prperty class - trigger-element - is used to identify which trait to add.
    $form[$form_dialog_wrapper]['search_fieldset']['flex_container']['trait'] = [
      '#type' => 'textfield',
      '#maxlength' => 150,
      '#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      '#autocomplete_route_parameters' => [
        'count' => 10,
        'cv_id' => $genus_config,
      ],
      '#attributes' => [
        'placeholder' => 'Trait name (e.g., Plant height or Days to flower)',
        'style' => 'margin: 0 0 0 10px;',
        'class' => [
          'trigger-element',
        ],
      ],
      '#ajax' => [
        'callback' => '::loadGenusTrait',
        'event' => 'change',
        'wrapper' => $dialog_wrapper,
        'progress' => [
          'type' => 'fullscreen',
          'message' => '',
        ],
      ],
      '#states' => [
        'disabled' => [
          ':input[name="genus"]' => ['value' => 0],
        ],
      ],
    ];

    $form[$form_dialog_wrapper]['a_note'] = [
      '#type' => 'container',
      '#children' => [
        'a_note' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => 'Start typing part of the trait name into the search field to search for specific traits,
            or click - Show all Traits, to explore all available traits for the selected genus.',
        ],
      ],
      '#states' => [
        'invisible' => [
          ':input[name="filter_genus"]' => ['value' => 0],
        ],
      ],
    ];

    // Prepare tratis that matched the search key.
    if ($trigger_el && isset($trigger_el['#attributes']['class'])
      && in_array('trigger-element', $trigger_el['#attributes']['class'])) {

      // Listen for operation to add trait combo to experiment.
      if ($trigger_el['#value'] == 'Add') {
        $values = $form_state->getValues();
        $item_key = $trigger_el['#id'];

        $label = $values[$item_key . '_label'] ?: $values[$item_key . '_default_label'];

        // No same labels in an experiment.
        $label_exists = $this->database_connection->select(self::PHENO_COMBO_TABLE, 'tc')
          ->fields('tc', ['combo_id'])
          ->condition('tc.label', $label, '=')
          ->condition('tc.project_id', $experiment_id, '=')
          ->execute()
          ->fetchField();

        if ($label_exists) {
          $form['duplicate_label'] = [
            '#theme' => 'status_messages',
            '#message_list' => [
              'warning' => [
                'The label is already used in the experiment.',
              ],
            ],
            '#status_headings' => [
              'error' => 'Label already exists',
            ],
          ];
        }

        [$attr_id, $observable_id, $unit_id] = explode(':', $values[$item_key . '_combo']);

        $transaction = $this->database_connection->startTransaction();
        try {
          $this->database_connection
            ->insert(self::PHENO_COMBO_TABLE)
            ->fields([
              'project_id' => $experiment_id,
              'attr_id' => $attr_id,
              'observable_id' => $observable_id,
              'unit_id' => $unit_id,
              'label' => $label,
              'is_archived' => 0,
              'is_required' => $values[$item_key . '_required'],
              'was_shared' => 0,
              'was_collected' => 0,
              'uid' => $this->currentUser()->id(),
              'timestamp' => time(),
            ])
            ->execute();
        }
        catch (Exception $e) {
          $transaction->rollback();
        }
      }

      // List genus traits.
      $query = $this->database_connection->select(self::PHENO_COMBO_TABLE, 'tc');
      $query
        ->addExpression('CONCAT(tc.attr_id, \':\', tc.observable_id, \':\', tc.unit_id)', 'combo');
      $exp_traits = $query
        ->condition('tc.project_id', $experiment_id, '=')
        ->execute()
        ->fetchCol();

      $trait_name = $form_state->getValue('trait');

      // Exclude trait term properties construct (in parenthesis) returned by
      // the trait autocomplete field.
      $search_key = preg_replace('/\s*\(.*?\)/', '', $trait_name);

      $query = $this->chado_connection
        ->select('1:cvterm', 'tc')
        ->fields('tc', ['cvterm_id', 'name', 'definition'])
        ->condition('tc.cv_id', $genus_config, '=');

      if (strtolower($trait_name) != 'all') {
        $query
          ->condition('tc.name', trim($search_key) . '%', 'LIKE');
      }

      $query_result = $query
        ->orderBy('tc.name', 'ASC')
        ->execute();

      $form[$form_dialog_wrapper]['table'] = [
        '#type' => 'table',
        '#header' => [],
        '#rows' => [],
        '#sticky' => FALSE,
        '#empty' => 'No traits found or traits may have already been included in the experiment.',
        '#allowed_tags' => ['br', 'em', 'div', 'def', 'small', 'span', 'section'],
      ];

      $this->service_PhenoTraits->setTraitGenus($genus);

      foreach ($query_result as $trait_index => $trait) {
        $trait_methods = $this->service_PhenoTraits->getTraitMethod($trait->cvterm_id);

        if (!$trait_methods) {
          // Skip trait that does not have a method.
          continue;
        }

        $methods = [];

        foreach ($trait_methods as $method) {
          $method_unit = $this->service_PhenoTraits->getMethodUnit($method->cvterm_id)[0];

          // Exclude from list - trait-method-unit combo already in experiment.
          $combo_ids = $trait->cvterm_id . ':' . $method->cvterm_id . ':' . $method_unit->cvterm_id;
          if (in_array($combo_ids, $exp_traits)) {
            continue;
          }

          array_push($methods, [
            'method_id' => $method->cvterm_id,
            'method_shortname' => $method->name,
            'unit' => $method_unit->name,
            'type' => $this->service_PhenoTraits->getMethodUnitDataType($method_unit->cvterm_id),
            'collection_method' => $method->definition,
          ]);

          // The main container/wrapper for each fieldset containing - label,
          // required, add button attached to each trait-method.
          $item_key = 'combo_' . $trait->cvterm_id . '_' . $method->cvterm_id;

          $build['fieldset'][$item_key] = [
            '#type' => 'container',
            '#tree' => FALSE,
            '#attributes' => [
              'class' => [
                'container-inline',
              ],
            ],
          ];

          // Fieldset element: label and default label.
          $default_label = $trait->name . ' ' . $method->name;

          $build['fieldset'][$item_key][$item_key . '_label'] = [
            '#type' => 'textfield',
            '#name' => $item_key . '_label',
            '#maxlength' => 150,
            '#theme_wrappers' => [],
            '#attributes' => [
              'placeholder' => 'Use trait with label: ' . $default_label,
              'title' => 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment.',
            ],
          ];

          $build['fieldset'][$item_key][$item_key . '_default_label'] = [
            '#type' => 'hidden',
            '#name' => $item_key . '_default_label',
            '#value' => $default_label,
          ];

          // Fieldset element: required trait.
          $build['fieldset'][$item_key][$item_key . '_required'] = [
            '#type' => 'checkbox',
            '#name' => $item_key . '_required',
            '#return_value' => 1,
            '#default_value' => 0,
          ];

          $build['fieldset'][$item_key]['star'] = [
            '#type' => 'html_tag',
            '#tag' => 'i',
            '#attributes' => [
              'class' => [
                'fa-solid',
                'fa-star',
              ],
              'title' => 'Make this trait required',
            ],
          ];

          // Fieldset element: trait-method-unit combo reference ids and add.
          $build['fieldset'][$item_key][$item_key . '_combo'] = [
            '#type' => 'hidden',
            '#name' => $item_key . '_combo',
            '#value' => $combo_ids,
          ];

          $build['fieldset'][$item_key][$item_key . '_add'] = [
            '#type' => 'button',
            '#name' => $item_key . '_add',
            '#value' => 'Add',
            '#id' => $item_key,
            '#attributes' => [
              'class' => [
                'trigger-element',
              ],
            ],
            '#ajax' => [
              'callback' => '::addTraitCombo',
              'event' => 'click',
              'wrapper' => $dialog_wrapper,
              'progress' => [
                'type' => 'fullscreen',
                'message' => '',
              ],
            ],
          ];
        }

        if (count($methods) < 1) {
          continue;
        }

        $form[$form_dialog_wrapper]['table'][$trait_index]['combo'] = [
          '#type' => 'component',
          '#component' => 'trpcultivate_phenotypes:trait_combo',
          '#props' => [
            'name' => $trait->name,
            'definition' => $trait->definition,
            'multiselect_method' => TRUE,
            'method_unit_combo' => $methods,
            'trait_id' => (int) $trait->cvterm_id,
          ],
          'content' => $build['fieldset'],
        ];

        $build = [];
      }
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Function callback - set genus.
   */
  public function setGenus(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

  /**
   * Function callback - list trait combo that matched.
   */
  public function loadGenusTrait(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

  /**
   * Function callback - add trait combo.
   */
  public function addTraitCombo(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

}
