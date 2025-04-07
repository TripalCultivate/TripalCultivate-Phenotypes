<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes;

use Drupal\file\Entity\File;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of phenotypic data backups.
 */
final class PhenodataBackupListBuilder extends ConfigEntityListBuilder implements FormInterface {

  /**
   * Number of item per page (override default 50).
   *
   * @var int
   */
  protected $limit = 10;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $service_EntityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\From\FormBuilderInterface $form_builder
   *   The form builder interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    FormBuilderInterface $form_builder,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($entity_type, $storage);

    $this->service_EntityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritDoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {

    $header = [];

    $header['project_id'] = $this->t('Project/Experiment');
    $header['comments'] = $this->t('Notes/Comments');
    $header['backup_date'] = $this->t('Date Created');
    $header['file_id'] = $this->t('Data File');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {

    $row = [];

    $row['project_id'] = '';
    $project_id = $entity->get('project_id');

    if ($project_id) {
      $project_name = ChadoProjectAutocompleteController::getProjectName((int) $project_id);
      $row['project_id'] = [];
      $row['project_id']['data'] = [
        '#markup' => '<a target="_blank" href="/experiment/' . str_replace(' ', '-', $project_name) . '">' . $project_name . '</a>',
      ];
    }

    $row['comments'] = '--';
    $comments = $entity->get('comments');

    if (!empty($comments)) {
      $row['comments'] = [];
      $row['comments']['data'] = [
        '#markup' => '<small>' . $comments . '</small>',
      ];
    }

    $row['backup_date'] = $entity->get('backup_date');

    $row['file_id'] = '';
    $file_id = $entity->get('file_id');

    if ($file_id) {
      $file_obj = File::load($file_id[0]);
      if ($file_obj) {
        $url = $file_obj->createFileUrl();

        $row['file_id'] = [];
        $row['file_id']['data'] = [
          '#markup' => '<a class="button button--primary" target="_blank" href="' . $url . '">Download</a>',
        ];
      }
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritDoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operation = [];

    if ($entity->access('delete')) {
      $operation['delete'] = [
        'title' => 'Delete',
        'url' => $entity->toUrl('delete-form'),
      ];
    }

    return $operation;
  }

  /**
   * {@inheritDoc}
   */
  public function render() {

    $build = [];

    $build['filter_form'] = $this->formBuilder->getForm($this);

    $build['table'] = parent::render();

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'phenodata_backup_display_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = [];
    $form['#attributes']['style'] = 'margin-bottom: 20px';

    // Populate the select field with all the project names
    // available in the list.
    $project_names = [];
    $list = $this->service_EntityTypeManager
      ->getStorage('phenodata_backup')
      ->loadMultiple();

    $project_names = [
      0 => '- Any - ',
    ];

    foreach ($list as $entity) {
      $project_id = (int) $entity->get('project_id');
      $project_names[$project_id] = ChadoProjectAutocompleteController::getProjectName($project_id);
    }

    $filter_project_id = \Drupal::request()
      ->get('project_id', 0);

    $form['project_id'] = [
      '#type' => 'select',
      '#title' => 'Project or Experiment Name',
      '#options' => $project_names,
      '#attributes' => [
        'style' => 'width: 100%',
      ],
      '#default_value' => $filter_project_id,
    ];

    $form['actions'] = [
      '#tree' => FALSE,
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Filter',
      '#button_type' => 'default',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $project_id = $form_state->getValue('project_id');

    $query_url = Url::fromRoute('<current>', [], ['query' => ['project_id' => $project_id]]);
    $form_state->setRedirectUrl($query_url);
  }

  /**
   * {@inheritDoc}
   */
  public function load() {

    $entities = parent::load();

    $filter_project_id = \Drupal::request()
      ->get('project_id', 0);

    if (!preg_match('/^\d+$/', (string) $filter_project_id)) {
      // If mangled filter value is not valid, default to load all.
      $filter_project_id = 0;
    }

    if ($filter_project_id > 0) {
      $entities = array_filter($entities, function ($e) use ($filter_project_id) {
        return $e->get('project_id') == $filter_project_id;
      });
    }

    // Always sort by date backed up, latest first.
    usort($entities, [self::class, 'sortByDate']);

    return $entities;
  }

  /**
   * Sort by backup date.
   *
   * @param object $a
   *   First item for comparison.
   * @param object $b
   *   Second item for comparison.
   *
   * @return object
   *   Entity config object.
   */
  public static function sortByDate($a, $b) {
    $key = 'backup_date';

    $date_1 = strtotime($a->get($key));
    $date_2 = strtotime($b->get($key));

    // Same, keep order.
    if ($date_1 == $date_2) {
      return 0;
    }

    // Date 1 should be prior to date 2.
    return ($date_1 > $date_2) ? -1 : 1;
  }

}
