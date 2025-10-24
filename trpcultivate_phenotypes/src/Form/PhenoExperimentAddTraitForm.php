<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
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
class PhenoExperimentAddTraitForm extends FormBase {

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
   * The genus used to filter the traits table and show only related traits.
   *
   * @var string
   */
  private string $filter_genus;

  /**
   * Constructor.
   *
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
    ChadoConnection $chado_connection,
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer,
    RouteMatchInterface $route_match,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

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
    return 'content_bio_data_research_experiment_add_trait_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Save the slug values entity id so it is available in multiple subsequent
    // AJAX process requests.
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
      // Research experiment entity does not exist contain exp_name field.
      throw new NotFoundHttpException();
    }

    $form['#attached']['library'] = [
      'trpcultivate_phenotypes/trpcultivate-phenotypes-experiment-configuration',
      'trpcultivate_phenotypes/trpcultivate-phenotypes-script-autoselect-field',
    ];

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

    $form['fieldset'] = [
      '#type' => 'details',
      '#title' => 'Search Trait',
      '#open' => TRUE,
      '#id' => 'tcp-serch-controls-fieldset',
    ];

    $active_genus = $this->service_RouteMatch->getParameter('genus');
    $experiment_genus = $this->service_PhenoGenusProject
      ->getGenusOfProject((int) $experiment[0]['record_id']);
    $form_state->set('project_id', $experiment[0]['record_id']);

    $genus_config = $this->service_PhenoGenusOntology
      ->getGenusOntologyConfigValues($active_genus)['trait'];

    $form['fieldset']['genus'] = [
      '#type' => 'select',
      '#options' => array_combine($experiment_genus, $experiment_genus),
      '#default_value' => $active_genus,
    ];

    $form['fieldset']['match_trait'] = [
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'tripal_chado.cvterm_autocomplete',
      '#autocomplete_route_parameters' => ['count' => 10, 'cv_id' => $genus_config],
      '#attributes' => [
        'placeholder' => 'Trait',
        'class' => ['tcp-autocomplete'],
      ],
      '#ajax' => [
        'callback' => '::matchTrait',
        'event' => 'change',
        'method' => 'replace',
        'wrapper' => 'tcp-match-trait-result',
      ],
      '#id' => 'tcp-search-trait-field',
    ];

    $form['match_trait_result'] = [
      '#prefix' => '<div id="tcp-match-trait-result">',
      '#markup' => '<p>Start typing part of the trait name to search for specific traits, or click
       <a href="">Show all Traits</a> to view all available traits.</p>',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * Function callback - list traits that matched.
   */
  public function matchTrait(array &$form, FormStateInterface $form_state) {

    $response = new AjaxResponse();

    $genus = $form_state->getValue('genus');
    $genus_config = $this->service_PhenoGenusOntology
      ->getGenusOntologyConfigValues($genus)['trait'];

    $key = $form_state->getValue('match_trait');
    $search_trait = preg_replace('/\s*\(.*?\)/', '', $key);

    $query = $this->chado_connection->select('1:cvterm', 'tc')
      ->fields('tc', ['cvterm_id', 'name', 'definition'])
      ->condition('tc.cv_id', $genus_config, '=')
      ->condition('tc.name', trim($search_trait) . '%', 'LIKE')
      ->orderBy('tc.name', 'ASC')
      ->execute();

    $this->service_PhenoTraits->setTraitGenus($genus);

    $query_combo = $this->chado_connection->select('trpcultivate_phenocombo', 'tc');
    $query_combo
      ->addExpression('CONCAT(tc.attr_id, \':\', tc.observable_id, \':\', tc.unit_id)', 'combo');
    $combos = $query_combo
      ->condition('tc.project_id', $form_state->get('project_id'), '=')
      ->execute()
      ->fetchCol();

    $rows = [];
    foreach ($query as $trait) {
      $trait_methods = $this->service_PhenoTraits->getTraitMethod($trait->cvterm_id);
      if (!$trait_methods) {
        // Skip trait that does not have method.
        continue;
      }

      $methods = [];
      $controls = [];

      foreach ($trait_methods as $method) {
        $method_unit = $this->service_PhenoTraits->getMethodUnit($method->cvterm_id)[0];

        // Do not suggest trait-method-unit combo already in the experiment.
        if (in_array($trait->cvterm_id . ':' . $method->cvterm_id . ':' . $method_unit->cvterm_id, $combos)) {
          continue;
        }

        array_push($methods, [
          'method_shortname' => $method->name,
          'unit' => $method_unit->name,
          'type' => $this->service_PhenoTraits->getMethodUnitDataType($method_unit->cvterm_id),
          'collection_method' => $method->definition,
        ]);

        array_push($controls, [
          'field_label' => [
            '#type' => 'textfield',
            '#theme_wrappers' => [],
            '#attributes' => [
              'placeholder' => 'Use this trait with the label: ' . $trait->name . ' ' . $method->name,
              'class' => ['tcp-add-textfield'],
            ],
          ],
          'field_add' => [
            '#type' => 'button',
            '#value' => 'Add',
            '#attributes' => [
              'class' => ['button--primary'],
            ],
          ],
        ]);
      }

      if (count($methods) < 1) {
        continue;
      }

      $rows[] = [
        [
          'data' => [
            '#type' => 'component',
            '#component' => 'trpcultivate_phenotypes:trait_combo',
            '#props' => [
              'name' => $trait->name,
              'definition' => $trait->definition,
              'multiselect_method' => TRUE,
              'method_unit_combo' => $methods,
            ],
            '#slots' => [
              'controls' => $controls,
            ],
          ],
        ],
      ];
    }

    $traits = [
      '#type' => 'table',
      '#header' => [],
      '#rows' => $rows,
      '#empty' => 'No traits found',
      '#sticky' => FALSE,
      '#allowed_tags' => ['br', 'em', 'div', 'def', 'small', 'span', 'select'],
    ];

    $html = new HtmlCommand('#tcp-match-trait-result', $this->service_Renderer->renderRoot($traits));
    $response->addCommand($html);

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
