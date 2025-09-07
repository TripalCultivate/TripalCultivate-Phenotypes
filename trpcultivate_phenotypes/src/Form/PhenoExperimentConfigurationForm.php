<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Entity\TripalEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Research experiment id.
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
   */
  public function __construct(
    ChadoConnection $chado_connection,
    RouteMatchInterface $route_match,
  ) {

    $this->chado_connection = $chado_connection;
    $this->research_experiment = $route_match->getParameter('tripal_entity');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('tripal_chado.database'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'content_bio_data_experiment_configure_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->messenger()
      ->addWarning('A Trait cannot be modified or removed from an Experiment once phenotypic data has been associated with it.');

    // Update the title to show which reseach experiment is being setup.
    $form['#title'] = 'Phenotypes: ' . $this->research_experiment->label();

    $label_tip = 'A short experiment-specific label referring to this Trait-Method-Unit combination. This will be used in the data collection file and must be unique within this experiment.';

    $traits_table = [
      '#type' => 'table',
      '#header' => [
        [
          'data' => [
            '#markup' => 'label <span alt="' . $label_tip . '" title="' . $label_tip . '">
              <svg id="pheno-label-help-icon" height="16" width="16">
                <circle cx="8" cy="8" r="8" />
                <text x="8" y="12" text-anchor="middle" font-size="11" font-family="Arial" fill="#FFFFFF">?</text>
              </svg>
            </span>',
            '#allowed_tags' => ['span', 'svg', 'circle', 'text'],
          ],
        ],
        [
          'data' => 'Trait-Method-Unit',
          'style' => 'width: 75%',
        ],
        [
          'data' => 'Remove',
          'style' => 'width: 1%',
        ],
      ],
      '#attributes' => [
        'id' => 'pheno-experiment-traits-summary-table',
      ],
    ];

    // Get experiment/project id slug value.
    // Query all traits.
    // Resove ids.
    $traits_table['#rows'] = [
      [
        'DTF @50%',
        'Days to Flower DTF50 (cm) Quantitative - Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut eu urna ultrices, tincidunt tellus id, semper elit. Ut fringilla ex ut molestie efficitur. Nunc eros mi, luctus sit amet arcu non, efficitur consequat enim',
        'x',
      ],
    ];

    $form['pheno_experiment_traits_summary_table'] = $traits_table;

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
