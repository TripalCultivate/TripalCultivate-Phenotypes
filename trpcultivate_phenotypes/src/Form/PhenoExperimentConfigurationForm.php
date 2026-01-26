<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Ajax\AjaxResponse;
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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class definition of Experiment Phenotypes Configuration page.
 */
class PhenoExperimentConfigurationForm extends FormBase {

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
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database_connection
   *   Drupal database connection.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
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
    Connection $database_connection,
    ChadoConnection $chado_connection,
    TripalLogger $tripal_logger,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesGenusProjectService $service_PhenoGenusProject,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->database_connection = $database_connection;
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

    return 'pheno_experiment_configuration_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tripal_entity = $this->getRouteMatch()->getParameter('tripal_entity');

    // Route validates instance of tripal_entity and presence of exp_name field.
    $experiment = $tripal_entity->get('exp_name')->getValue();
    ['record_id' => $experiment_id, 'value' => $experiment_name] = $experiment[0];

    // Update the title to show which reseach experiment is being configured.
    $form['#title'] = 'Configure Phenotypes for ' . $experiment_name;

    $form['tripal_entity_id'] = [
      '#type' => 'hidden',
      '#value' => $tripal_entity->id(),
    ];

    // If the /genus slug is not provided, show all trait for all genus.
    $genus = $this->getRouteMatch()->getParameter('genus') ?: 0;

    $invalid_genus = 0;
    $exp_germgenus = $tripal_entity->get('exp_germgenus')->getValue();

    foreach ($exp_germgenus as $germgenus) {
      if (!$this->service_PhenoGenusOntology->getGenusOntologyConfigValues($germgenus['value'])) {
        $invalid_genus++;
      }
    }

    if ($invalid_genus == count($exp_germgenus)) {
      $this->messenger()->addError('The Research Experiment has no configured genus set.');

      return $form;
    }

    $exp_phenogenus = $this->service_PhenoGenusProject->getGenusOfProject((int) $experiment_id);
    if ($genus && !in_array($genus, $exp_phenogenus)) {
      // Genus does not exist.
      $this->tripal_logger->error('The genus is not supported by the experiment.');
      throw new NotFoundHttpException();
    }

    // Create a mapping array to map cv name to a genus and populate the genus
    // filter select field with phenotypes configured genus options.
    $genus_map = [];
    foreach ($exp_phenogenus as $phenogenus) {
      $cv_id = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($phenogenus)['trait'];
      $genus_map[$cv_id] = $phenogenus;
    }

    // Determine if the page will default to a genus or all genus.
    if (count($exp_phenogenus) == 1) {
      $genus = $exp_phenogenus[0];
    }

