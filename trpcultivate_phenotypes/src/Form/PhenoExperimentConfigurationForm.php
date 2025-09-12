<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Entity\TripalEntity;
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
   * Genus-ontotology service.
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
  private TripalEntity $research_experiment;

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

    $experiment_id = $this->research_experiment->getID();
    if (!$experiment_id) {
      throw new NotFoundHttpException();
    }

    $this->messenger()
      ->addWarning('A Trait cannot be modified or removed from an Experiment once phenotypic data has been associated with it.');

    // Update the title to show which reseach experiment is being setup.
    $form['#title'] = 'Phenotypes: ' . $this->research_experiment->label();

    // Setup traits summary listing table.
    $headers = [];
    $summary_table_name = 'experiment_traits_summary_table';

    $headers = [
      'label' => [
        'data' => [
          '#type' => 'component',
          '#component' => 'trpcultivate_phenotypes:help_text',
          '#slots' => [],
          '#props' => [
            'label' => 'Label',
            'help_text' => 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment.',
          ],
        ],
      ],
      'trait_combo' => [
        'data' => [
          '#type' => 'select',
          '#options' => [0 => 'Filter by genus'],
          '#default_value' => 0,
          '#theme_wrappers' => [],
          '#prefix' => '<span>Trait Method Unit: </span><span>',
          '#suffix' => '</span>',
        ],
        'style' => 'width: 75%;',
      ],
      'remove' => [
        'data' => 'Remove',
        'style' => 'width: 1%;',
      ],
    ];

    $form[$summary_table_name] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => [],
      '#empty' => 'No traits found',
      '#sticky' => FALSE,
      '#allowed_tags' => ['br', 'em', 'def', 'svg', 'small', 'span', 'text', 'circle', 'select'],
      '#attributes' => [
        'id' => 'tcp-experiment-traits-summary-table',
      ],
    ];

    $experiment_genus = $this->service_PhenoGenusProject->getGenusOfProject($experiment_id);
    if (!$experiment_genus) {
      $this->messenger()->addError('The Research Experiment has either no genus set or has one but is not properly configured.');
      return $form;
    }

    // Create a mapping array to map cv name to a genus, set a group colour, and
    // populate the genus filter select field.
    $genus_map = [];
    foreach ($experiment_genus as $i => $g) {
      $cv_id = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($g)['trait'];

      $genus_map[$cv_id] = [
        'genus' => $g,
        'bg' => ($i % 2) ? '#F7F7F7' : '#FFFFFF',
      ];

      $form[$summary_table_name]['#header']['trait_combo']['data']['#options'][$g] = $g;
    }

    $rows = [];

    $query = $this->chado_connection->select('trpcultivate_phenocombo', 'tc');
    $query->join('1:cvterm', 't', 'tc.attr_id = t.cvterm_id');
    $query->join('1:cv', 'v', 't.cv_id = v.cv_id');

    $query
      ->fields('tc', ['combo_id', 'attr_id', 'observable_id', 'unit_id', 'label'])
      ->fields('t', ['cv_id'])
      ->condition('tc.project_id', $this->research_experiment->getID(), '=')
      ->orderBy('v.name', 'ASC')
      ->orderBy('is_required', 'DESC')
      ->orderBy('label', 'ASC');

    $query_result = $query->execute();

    $set_genus = [];
    foreach ($query_result as $trait_row) {
      // Set genus.
      $genus = $genus_map[$trait_row->cv_id]['genus'];
      if (!in_array($genus, $set_genus)) {
        $this->service_PhenoTraits->setTraitGenus($genus);
        array_push($set_genus, $genus);
      }

      ['trait' => $trait, 'method' => $method, 'unit' => $unit] = $this->service_PhenoTraits->getTraitMethodUnitCombo(
        $trait_row->attr_id,
        $trait_row->observable_id,
        $trait_row->unit_id,
      );

      $rows[] = [
        'data' => [
          [
            'data' => [
              '#markup' => $trait_row->label . '<br /><small>' . $genus . '</small>',
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
              ],
            ],
          ],
          'x',
        ],
        'style' => 'background-color:' . $genus_map[$trait_row->cv_id]['bg'],
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
