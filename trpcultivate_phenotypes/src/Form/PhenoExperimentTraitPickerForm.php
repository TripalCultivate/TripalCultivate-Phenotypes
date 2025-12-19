<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class definition of Experiment Add Trait.
 */
class PhenoExperimentTraitPickerForm extends FormBase {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db_connection;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $service_EntityTypeManager;

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
   * The Drupal Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $service_Renderer;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $service_RouteMatch;

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
  private const PHENO_COMBO = 'trpcultivate_phenocombo';

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $db_connection
   *   Drupal database connection.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Render service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultiavtePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   TripalCultivate Phenotypes Genus-Ontology.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   TripalCultivate Phenotypes Genus-Project service.
   * @param \Drupal\trpcultivate_phenotypes\Service\ripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   */
  public function __construct(
    Connection $db_connection,
    ChadoConnection $chado_connection,
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer,
    RouteMatchInterface $route_match,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->db_connection = $db_connection;
    $this->chado_connection = $chado_connection;
    $this->service_EntityTypeManager = $entity_type_manager;
    $this->service_Renderer = $renderer;
    $this->service_RouteMatch = $route_match;
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
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_route_match'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.genus_project'),
      $container->get('trpcultivate_phenotypes.traits'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'content_bio_data_research_experiment_trait_picker_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Save route slug values for subsequent AJAX requests.
    $tripal_entity_id = $form_state->get('tripal_entity_id');

    if ($tripal_entity_id) {
      $research_experiment = $this->service_EntityTypeManager
        ->getStorage('tripal_entity')
        ->load($tripal_entity_id);
    }
    else {
      $research_experiment = $this->service_RouteMatch
        ->getParameter('tripal_entity');

      if (!$research_experiment->id()) {
        // Research experiment entity does not exist.
        throw new NotFoundHttpException();
      }

      $form_state->set('tripal_entity_id', $research_experiment->id());
    }

    $experiment = $research_experiment->get('exp_name')->getValue();
    if (!$experiment) {
      // Research experiment entity does not contain exp_name field.
      throw new NotFoundHttpException();
    }

    $project_id = (int) $experiment[0]['record_id'];
    $form_state->set('project_id', $project_id);

    if (isset($form_state->getTriggeringElement()['#name'])
      && $form_state->getTriggeringElement()['#name'] == 'genus') {

      $form_state->set('genus', $form_state->getValue('genus'));
      $form_state->set('project_id', $form_state->get('project_id'));
    }

    $genus = $form_state->get('genus');
    $experiment_genus = $this->service_PhenoGenusProject
      ->getGenusOfProject($project_id);

    if (!$genus) {
      $genus = $this->service_RouteMatch->getParameter('genus');
      $genus = (empty($genus)) ? 0 : $genus;

      if ($genus == 0 && count($experiment_genus) == 1) {
        $genus = $experiment_genus[0];
      }

      $form_state->set('genus', $genus);
    }

    // Default to null cv (cv id 1) instead of 0. This field will be disabled.
    $genus_config = 1;
    if ($genus) {
      $genus_config = $this->service_PhenoGenusOntology
        ->getGenusOntologyConfigValues($genus)['trait'];
    }

    $this->messenger()->deleteAll();
    $form['reminder'] = [
      '#theme' => 'status_messages',
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

    $form_dialog_wrapper = self::FORM_WRAPPER;
    $form[$form_dialog_wrapper] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $dialog_wrapper,
      ],
    ];

    $form[$form_dialog_wrapper]['search_fieldset'] = [
      '#type' => 'details',
      '#title' => [
        '#markup' => '
          Search Trait
          <small><a href="#">Suggest a Trait</a> | <a href="#">Show all Traits</a></small>
        ',
      ],
      '#attributes' => [
        'class' => ['container-inline'],
      ],
      '#open' => TRUE,
      '#id' => 'tcp-search-fieldset',
    ];

    $form[$form_dialog_wrapper]['search_fieldset']['genus'] = [
      '#type' => 'select',
      '#options' => array_combine($experiment_genus, $experiment_genus),
      '#empty_option' => 'Select Genus',
      '#empty_value' => 0,
      '#default_value' => $genus,
      '#ajax' => [
        'callback' => '::setGenus',
        'event' => 'change',
        'wrapper' => $dialog_wrapper,
        'throbber' => [
          'progress' => 'none',
        ],
      ],
      '#states' => [
        'disabled' => [
          ':input[name="genus"]' => ['!value' => 0],
        ],
      ],
      '#id' => 'tcp-genus',
    ];

    $form[$form_dialog_wrapper]['search_fieldset']['trait'] = [
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      '#size' => 48,
      '#autocomplete_route_parameters' => [
        'count' => 10,
        'cv_id' => $genus_config,
      ],
      '#attributes' => [
        'placeholder' => 'Trait name (e.g., Plant height or Days to flower)',
        'style' => 'margin: 0 0 0 5px; width: 100%',
        'class' => ['trigger-element'],
      ],
      '#ajax' => [
        'callback' => '::matchTrait',
        'event' => 'change',
        'wrapper' => $dialog_wrapper,
      ],
      '#states' => [
        'disabled' => [
          ':input[name="genus"]' => ['value' => 0],
        ],
      ],
      '#id' => 'tcp-trait',
    ];

    $form[$form_dialog_wrapper]['a_note'] = [
      '#type' => 'container',
      '#markup' => '<p>Start typing part of the trait name into the search field to search for specific traits, or click
       <a href="#">Show All Traits</a> to explore all available traits for the selected genus.</p>',
      '#states' => [
        'invisible' => [
          ':input[name="genus"]' => ['value' => 0],
        ],
      ],
    ];

