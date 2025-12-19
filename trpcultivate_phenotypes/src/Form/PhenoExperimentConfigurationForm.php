<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\core\Url;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusProjectService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->chado_connection = $chado_connection;
    $this->route_match = $route_match;
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
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.genus_project'),
      $container->get('trpcultivate_phenotypes.traits'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'content_bio_data_research_experiment_configure_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $research_experiment = $this->route_match->getParameter('tripal_entity');

    $experiment = $research_experiment->get('exp_name')->getValue();
    if (!$experiment) {
      // Research experiment entity does not exist.
      throw new NotFoundHttpException();
    }
    ['record_id' => $experiment_id, 'value' => $experiment_name] = $experiment[0];

    // If the /genus slug is not provided, show all trait.
    $filter_genus = $this->route_match->getParameter('genus') ?? 0;

    // Update the title to show which reseach experiment is being setup.
    $form['#title'] = 'Configure Phenotypes for ' . $experiment_name;

    // Form buttons used by the remove confirm dialog window.
    $form['#attached']['library'][] = 'core/drupal.dialog';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // Prepare traits summary table render array.
    $summary_table_name = 'experiment_traits_summary_table';

    // The second item of the header row has a select field element to filter
    // trait table by a genus.
    $headers = [];
    $headers = [
      'label' => [
        'data' => [
          '#markup' => 'Label <i class="fa-solid fa-circle-question" title="A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment."></i>',
        ],
      ],
      'trait_combo' => [
        'data' => [
          '#type' => 'select',
          '#options' => [0 => 'All Genus'],
          '#value' => $filter_genus,
          '#theme_wrappers' => [],
          '#prefix' => '<span>Trait Method Unit: </span><span>',
          '#suffix' => '</span>',
          '#attributes' => [
            'id' => 'tcp-filter-trait-table-by-genus',
          ],
        ],
      ],
      'remove' => 'Remove',
    ];

    $form[$summary_table_name] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => [],
      '#empty' => 'No traits found',
      '#sticky' => FALSE,
      '#allowed_tags' => ['br', 'em', 'def', 'small', 'span', 'select'],
      '#attributes' => [
        'id' => 'tcp-experiment-traits-summary-table',
      ],
    ];

    // Ensure research experiment has at least one configured genus.
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
      throw new NotFoundHttpException();
    }

    $this->messenger()
      ->addWarning('A Trait cannot be modified or removed from an Experiment once phenotypic data has been associated with it.');

    // Create a mapping array to map cv name to a genus and populate the genus
    // filter select field with available genus options.
    $genus_map = [];
    foreach ($experiment_genus as $genus) {
      $cv_id = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus)['trait'];

      $genus_map[$cv_id] = $genus;
      $form[$summary_table_name]['#header']['trait_combo']['data']['#options'][$genus] = $genus;
    }

    // Determine if the page will default to a genus or all genus.
    if (count($experiment_genus) == 1) {
      $single_genus = $experiment_genus[0];

      $form[$summary_table_name]['#header']['trait_combo']['data']['#value'] = $single_genus;
      // From the Phenotypes tab, the url is /configure, update to include the
      // the default genus - /configure/genus.
      $form['#attached']['drupalSettings']['tcpSettings']['genus'] = $single_genus;
      $filter_genus = $single_genus;
    }

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

      // Table column values:
      // Column label.
      $form[$summary_table_name]['combo' . $trait_row->combo_id]['label'] = [
        '#type' => '#markup',
        '#markup' => ($filter_genus) ? $trait_row->label : $trait_row->label . '<br /><small>' . $genus . '</small>',
      ];

      // The trait combo.
      $form[$summary_table_name]['combo' . $trait_row->combo_id]['trait_combo'] = [
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

      // A remove button.
      $form[$summary_table_name]['combo' . $trait_row->combo_id]['remove'] = [
        '#type' => 'button',
        '#value' => 'Remove',
        '#name' => self::TABLE_ROW_CLASS . $trait_row->combo_id,
        '#disabled' => ($trait_row->is_archived || $trait_row->was_shared || $trait_row->was_collected) ? TRUE : FALSE,
        '#attributes' => [
          'class' => [
            'button--primary',
          ],
          'data-trait-combo' => $trait_row->combo_id,
        ],
        '#ajax' => [
          'callback' => '::confirmRemove',
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
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

      $form[$summary_table_name]['combo' . $trait_row->combo_id]['#attributes'] = [
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
   * AJAX callback: dialog confirm removal of a trait.
   */
  public function confirmRemove(array &$form, FormStateInterface $form_state) {

    // Remove a row from DOM.
    $triggering_element = $form_state->getTriggeringElement();

    if (isset($triggering_element)) {
      $combo_id = (int) trim($triggering_element['#attributes']['data-trait-combo']);

      if ($combo_id > 0) {
        $response = new AjaxResponse();

        $build['actions']['confirm'] = [
          '#markup' => '<p>Are you sure you want to remove this trait?</p>',
        ];

        $url = Url::fromRoute(
          'trpcultivate_phenotypes.experiment_handle_trait',
          [
            'action' => 'remove',
          ],
          [
            'query' => [
              'combo_id' => $combo_id,
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

        $build['actions']['remove'] = [
          '#type' => 'link',
          '#url' => $url,
          '#title' => 'Remove',
        ];

        $build['actions']['cancel'] = [
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
          $build['actions'],
          [
            'width' => 300,
          ]
        ));
      }
    }

    return $response;
  }

}
