<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class definition of Experiment Configuration page.
 */
class PhenoExperimentConfigurationForm extends FormBase {

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $route_match;

  /**
   * Request parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $request_params;

  /**
   * Tripal logger service.
   *
   * @var \Drupal\tripal\Services\TripalLogger
   */
  protected $tripal_logger;

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
   * The table name.
   *
   * @var string
   */
  private const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Table row class name.
   *
   * @var string
   */
  private const TABLE_ROW_CLASS = 'trait-combbo';

  /**
   * Constructor.
   *
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_params
   *   Request parameters.
   * @param \Drupal\tripal\Services\TripalLogger $tripal_logger
   *   Tripal logger service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultiavtePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   TripalCultivate Phenotypes Genus-Ontology service.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject
   *   TripalCultivate Phenotypes Genus-Project service.
   * @param \Drupal\trpcultivate_phenotypes\Service\ripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   */
  public function __construct(
    ChadoConnection $chado_connection,
    RouteMatchInterface $route_match,
    RequestStack $request_params,
    TripalLogger $tripal_logger,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->chado_connection = $chado_connection;
    $this->route_match = $route_match;
    $this->request_params = $request_params;
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
      $container->get('tripal_chado.database'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
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

    return 'research_experiment_configure_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $research_experiment = $this->route_match->getParameter('tripal_entity');

    $experiment = $research_experiment->get('exp_name')->getValue();
    if (!$experiment) {
      // Research experiment entity does not exist.
      $this->tripal_logger->error('Research experiment Tripal entity does not exist.');
      throw new NotFoundHttpException();
    }

    ['record_id' => $experiment_id, 'value' => $experiment_name] = $experiment[0];
    // Update the title to show which reseach experiment is being setup.
    $form['#title'] = 'Configure Phenotypes for ' . $experiment_name;

    $form['research_experiment_entity_id'] = [
      '#type' => 'hidden',
      '#value' => $research_experiment->id(),
    ];

    // If the /genus slug is not provided, show all trait for all genus.
    $filter_genus = $this->route_match->getParameter('genus') ?? 0;

    $configured_genus = 0;
    $exp_genus = $research_experiment->get('exp_germgenus')->getValue();

    foreach ($exp_genus as $germgenus) {
      $is_configured = $this->service_PhenoGenusOntology
        ->getGenusOntologyConfigValues($germgenus['value']);

      if (!$is_configured) {
        $configured_genus++;
      }
    }

    if ($configured_genus == count($exp_genus)) {
      $this->messenger()->addError('The Research Experiment has no configured genus set.');
      return $form;
    }

    $experiment_genus = $this->service_PhenoGenusProject->getGenusOfProject((int) $experiment_id);
    if ($filter_genus && !in_array($filter_genus, $experiment_genus)) {
      // Filter genus does not exist.
      $this->tripal_logger->error('Research experiment genus does not exist.');
      throw new NotFoundHttpException();
    }

    // Create a mapping array to map cv name to a genus and populate the genus
    // filter select field with available genus options.
    $genus_map = [];
    foreach ($experiment_genus as $genus) {
      $cv_id = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus)['trait'];
      $genus_map[$cv_id] = $genus;
    }

    // Determine if the page will default to a genus or all genus.
    if (count($experiment_genus) == 1) {
      $filter_genus = $experiment_genus[0];
    }

    $form['filter_fieldset'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
        ],
        'style' => 'float: right',
      ],
    ];

    $form['filter_fieldset']['filter_icon'] = [
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#attributes' => [
        'class' => [
          'fa-solid',
          'fa-filter',
        ],
        'title' => 'Filter the trait summary table by a genus',
      ],
    ];

    $form['filter_fieldset']['filter_genus'] = [
      '#type' => 'select',
      '#options' => array_combine(array_values($genus_map), array_values($genus_map)),
      '#empty_option' => 'All Genus',
      '#empty_value' => 0,
      '#default_value' => $filter_genus,
      '#theme_wrappers' => [],
      '#ajax' => [
        'callback' => '::filterByGenus',
        'event' => 'change',
        'progress' => [
          'type' => 'none',
          'message' => '',
        ],
      ],
    ];

    // Prepare traits summary table render array.
    $headers = [
      'label' => [
        'data' => '',
        'style' => 'width: 24%',
      ],
      'combo' => [
        'data' => 'Trait Method Unit',
        'style' => 'width: 75%',
      ],
      'remove' => [
        'data' => 'Remove',
        'style' => 'width: 1%',
      ],
    ];

    $headers['label']['data'] = [
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#prefix' => 'Label',
      '#attributes' => [
        'class' => [
          'fa-solid',
          'fa-circle-question',
        ],
        'title' => 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment',
      ],
    ];

    $this->messenger()
      ->addWarning('A Trait cannot be modified or removed from an Experiment once phenotypic data has been associated with it.');

    $build['add_trait'] = [
      '#type' => 'link',
      '#title' => '+ Add Trait',
      '#url' => Url::fromRoute(
        'trpcultivate_phenotypes.experiment_trait_picker',
        [
          'tripal_entity' => $research_experiment->id(),
          'genus' => $filter_genus,
        ],
      ),
      '#prefix' => 'No traits found for this experiment. ',
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => '{"width": 850}',
        'id' => 'tcp-trait-picker-window',
        'style' => 'color: blue; font-weight: 200; text-decoration: underline;',
      ],
    ];

    $summary_table_name = 'experiment_traits_summary_table';
    $form[$summary_table_name] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => [],
      '#sticky' => FALSE,
      '#allowed_tags' => ['a', 'br', 'em', 'def', 'small', 'span', 'select'],
      '#empty' => [
        '#type' => 'link',
        '#title' => 'Add Trait',
        '#url' => Url::fromRoute(
          'trpcultivate_phenotypes.experiment_trait_picker',
          [
            'tripal_entity' => $research_experiment->id(),
            'genus' => $filter_genus,
          ],
        ),
        '#prefix' => 'No traits found for this experiment. ',
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => '{"width": 850}',
          'id' => 'tcp-trait-picker-window',
          'style' => 'color: blue; font-weight: 200; text-decoration: underline;',
        ],
      ],
    ];

    // A request to remove a trait combo.
    // Form buttons used by the remove confirm dialog window.
    $form['#attached']['library'][] = 'core/drupal.dialog';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $request = $this->request_params->getCurrentRequest();
    if ($request->get('action') == 'del' && (int) $request->get('id') > 0) {
      $id = $request->get('id');
      return $this->removeCombo($form, $form_state, (int) $id);
    }

    // Prepare traits that matched the search.
    // Query the list of traits in an experiment. Sort the result first by the
    // genus, cv name (based on the cv_id) and then by trait is_required status
    // value (required traits first) and finally, by trait name alphabetically.
    $query = $this->chado_connection->select(self::PHENO_COMBO_TABLE, 'tc');
    $query->join('1:cvterm', 't', 'tc.attr_id = t.cvterm_id');
    $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

    $query
      ->fields('tc', [
        'combo_id',
        'attr_id',
        'observable_id',
        'unit_id',
        'label',
        'is_archived',
        'is_required',
        'was_shared',
        'was_collected',
      ])
      ->fields('t', ['cv_id', 'name'])
      ->condition('tc.project_id', $experiment_id, '=')
      ->orderBy('v.name', 'ASC')
      ->orderBy('t.name', 'ASC')
      ->orderBy('label', 'ASC');

    if ($filter_genus) {
      $query->condition('t.cv_id', array_search($filter_genus, $genus_map), '=');
    }

    $query_result = $query->execute();

    $set_genus = [];
    $a_group = FALSE;

    foreach ($query_result as $i => $trait_row) {
      $first_row = FALSE;

      $genus = $genus_map[$trait_row->cv_id];
      if (!in_array($genus, $set_genus)) {
        $this->service_PhenoTraits->setTraitGenus($genus);
        array_push($set_genus, $genus);

        $a_group = !$a_group;
        $first_row = TRUE;
      }

      ['trait' => $trait, 'method' => $method, 'unit' => $unit] = $this->service_PhenoTraits->getTraitMethodUnitCombo(
        $trait_row->attr_id,
        $trait_row->observable_id,
        $trait_row->unit_id,
      );

      // Table column: combo label.
      $form[$summary_table_name][$i]['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => ($filter_genus) ? $trait_row->label : $trait_row->label . '<br /><span class="marker" title="Genus">' . $genus . '</span>',
        '#attributes' => [
          'class' => [
            'views-field',
          ],
        ],
      ];

      // Table column: trait combo.
      $form[$summary_table_name][$i]['trait_combo'] = [
        '#type' => 'component',
        '#component' => 'trpcultivate_phenotypes:trait_combo',
        '#slots' => [],
        '#props' => [
          'name' => $trait->name,
          'definition' => $trait->definition,
          'multiselect_method' => FALSE,
          'method_unit_combo' => [
            [
              'method_shortname' => $method->name,
              'unit' => $unit->name,
              'type' => $this->service_PhenoTraits->getMethodUnitDataType($trait_row->unit_id),
              'collection_method' => $method->definition,
            ],
          ],
          'status' => [
            'archived' => $trait_row->is_archived,
            'required' => $trait_row->is_required,
            'shared' => $trait_row->was_shared,
            'collected' => $trait_row->was_collected,
          ],
        ],
      ];

      // Table column: remove action.
      $form[$summary_table_name][$i]['remove'] = [
        '#type' => 'button',
        '#value' => 'Remove',
        '#name' => self::TABLE_ROW_CLASS . $trait_row->combo_id,
        '#id' => $trait_row->combo_id,
        '#disabled' => ($trait_row->is_archived || $trait_row->was_shared || $trait_row->was_collected) ? TRUE : FALSE,
        '#attributes' => [
          'class' => [
            'button',
            'button--small',
          ],
        ],
        '#ajax' => [
          'callback' => '::confirmRemove',
          'event' => 'click',
          'progress' => [
            'type' => 'none',
            'message' => '',
          ],
        ],
      ];

      // To aid grouping of traits by genus, darken the top border of the first
      // row (trait) in the same genus. The initial class is to reference a row
      // for remove trait AJAX callback.
      $group_class = [self::TABLE_ROW_CLASS . $trait_row->combo_id];

      if ($first_row && $i > 0) {
        array_push($group_class, 'tcp-group-border');
      }

      $form[$summary_table_name][$i]['#attributes'] = [
        'class' => implode(' ', $group_class),
      ];
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Function callback - filter trait summary table by a genus.
   */
  public function filterByGenus(array &$form, FormStateInterface $form_state) {

    $params = [
      'tripal_entity' => $form_state->getValue('research_experiment_entity_id'),
    ];

    if ($filter_genus = $form_state->getValue('filter_genus')) {
      $params['genus'] = $filter_genus;
    }

    $url = Url::fromRoute('trpcultivate_phenotypes.experiment_configuration', $params)
      ->toString();

    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($url));

    return $response;
  }

  /**
   * AJAX callback: remove a trait combo from an experiment.
   */
  public function confirmRemove(array &$form, FormStateInterface $form_state) {

    $response = new AjaxResponse();

    $build['confirm'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'Are you sure you want to remove this trait?',
    ];

    $url = Url::fromRoute(
      '<current>',
      [],
      [
        'query' => [
          'action' => 'del',
          'id' => $form_state->getTriggeringElement()['#id'],
        ],
      ],
    );

    $url->setOption(
      'attributes',
      [
        'class' => [
          'use-ajax',
          'button',
          'button--primary',
          'button--danger',
        ],
      ],
    );

    $build['remove'] = [
      '#type' => 'link',
      '#url' => $url,
      '#title' => 'Remove',
    ];

    $build['cancel'] = [
      '#type' => 'button',
      '#value' => 'Cancel',
      '#attributes' => [
        'onclick' => "
          Drupal.dialog(jQuery('#drupal-modal')).close();
          event.preventDefault();
        ",
      ],
    ];

    $response->addCommand(new OpenModalDialogCommand(
      'Confirm Remove',
      $build,
      [
        'width' => 400,
      ]
    ));

    return $response;
  }

  /**
   * AJAX callback: remove a trait combo from an experiment.
   */
  public function removeCombo(array &$form, FormStateInterface $form_state, int $id) {

    $response = new AjaxResponse();
    $response
      ->addCommand(new RemoveCommand('.' . self::TABLE_ROW_CLASS . $id))
      ->addCommand(new CloseModalDialogCommand());

    return $response;
  }

}
