<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class definition of Experiment Add Trait.
 */
class PhenoExperimentTraitSelectorForm extends FormBase {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $drupaldb_connection;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Tripal logger service.
   *
   * @var \Drupal\tripal\Services\TripalLogger
   */
  protected TripalLogger $tripal_logger;

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
  private const FORM_WRAPPER = 'form_wrapper';

  /**
   * The table name.
   *
   * @var string
   */
  private const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $drupaldb_connection
   *   Drupal database connection.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\tripal\Services\TripalLogger $tripal_logger
   *   Tripal logger service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultiavtePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   TripalCultivate Phenotypes Genus-Ontology.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   TripalCultivate Phenotypes Genus-Project service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   */
  public function __construct(
    Connection $drupaldb_connection,
    ChadoConnection $chado_connection,
    TripalLogger $tripal_logger,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->drupaldb_connection = $drupaldb_connection;
    $this->chado_connection = $chado_connection;
    $this->tripal_logger = $tripal_logger;
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
      $container->get('tripal.logger'),
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

    // Route validates instance of tripal_entity and presence of exp_name field.
    $experiment = $tripal_entity->get('exp_name')->getValue();
    ['record_id' => $experiment_id, 'value' => $experiment_name] = $experiment[0];

    // Update the title to show which research experiment is being configured.
    $form['#title'] = 'Add traits to ' . $experiment_name;

    $form['tripal_entity_id'] = [
      '#type' => 'hidden',
      '#value' => $tripal_entity->id(),
    ];

    $genus = $this->getRouteMatch()->getParameter('genus') ?: 0;

    $exp_phenogenus = $this->service_PhenoGenusProject->getGenusOfProject((int) $experiment_id);
    if ($genus && !in_array($genus, $exp_phenogenus)) {
      // The genus does not exist in the list of genus of the experiment.
      $this->tripal_logger->error('The genus is not supported by the experiment.');
      throw new NotFoundHttpException();
    }

    if (!$genus) {
      $genus = $form_state->getValue('genus', 0);

      // Select the unique genus when none is supplied for the entity.
      if ($genus == 0 && count($exp_phenogenus) == 1) {
        $genus = $exp_phenogenus[0];
      }
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
    $dialog_wrapper = 'tcp-form-wrapper';

    // The main AJAX response wrapper/container.
    $form_dialog_wrapper = self::FORM_WRAPPER;

    $form_container = [
      '#type' => 'container',
    ];

    $form[$form_dialog_wrapper] = array_merge(
      $form_container,
      [
        '#attributes' => [
          'id' => $dialog_wrapper,
          'style' => 'position: relative;',
        ],
      ],
    );

    $form[$form_dialog_wrapper]['search_toolbar'] = array_merge(
      $form_container,
      [
        '#attributes' => [
          'style' => 'position: absolute; right: 15px; z-index: 1000;',
        ],
      ],
    );

    $form[$form_dialog_wrapper]['search_toolbar']['import_traits'] = [
      '#type' => 'link',
      '#title' => 'Import Traits',
      '#url' => Url::fromUri(
        'internal:/admin/tripal/loaders/trpcultivate-phenotypes-traits-importer',
      ),
      '#attributes' => [
        'target' => '_blank',
        'title' => 'Could not find a trait? Launch Trait Importer in a new window.',
      ],
      '#suffix' => ' <i class="fa-solid fa-arrow-up-right-from-square"></i>',
    ];

    $form[$form_dialog_wrapper]['search_toolbar']['slash'] = [
      '#markup' => '&nbsp;&nbsp; / &nbsp;',
    ];

    $form[$form_dialog_wrapper]['search_toolbar']['show_all'] = [
      '#type' => 'button',
      '#value' => 'Show all Traits',
      '#attributes' => [
        'onclick' => 'jQuery("#tcp-trait").val("");',
        'class' => [
          'trigger-element',
          'button--small',
        ],
        'title' => 'Show all available traits for the selected genus',
      ],
      '#ajax' => [
        'callback' => '::loadGenusTraits',
        'event' => 'click',
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

    $form[$form_dialog_wrapper]['search_toolbar']['close'] = [
      '#type' => 'button',
      '#value' => 'Close & Refresh Table',
      '#attributes' => [
        'class' => [
          'button--small',
        ],
        'title' => 'Close Trait Selector and update trait summary listing table',
      ],
      '#ajax' => [
        'callback' => '::closeTraitSelector',
        'event' => 'click',
        'progress' => [
          'type' => 'fullscreen',
          'message' => '',
        ],
      ],
    ];

    $form[$form_dialog_wrapper]['search_fieldset'] = [
      '#type' => 'details',
      '#title' => 'Search Trait',
      '#open' => TRUE,
    ];

    $form[$form_dialog_wrapper]['search_fieldset']['flex_container'] = array_merge(
      $form_container,
      [
        '#attributes' => [
          'style' => 'display: flex',
        ],
      ],
    );

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

    // Prepare autocomplete route parameter cv_id for the genus.
    $genus_config = 1;
    if ($genus) {
      $genus_config = $this->service_PhenoGenusOntology
        ->getGenusOntologyConfigValues($genus)['trait'];
    }

    // The property class - trigger-element is used to tag field with defined
    // AJAX actions, marking elements that initiate a request.
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
        'style' => 'margin: 0 0 0 15px;',
        'onclick' => 'this.select()',
        'class' => [
          'trigger-element',
        ],
      ],
      '#ajax' => [
        'callback' => '::loadGenusTraits',
        'event' => 'autocompleteclose',
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
      '#id' => 'tcp-trait',
    ];

    $form[$form_dialog_wrapper]['search_tooltips'] = [
      '#type' => 'container',
      '#children' => [
        'a_tip' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => 'Start typing part of the trait name into the search field to search for specific traits, or click "Show all Traits" button, to explore all available traits for the selected genus.',
        ],
      ],
      '#states' => [
        'invisible' => [
          ':input[name="genus"]' => ['value' => 0],
        ],
      ],
    ];

    // Capture trigger element responsible for staring trait selection request.
    $trigger_el = $form_state->getTriggeringElement() ?? 0;

    // Prepare traits that matched the search key.
    if ($trigger_el && isset($trigger_el['#attributes']['class'])
      && in_array('trigger-element', $trigger_el['#attributes']['class'])) {

      unset($form[$form_dialog_wrapper]['search_tooltips']);

      $trait_name = $form_state->getValue('trait');

      // Listen for operation to add trait combo to experiment.
      if ($trigger_el['#value'] == 'Add') {
        $values = $form_state->getValues();
        $item_key = $trigger_el['#id'];

        $label = $values[$item_key . '_label'] ?: $values[$item_key . '_default_label'];

        // No same labels in an experiment.
        $label_exists = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tc')
          ->fields('tc', ['combo_id'])
          ->condition('tc.label', $label, '=')
          ->condition('tc.project_id', $experiment_id, '=')
          ->execute()
          ->fetchField();

        if ($label_exists) {
          $form[$form_dialog_wrapper]['duplicate_label'] = [
            '#theme' => 'status_messages',
            '#message_list' => [
              'error' => [
                'The label is already used in the experiment.',
              ],
            ],
            '#status_headings' => [
              'error' => 'Label already exists',
            ],
          ];
        }
        else {
          [$attr_id, $observable_id, $unit_id] = explode(':', $values[$item_key . '_combo']);

          $transaction = $this->drupaldb_connection->startTransaction();
          try {
            $this->drupaldb_connection
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
      }

      // List genus traits.
      $query = $this->drupaldb_connection->select(self::PHENO_COMBO_TABLE, 'tc');
      $query
        ->addExpression('CONCAT(tc.attr_id, \':\', tc.observable_id, \':\', tc.unit_id)', 'combo');
      $exp_traits = $query
        ->condition('tc.project_id', $experiment_id, '=')
        ->execute()
        ->fetchCol();

      // Exclude trait term properties construct (in parenthesis) returned by
      // the trait autocomplete field.
      $search_key = preg_replace('/\s*\(.*?\)/', '', $trait_name);

      $query = $this->chado_connection
        ->select('1:cvterm', 'tc')
        ->fields('tc', ['cvterm_id', 'name', 'definition'])
        ->condition('tc.cv_id', $genus_config, '=');

      if ($trigger_el['#value'] != 'Show all Traits') {
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
              'style' => 'width: 70%;',
              'placeholder' => 'Use trait with label: ' . $default_label,
              'title' => 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment. If no value is given, Label defaults to: ' . $default_label,
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
              'title' => 'Make this trait Required: this measurement is required to answer the questions in this experiment and must be included when uploading phenotypic data for this experiment.',
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
   * AJAX callback: close trait selector.
   */
  public function closeTraitSelector(array &$form, FormStateInterface $form_state) {

    $params = [
      'tripal_entity' => $form_state->getValue('tripal_entity_id'),
      'genus' => $form_state->getValue('genus'),
    ];

    if (!$params['genus']) {
      unset($params['genus']);
    }

    $response = new AjaxResponse();
    $response
      ->addCommand(new CloseDialogCommand())
      ->addCommand(new RedirectCommand(
        Url::fromRoute('trpcultivate_phenotypes.experiment_configuration', $params, ['query' => []])
          ->toString()
      ));

    return $response;
  }

  /**
   * Function callback - restrict the trait selector to a specific genus.
   */
  public function setGenus(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

  /**
   * Function callback - related to genus, list trait combo that matched.
   */
  public function loadGenusTraits(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

  /**
   * Function callback - add trait combo to experiment.
   */
  public function addTraitCombo(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

}
