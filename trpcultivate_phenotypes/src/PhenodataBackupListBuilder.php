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
use Drupal\file\Entity\File;
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
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\From\FormBuilderInterface $form_builder
   *   The form builder interface.
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
      ->getListBuilder('phenodata_backup')
      ->load();

    $project_names = [
      0 => 'Select Project Name',
    ];

    foreach ($list as $entity) {
      $project_id = (int) $entity->get('project_id');
      $project_names[$project_id] = ChadoProjectAutocompleteController::getProjectName($project_id);
    }

    $form['project_name'] = [
      '#type' => 'select',
      '#options' => $project_names,
      '#attributes' => [
        'style' => 'width: 100%',
      ],
    ];

    $form['actions'] = [
      '#tree' => FALSE,
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Apply Filter',
      '#button_type' => 'primary',
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
  }

}