    // Contain page links and select field displayed inline - in a single row.
    $config_toolbar = 'config_toolbar';
    $form[$config_toolbar] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
        ],
        'style' => 'text-align: right; margin: 0 0 10px 0',
      ],
    ];

    $form[$config_toolbar]['add_trait'] = [
      '#type' => 'link',
      '#title' => 'Add Trait',
      '#suffix' => ' &nbsp;&nbsp; ',
      '#url' => Url::fromRoute(
        'trpcultivate_phenotypes.experiment_traitselector',
        [
          'tripal_entity' => $tripal_entity->id(),
          'genus' => $genus,
        ],
      ),
      '#ajax' => [
        'dialogType' => 'modal',
        'dialog' => [
          'width' => 850,
          'dialogClass' => 'tcp-no-close',
          'closeText' => 'Close Trait Selector window',
        ],
        'progress' => [
          'type' => 'fullscreen',
          'message' => '',
        ],
      ],
    ];

    $form[$config_toolbar]['refresh'] = [
      '#type' => 'link',
      '#title' => 'Refresh Table',
      '#url' => Url::fromRoute('<current>'),
    ];

    $form[$config_toolbar]['slash'] = [
      '#markup' => '&nbsp;&nbsp; / &nbsp;',
    ];

    $form[$config_toolbar]['filter_icon'] = [
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#value' => '',
      '#attributes' => [
        'class' => [
          'fa-solid',
          'fa-filter',
        ],
        'title' => 'Filter the trait summary table by a genus',
      ],
    ];

    $form[$config_toolbar]['filter_genus'] = [
      '#type' => 'select',
      '#options' => array_combine($genus_option = array_values($genus_map), $genus_option),
      '#empty_option' => 'All Genus',
      '#empty_value' => 0,
      '#default_value' => $genus,
      '#theme_wrappers' => [],
      '#ajax' => [
        'callback' => '::loadGenusTraits',
        'event' => 'change',
        'progress' => [
          'type' => 'fullscreen',
          'message' => '',
        ],
      ],
    ];

    // Prepare traits summary table render array.
    $this->messenger()
      ->addWarning('A Trait cannot be modified or removed from an Experiment once phenotypic data has been associated with it.');

    $table_header = [
      'label' => [
        'data' => '',
        'style' => 'width: 15%',
      ],
      'combo' => [
        'data' => 'Trait Method Unit',
        'style' => 'width: 84%',
      ],
      'operations' => [
        'data' => 'Operations',
        'style' => 'width: 1%;',
      ],
    ];

    $table_header['label']['data'] = [
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#prefix' => 'Label',
      '#value' => '',
      '#attributes' => [
        'class' => [
          'fa-solid',
          'fa-circle-question',
        ],
        'title' => 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment',
      ],
    ];

    $summary_table_name = 'experiment_traits_summary_table';

    $form[$summary_table_name] = [
      '#type' => 'table',
      '#header' => $table_header,
      '#rows' => [],
      '#sticky' => FALSE,
      '#allowed_tags' => ['a', 'br', 'em', 'def', 'small', 'span', 'section'],
      '#empty' => array_merge(
        $form['config_toolbar']['add_trait'],
        [
          '#attributes' => [
            'style' => 'color: blue; font-weight: 200; text-decoration: underline;',
          ],
          '#prefix' => 'No traits found for this research experiment: ',
        ]
      ),
    ];

    // With trait combos being added to this table, ensure that form will render
    // the most up-to-date listings rather than a cached snapshot.
    $form[$summary_table_name]['#cache'] = ['max-age' => 0];

    // AJAX library dependencies.
    $form['#attached']['library'][] = 'core/drupal.dialog';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // Listen for operation request to remove, require, or archive.
    $request = $this->getRequest();

    if ($request->getMethod() == 'GET') {
      foreach (['remove', 'require', 'optional', 'active', 'archive'] as $action) {
        if ((int) $request->get($action) > 0) {
          $combo_id = $request->get($action);
          $this->handleOperation($action, $experiment_id, $combo_id);

          break;
        }
      }
    }

    // Prepare traits that matched the trait search key.
    // Result is groupped by genus.
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

    if ($genus) {
      $query->condition('t.cv_id', array_search($genus, $genus_map), '=');
    }

    $query_result = $query->execute();

    $set_genus = [];
    $a_group = FALSE;

    foreach ($query_result as $i => $trait_row) {
      $first_row = FALSE;

      $trait_genus = $genus_map[$trait_row->cv_id];
      if (!in_array($trait_genus, $set_genus)) {
        $this->service_PhenoTraits->setTraitGenus($trait_genus);
        array_push($set_genus, $trait_genus);

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
        '#tag' => 'span',
        '#value' => ($genus) ? $trait_row->label : $trait_row->label . '<br /><span class="marker" title="Genus">' . $trait_genus . '</span>',
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

      // Table column: trait combo operations.
      $is_removable = ($trait_row->is_archived || $trait_row->was_shared || $trait_row->was_collected)
        ? FALSE : TRUE;

      $form[$summary_table_name][$i]['operations'] = [
        '#type' => 'dropbutton',
        '#dropbutton_type' => 'small',
        '#links' => [
          'remove' => [
            'title' => 'Remove',
            'url' => Url::fromRoute('<current>', [], [
              'query' => [
                'remove' => $trait_row->combo_id,
              ],
              'attributes' => [
                'onclick' => 'return ' . ($is_removable ? 'confirm("Are you sure you want to Remove trait?")' : 'false'),
                'style' => 'pointer-events: ' . ($is_removable ? 'auto' : 'none') . '; opacity: ' . ($is_removable ? 1 : 0.3),
              ],
            ]),
          ],
          'require' => [
            'title' => 'Set ' . $status = ($trait_row->is_required ? 'Optional' : 'Require'),
            'url' => Url::fromRoute('<current>', [], [
              'query' => [
                strtolower($status) => $trait_row->combo_id,
              ],
              'attributes' => [
                'onclick' => 'return confirm("Are you sure you want to set status to ' . ucfirst($status) . '?")',
              ],
            ]),
          ],
          'archive' => [
            'title' => 'Set ' . $status = ($trait_row->is_archived ? 'Active' : 'Archive'),
            'url' => Url::fromRoute('<current>', [], [
              'query' => [
                strtolower($status) => $trait_row->combo_id,
              ],
              'attributes' => [
                'onclick' => 'return confirm("Are you sure you want to set status to ' . ucfirst($status) . '?")',
              ],
            ]),
          ],
        ],
      ];

      // Helps group traits by genus - the first row of each genus has thicker
      // top border style rule.
      $form[$summary_table_name][$i]['#attributes'] = [
        'style' => 'border-top: ' . (($first_row && $i > 0) ? '8px solid #DADADA;' : 'inherit;') ,
        'valign' => 'top',
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
   * Handle operation request.
   *
   * @param string $action
   *   One of - remove, require, optional, active, or archive.
   * @param int $project_id
   *   The project id the combo id is specific to.
   * @param int $combo_id
   *   The combo id to apply an action to.
   */
  public function handleOperation(string $action, int $project_id, int $combo_id): void {

    $combo = $this->database_connection->select(self::PHENO_COMBO_TABLE, 'tc')
      ->fields('tc', ['combo_id', 'is_archived', 'was_shared', 'was_collected'])
      ->condition('tc.combo_id', $combo_id, '=')
      ->condition('tc.project_id', $project_id, '=')
      ->execute()
      ->fetchObject();

    if (!$combo) {
      $this->messenger()
        ->addError($message = 'Invalid request: Could not find trait combo.');
      throw new AccessDeniedHttpException($message);
    }

    $ok = 0;

    switch ($action) {

      case 'remove':

        if ($combo->is_archived == 1 || $combo->was_shared == 1 || $combo->was_collected == 1) {
          $this->messenger()
            ->addError($message = 'Invalid request: Not allowed to remove trait marked archived, shared, or collected.');
          throw new AccessDeniedHttpException($message);
        }

        $transaction = $this->database_connection->startTransaction();
        try {
          $ok = $this->chado_connection
            ->delete(self::PHENO_COMBO_TABLE)
            ->condition('combo_id', $combo->combo_id, '=')
            ->execute();
        }
        catch (\Exception $e) {
          $transaction->rollback();

          $this->tripal_logger->error($msg = 'Invalid request: Failed to remove trait from experiment.');
          $this->messenger()->addStatus($msg);
        }

        break;

      case 'require':
      case 'optional':
      case 'active':
      case 'archive':

        $field_map = [
          'require' => 'is_required',
          'optional' => 'is_required',
          'active' => 'is_archived',
          'archive' => 'is_archived',
        ];

        $status = (in_array($action, ['require', 'archive'])) ? 1 : 0;

        $transaction = $this->database_connection->startTransaction();
        try {
          $ok = $this->database_connection
            ->update(self::PHENO_COMBO_TABLE)
            ->fields([
              $field_map[$action] => $status,
            ])
            ->condition('combo_id', $combo->combo_id, '=')
            ->condition($field_map[$action], $status, '<>')
            ->execute();
        }
        catch (\Exception $e) {
          $transaction->rollback();

          $this->tripal_logger->error($msg = 'Invalid request: Failed to update trait status.');
          $this->messenger()->addStatus($msg);
        }

        break;
    }

    if ($ok === 1) {
      $this->messenger()
        ->addStatus('The trait combo operation ' . ucfirst($action) . ' completed successfully.');
    }
  }

  /**
   * Function callback - load traits specific to a genus.
   */
  public function loadGenusTraits(array &$form, FormStateInterface $form_state) {

    $params = [
      'tripal_entity' => $form_state->getValue('tripal_entity_id'),
      'genus' => $form_state->getValue('filter_genus'),
    ];

    if (!$params['genus']) {
      unset($params['genus']);
    }

    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand(
      Url::fromRoute('trpcultivate_phenotypes.experiment_configuration', $params, ['query' => []])
        ->toString()
    ));

    return $response;
  }

}
