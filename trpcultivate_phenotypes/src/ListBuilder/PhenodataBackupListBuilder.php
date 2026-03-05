<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\ListBuilder;

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
use Drupal\tripal\Services\TripalEntityLookup;
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
   * Tripal Entity lookup.
   *
   * @var Drupal\tripal\Services\TripalEntityLookup
   */
  protected TripalEntityLookup $service_TripalEntityLookup;

  /**
   * Configuration entity fields per permission.
   *
   * @var array
   */
  private $entity_field_header = [];

  /**
   * An array of backup the user has access to.
   *
   * @var array
   */
  private $user_backup = [];

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
   * @param \Drupal\tripal\Services\TripalEntityLookup $tripalentity_lookup
   *   Tripal entity lookup service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    FormBuilderInterface $form_builder,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $user,
    TripalEntityLookup $tripalentity_lookup,
  ) {
    parent::__construct($entity_type, $storage);

    $this->service_EntityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->user = $user;

    $headers = [
      'project_id' => $this->t('Research Experiment'),
      'comments' => $this->t('Notes/Comments'),
      'backup_date' => $this->t('Date Created'),
      'file_id' => $this->t('Data File'),
      'user_id' => $this->t('Created By'),
    ];

    $has_view_all = $this->user->hasPermission('view_all phenodata_backup');
    $has_view_own = $this->user->hasPermission('view_own phenodata_backup');

    if ($has_view_all || $this->user->hasPermission('administer phenodata_backup')) {
      $this->entity_field_header = $headers;
    }
    elseif ($has_view_own) {
      unset($headers['user_id']);
      $this->entity_field_header = $headers;
    }

    // Construct list of backups the user has access to.
    $query = $this->getEntityListQuery();

    if (!isset($this->entity_field_header['user_id'])) {
      $query
        ->condition('user_id', $this->user->id());
    }

    $entity_ids = $query->sort('backup_date', 'DESC')->execute();
    $this->user_backup = $this->storage->loadMultiple($entity_ids);

    $this->service_TripalEntityLookup = $tripalentity_lookup;
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
      $container->get('tripal.tripal_entity.lookup'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {

    return $this->entity_field_header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {

    $values = [];

    $key = 'project_id';
    $project_id = (int) $entity->get($key);
    $project_name = ChadoProjectAutocompleteController::getProjectName($project_id);

    $entity_id = $this->service_TripalEntityLookup
      ->getEntityId($project_id, 'SIO', '000994', 'project');

    $values[$key]['data'] = $this->service_TripalEntityLookup
      ->getRenderableItem($project_name, $entity_id) + [
        '#attributes' => ['target' => '_blank'],
      ];

    $key = 'comments';
    $comments = $entity->get($key);
    $values[$key]['data'] = [
      '#type' => 'html_tag',
      '#tag' => 'small',
      '#value' => empty($comments) ? '--' : $comments,
    ];

    $key = 'backup_date';
    $values[$key] = date('Y-M-d H:i:s', strtotime($entity->get($key)));

    $key = 'file_id';
    $file_id = $entity->get($key);
    $file_obj = $this->service_EntityTypeManager
      ->getStorage('file')
      ->load($file_id);

    if ($file_obj) {
      $values[$key]['data'] = [
        '#type' => 'button',
        '#value' => 'Download',
        '#button_type' => 'primary',
        '#attributes' => [
          'onClick' => 'window.location.href="' . $file_obj->createFileUrl() . '"; return false;',
        ],
      ];
    }

    $key = 'user_id';
    $user_id = $entity->get($key);
    $user_obj = $this->service_EntityTypeManager
      ->getStorage('user')
      ->load($user_id);

    if ($user_obj) {
      $username = $user_obj->getAccountName();
      $values[$key]['data'] = [
        '#type' => 'html_tag',
        '#tag' => 'i',
        '#value' => $username,
      ];
    }

    // Based on the headers required, construct the values for each header.
    $row = [];
    foreach (array_keys($this->entity_field_header) as $field) {
      $row[] = $values[$field] ?? '--';
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritDoc}
   */
  public function getOperations(EntityInterface $entity) {

    // Only update is allowed.
    $operation = [];

    if ($entity->access('edit', $this->user, TRUE)) {
      $operation['edit'] = [
        'title' => 'Update',
        'url' => $entity->toUrl('edit-form'),
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
  public function load() {

    $user_backup = $this->user_backup;

    $filter_project_id = \Drupal::request()
      ->query
      ->get('project_id', 0);

    // Any attempt to mangle with the query string will just default to
    // load all projects.
    if (!preg_match('/^\d+$/', (string) $filter_project_id)) {
      $filter_project_id = 0;
    }

    // A request to filter user backups to limit to a specific project.
    if ($filter_project_id > 0) {
      $user_backup = array_filter($user_backup, function ($entity) use ($filter_project_id) {
        return $entity->get('project_id') == $filter_project_id;
      });

      // Filter resulted in an empty value indicates that the project as filter
      // criterion does not exist or user lacks access permission to view
      // backups in that project.
      if (empty($user_backup)) {
        $this->messenger()->addError(
          $this->t('The Research Experiment is not recognized or you do not have permission to see backups for it.')
        );
      }
    }

    return $user_backup;
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

    $project_names = [
      0 => '- Any - ',
    ];

    foreach ($this->user_backup as $entity) {
      $project_id = (int) $entity->get('project_id');

      if (!in_array($project_id, array_keys($project_names))) {
        $project_names[$project_id] = ChadoProjectAutocompleteController::getProjectName($project_id);
      }
    }
    asort($project_names);

    $filter_project_id = \Drupal::request()
      ->query
      ->get('project_id', 0);

    $form['project_id'] = [
      '#type' => 'select',
      '#title' => $this->entity_field_header['project_id'],
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
        $form_state->setErrorByName('project_id', $this->t(
          'The @project is not recognized or you do not have permission to see backups for it.',
          ['@project' => $this->entity_field_header['project_id']])
        );
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

}
