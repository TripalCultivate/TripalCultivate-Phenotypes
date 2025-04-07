<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of phenotypic data backups.
 */
final class PhenodataBackupListBuilder extends ConfigEntityListBuilder implements FormInterface {

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
   * Users.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $user;

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
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Drupal users.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    FormBuilderInterface $form_builder,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $user,
  ) {
    parent::__construct($entity_type, $storage);

    $this->service_EntityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->user = $user;
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
      $container->get('current_user'),
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
    $project_id = (int) $entity->get('project_id');

    if ($project_id) {
      $project_name = ChadoProjectAutocompleteController::getProjectName($project_id);
      $row['project_id'] = [];
      $row['project_id']['data'] = [
        '#markup' => '<a target="_blank" href="/experiment/' . str_replace(' ', '-', $project_name) . '">' . $project_name . '</a>',
      ];
    }

    // Comment is optional, display this character if not supplied.
    $row['comments'] = '--';
    $comments = $entity->get('comments');

    if (!empty($comments)) {
      $row['comments'] = [];
      $row['comments']['data'] = [
        '#markup' => '<small>' . $comments . '</small>',
      ];
    }

    $row['backup_date'] = $entity->get('backup_date');

    // Provide a download button.
    $row['file_id'] = '';
    $file_id = $entity->get('file_id');

    if ($file_id) {
      $file_obj = $this->service_EntityTypeManager
        ->getStorage('file')
        ->load($file_id);

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

    // Only delete is available.
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

    // The order is important.
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
    // Panels tend to stick, apply some margin.
    $form['#attributes']['style'] = 'margin-bottom: 20px';

    // Populate the select field with project names.
    $project_names = [];
    $list = $this->storage->loadMultiple();

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

    $project_id = (int) $form_state->getValue('project_id');

    if ($project_id > 0) {
      $project_name = ChadoProjectAutocompleteController::getProjectName($project_id);

      if (empty($project_name)) {
        $form_state->setErrorByName('project_id', 'The project is not recognized. Please select a project and try again.');
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $project_id = $form_state->getValue('project_id');

    $query_string = ($project_id == 0) ? [] : ['query' => ['project_id' => strip_tags($project_id)]];
    $query_url = Url::fromRoute('<current>', [], $query_string);
    $form_state->setRedirectUrl($query_url);
  }

  /**
   * {@inheritDoc}
   */
  public function load() {

    // Restrict the listing to the backup file of a user.
    $entity_ids = $this->getEntityListQuery()
      ->condition('user_id', $this->user->id(), '=')
      ->execute();

    $entities = $this->storage
      ->loadMultiple($entity_ids);

    // Further restrict to project when requested.
    $filter_project_id = \Drupal::request()
      ->get('project_id', 0);

    if (!preg_match('/^\d+$/', (string) $filter_project_id)) {
      // If filter value is not valid, default to load all.
      $filter_project_id = 0;
    }

    if ($filter_project_id > 0) {
      $entities = array_filter($entities, function ($e) use ($filter_project_id) {
        return $e->get('project_id') == $filter_project_id;
      });
    }

    // Always sort by date backed up, latest first.
    if ($entities) {
      usort($entities, [self::class, 'sortByDate']);
    }

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
