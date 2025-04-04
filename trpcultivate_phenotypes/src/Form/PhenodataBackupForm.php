<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Phenotypic Data Backup form.
 */
final class PhenodataBackupForm extends EntityForm {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $service_EntityTypeManager;

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInteface
   */
  protected ConfigFactoryInterface $service_ConfigFactory;

  /**
   * Users.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $user;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory service.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Drupal users.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $user,
  ) {

    $this->service_EntityTypeManager = $entity_type_manager;
    $this->service_ConfigFactory = $config_factory;
    $this->user = $user;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $project_name = $form_state->getValue('project_name');

    // Pull the project id from the project value.
    preg_match('/\((\d+)\)$/', $project_name, $matches);

    $invalid_project = FALSE;

    if (!isset($matches[1])) {
      $invalid_project = TRUE;
    }
    else {
      $project_id = (int) trim($matches[1]);
      $project_name = ChadoProjectAutocompleteController::getProjectName($project_id);

      if (empty($project_name)) {
        $invalid_project = TRUE;
      }
    }

    if ($invalid_project) {
      $form_state->setErrorByName('project_name', 'The project is not recognized. Please select a project and try again.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    // Entity configuration ID number.
    $form['id'] = [
      '#type' => 'hidden',
      '#default_value' => $this->entity->id(),
      '#value' => uniqid(),
    ];

    $config_backup_dir = $this->service_ConfigFactory
      ->get('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.directory.data_backup');
    $backup_dir = $config_backup_dir ?? 'public:://phenotype-backups/';

    $form['backup_file'] = [
      '#type' => 'managed_file',
      '#title' => 'Data File',
      '#description' => $this->t('Select data file to backup. Only tsv and txt file extensions are allowed.'),
      '#upload_location' => $backup_dir,
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'tsv txt',
        ],
      ],
    ];

    // Restrict project autocomplete suggestions to project used as
    // research_experiment Tripal content type.
    $entity_type = $this->service_EntityTypeManager
      ->getStorage('tripal_entity_type')
      ->load('research_experiment');

    $type_id = 0;
    if ($entity_type) {
      $entity_type_term_internalid = $entity_type->getTerm()
        ->getInternalId();

      // 0 value will suggest all projects.
      $type_id = $entity_type_term_internalid;
    }

    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => 'Project/Experiment Name',
      '#description' => $this->t('Select the project name the data file is specific to.'),
      '#description_display' => 'after',
      '#required' => TRUE,
      '#attributes' => ['placeholder' => 'Project/Experiment Name'],
      '#autocomplete_route_name' => 'tripal_chado.generic_autocomplete',
      '#autocomplete_route_parameters' => [
        'type_id' => $type_id,
        'match_limit' => 5,
        'base_table' => 'project',
        'column_name' => 'name',
        'type_column' => 'type_id',
        'property_table' => 'project',
      ],
    ];

    $form['comments'] = [
      '#type' => 'textarea',
      '#title' => 'Notes/Comments',
      '#description' => $this->t('Notes or comments about the data file.'),
      '#rows' => '5',
      '#resizable' => FALSE,
      '#maxlength' => 200,
    ];

    $form['backup_date'] = [
      '#type' => 'hidden',
      '#value' => date('Y-m-d H:i:s'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {

    $backup_file = $form_state->getValue('backup_file');

    if (!empty($backup_file)) {
      $file_obj = $this->service_EntityTypeManager
        ->getStorage('file')
        ->load($backup_file[0]);

      if ($file_obj) {
        // @todo Rename the file.
        $file_obj->setPermanent();
        $file_obj->save();

        $this->entity->set('file_id', $file_obj->id());
      }
    }

    $project_name = $form_state->getValue('project_name');

    preg_match('/\((\d+)\)$/', $project_name, $matches);
    if (isset($matches[1])) {
      $this->entity->set('project_id', trim($matches[1]));
    }

    $this->entity->set('backup_date', date('Y-M-d H:i:s'));

    $this->entity->set('user_id', $this->user->id());

    $this->entity->save();
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => 'Phenotype Data File Backup'];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
