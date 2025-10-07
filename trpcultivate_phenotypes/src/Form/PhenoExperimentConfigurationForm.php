<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
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
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private $research_experiment;

  /**
   * The genus used to filter the traits table and show only related traits.
   *
   * @var string
   */
  private $filter_genus;

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
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
    $this->service_PhenoGenusProject = $service_PhenoGenusProject;
    $this->service_PhenoTraits = $service_PhenoTraits;

    $this->research_experiment = $route_match->getParameter('tripal_entity');

    // If the /genus slug is not provided, show all trait.
    $filter_genus = $route_match->getParameter('genus');
    $this->filter_genus = ($filter_genus == '') ? 0 : $filter_genus;
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

    $experiment = $this->research_experiment->get('exp_name')->getValue();
    if (!$experiment) {
      // Research experiment entity does not exist.
      throw new NotFoundHttpException();
    }

    ['record_id' => $experiment_id, 'value' => $experiment_name] = $experiment[0];

    // Update the title to show which reseach experiment is being setup.
    $form['#title'] = 'Phenotypes: ' . $experiment_name;

    // Prepare traits summary table render array.
    $form['#attached']['library'][] = 'trpcultivate_phenotypes/trpcultivate-phenotypes-experiment-configuration';
    $summary_table_name = 'experiment_traits_summary_table';

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
          '#value' => $this->filter_genus,
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
    $exp_genus = $this->research_experiment->get('exp_germgenus')->getValue();

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

    $experiment_genus = $this->service_PhenoGenusProject->getGenusOfProject($experiment_id);
    if ($this->filter_genus && !in_array($this->filter_genus, $experiment_genus)) {
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
      $this->filter_genus = $single_genus;
    }

    $rows = [];

    // Query the list of traits in an experiment. Sort the result first by the
    // genus, cv name (based on the cv_id) and then by trait is_required status
    // value (required traits first) and finally, by trait name alphabetically.
    $query = $this->chado_connection->select('trpcultivate_phenocombo', 'tc');
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

    if ($this->filter_genus) {
      $query->condition('t.cv_id', array_search($this->filter_genus, $genus_map), '=');
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

      // To aid grouping of traits by genus, darken the top border of the first
      // row (trait) in the same genus and apply shade to the group.
      $group_class = [];
      array_push($group_class, ($a_group) ? 'tcp-group-shade-light' : 'tcp-group-shade-dark');
      if ($first_row && $i > 0) {
        array_push($group_class, 'tcp-group-border');
      }

      // Use the trait status to disable the remove option.
      $remove = 'x';
      if ($trait_row->is_archived || $trait_row->was_shared || $trait_row->was_collected) {
        $remove = '-';
      }

      $rows[] = [
        'data' => [
          [
            'data' => [
              '#markup' => ($this->filter_genus) ? $trait_row->label : $trait_row->label . '<br /><small>' . $genus . '</small>',
            ],
          ],
          [
            'data' => [
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
            ],
          ],
          $remove,
        ],
        'class' => implode(' ', $group_class),
      ];
    }

    // Populate table rows array.
    $form[$summary_table_name]['#rows'] = $rows;

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
