<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
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
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $research_experiment;

  /**
   * The genus used to filter the traits table and show only related traits.
   *
   * @var int|string
   */
  private int|string $filter_genus;

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

    $form['form_wrapper'] = [
      '#type' => 'container',
      '#id' => 'tcp-add-trait-form',
    ];

    $form['form_wrapper']['reminder'] = [
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

    $form['genus'] = [
      '#type' => 'select',
      '#title' => 'Genus',
      '#options' => [1 => 'Lens'],

    ];

    $form['search_trait'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => 'Trait',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