    // Prepare traits that matched the search.
    $triggering_element = $form_state->getTriggeringElement() ?? 0;

    if ($triggering_element && isset($triggering_element['#attributes']['class'])
      && in_array('trigger-element', $triggering_element['#attributes']['class'])) {

      if ($triggering_element && $triggering_element['#value'] == 'Add') {
        $item_key = $triggering_element['#id'];
        $values = $form_state->getValues();

        $label = $values[$item_key . '_label'];
        $label = (empty($label)) ? $values[$item_key . '_default_label'] : $label;

        // No same labels in an experiment.
        $label_exists = $this->db_connection->select(self::PHENO_COMBO, 'tc')
          ->fields('tc', ['combo_id'])
          ->condition('tc.label', $label, '=')
          ->condition('tc.project_id', $project_id, '=')
          ->execute()
          ->fetchField();

        if ($label_exists) {
          $form[$form_dialog_wrapper]['reminder'] = [
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

        $transaction = $this->db_connection->startTransaction();
        try {
          $this->db_connection
            ->insert(self::PHENO_COMBO)
            ->fields([
              'project_id' => $project_id,
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

      $query_combo = $this->db_connection->select(self::PHENO_COMBO, 'tc');
      $query_combo
        ->addExpression('CONCAT(tc.attr_id, \':\', tc.observable_id, \':\', tc.unit_id)', 'combo');
      $exp_combos = $query_combo
        ->condition('tc.project_id', $form_state->get('project_id'), '=')
        ->execute()
        ->fetchCol();

      $trait_name = $form_state->getValue('trait');

      // Exclude trait term properties construct (in parenthesis) returned by
      // the trait autocomplete field.
      $search_key = preg_replace('/\s*\(.*?\)/', '', $trait_name);

      $query_trait = $this->chado_connection
        ->select('1:cvterm', 'tc')
        ->fields('tc', ['cvterm_id', 'name', 'definition'])
        ->condition('tc.cv_id', $genus_config, '=');

      if (strtolower($trait_name) != 'all') {
        $query_trait
          ->condition('tc.name', trim($search_key) . '%', 'LIKE');
      }

      $trait_query_result = $query_trait
        ->orderBy('tc.name', 'ASC')
        ->execute();

      $this->service_PhenoTraits->setTraitGenus($genus);

      $form[$form_dialog_wrapper]['table'] = [
        '#type' => 'table',
        '#header' => [],
        '#rows' => [],
        '#sticky' => FALSE,
        '#empty' => 'No traits found or traits may have already been included in the experiment.',
        '#allowed_tags' => ['br', 'em', 'div', 'def', 'small', 'span', 'section'],
      ];

      foreach ($trait_query_result as $trait) {
        $trait_methods = $this->service_PhenoTraits->getTraitMethod($trait->cvterm_id);
        if (!$trait_methods) {
          // Skip trait that does not have a method.
          continue;
        }

        $methods = [];

        foreach ($trait_methods as $method) {
          $method_unit = $this->service_PhenoTraits->getMethodUnit($method->cvterm_id)[0];

          // Exclude from list, trait-method-unit combo already in experiment.
          $combo_ids = $trait->cvterm_id . ':' . $method->cvterm_id . ':' . $method_unit->cvterm_id;
          if (in_array($combo_ids, $exp_combos)) {
            continue;
          }

          array_push($methods, [
            'method_id' => $method->cvterm_id,
            'method_shortname' => $method->name,
            'unit' => $method_unit->name,
            'type' => $this->service_PhenoTraits->getMethodUnitDataType($method_unit->cvterm_id),
            'collection_method' => $method->definition,
          ]);

          $item_key = 'combo_' . $trait->cvterm_id . '_' . $method->cvterm_id;

          $build['actions'][$item_key] = [
            '#type' => 'container',
            '#tree' => FALSE,
            '#attributes' => [
              'class' => ['container-inline'],
            ],
          ];

          $build['actions'][$item_key][$item_key . '_label'] = [
            '#type' => 'textfield',
            '#name' => $item_key . '_label',
            '#maxlength' => 150,
            '#size' => 40,
            '#attributes' => [
              'title' => 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment.',
              'placeholder' => 'Use trait with label: ' . $default_label = $trait->name . ' ' . $method->name,
              'style' => 'margin: 0 15px 0 0; width: 100%',
            ],
          ];

          $build['actions'][$item_key][$item_key . '_default_label'] = [
            '#type' => 'hidden',
            '#name' => $item_key . '_default_label',
            '#value' => $default_label,
          ];

          $build['actions'][$item_key][$item_key . '_required'] = [
            '#type' => 'checkbox',
            '#name' => $item_key . '_required',
            '#return_value' => 1,
            '#default_value' => 0,
            '#suffix' => '<i class="fa-solid fa-star" title="Required Trait"></i>',
          ];

          $build['actions'][$item_key][$item_key . '_combo'] = [
            '#type' => 'hidden',
            '#name' => $item_key . '_combo',
            '#value' => $combo_ids,
          ];

          $build['actions'][$item_key][$item_key . '_add'] = [
            '#type' => 'button',
            '#name' => $item_key . '_add',
            '#value' => 'Add',
            '#id' => $item_key,
            '#attributes' => [
              'class' => ['trigger-element'],
            ],
            '#ajax' => [
              'callback' => '::addTrait',
              'event' => 'click',
              'wrapper' => $dialog_wrapper,
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
          'content' => $build['actions'],
        ];

        // Form elements in the component and not in the page.
        unset($build['actions']);
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
  public function matchTrait(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

  /**
   * Function callback - add trait combo.
   */
  public function addTrait(array &$form, FormStateInterface $form_state) {

    return $form[self::FORM_WRAPPER];
  }

}
