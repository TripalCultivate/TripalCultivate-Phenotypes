<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tripal\TripalImporter\PluginManagers\TripalImporterManager;
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
   * Tripal importer.
   *
   * @var \Drupal\tripal\TripalImporter\PluginManagers\TripalImporterManager
   */
  protected $tripal_importer;

  /**
   * Users.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $user;

  /**
   * Form mode.
   *
   * @var string
   */
  private string $form_mode;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory service.
   * @param \Drupal\tripal\TripalImporter\PluginManagers\TripalImporterManager $tripal_importer_manager
   *   Tripal importer plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Drupal users.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    TripalImporterManager $tripal_importer_manager,
    AccountInterface $user,
  ) {

    $this->service_EntityTypeManager = $entity_type_manager;
    $this->service_ConfigFactory = $config_factory;
    $this->tripal_importer = $tripal_importer_manager;
    $this->user = $user;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('tripal.importer'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $entity = $this->getEntity();

    // Entity configuration ID number.
    $form['id'] = [
      '#type' => 'hidden',
      '#default_value' => $entity->id(),
      '#value' => uniqid(),
    ];

    if ($entity->isNew()) {
      // Synchronize the file types defined by the importer that will be the
      // main source of future backup data collection file.
      $importer_plugin_definition = $this->tripal_importer
        ->getDefinition('trpcultivate-phenotypes-share-importer');
      $importer_file_extension = implode(' ', $importer_plugin_definition['file_types']);

      $config_backup_dir = $this->service_ConfigFactory
        ->get('trpcultivate_phenotypes.settings')
        ->get('trpcultivate.phenotypes.directory.data_backup');
      $backup_dir = $config_backup_dir ?? 'public:://phenotype-backups/';

      $form['backup_file'] = [
        '#type' => 'managed_file',
        '#title' => 'Data File',
        '#description' => $this->t('Select data file to backup. Only [@ext] file extensions are allowed.', ['@ext' => $importer_file_extension]),
        '#upload_location' => $backup_dir,
        '#multiple' => FALSE,
        '#required' => TRUE,
        '#upload_validators' => [
          'FileExtension' => [
            'extensions' => $importer_file_extension,
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
    }
    else {
      // Ensure that user can only modify data file that they own.
      if ($entity->get('user_id') != $this->user->id()) {
        $form['not_my_backup'] = [
          '#markup' => 'This data file backup does not belong to this account.',
        ];

        return $form;
      }

      $file_id = $entity->get('file_id');
      $file_obj = $this->service_EntityTypeManager
        ->getStorage('file')
        ->load($file_id);

      $url = $file_obj->createFileUrl();
      $filename = $file_obj->getFileName();

      $project_id = (int) $entity->get('project_id');
      $project_name = ChadoProjectAutocompleteController::getProjectName($project_id);

      $form['backup_file'] = [
        '#markup' => '<p>Data File: <a target="_blank" href="' . $url . '">' . $filename . '</a><br />' .
        'Project/Experiment: ' . $project_name . '</p>',
      ];
    }

    $form['comments'] = [
      '#type' => 'textarea',
      '#title' => 'Notes/Comments',
      '#description' => $this->t('Notes or comments about the data file.'),
      '#default_value' => $entity->get('comments'),
      '#rows' => '5',
      '#resizable' => FALSE,
      '#maxlength' => 200,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $entity = $this->getEntity();

    if ($entity->isNew()) {
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
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {

    $entity = $this->getEntity();

    if ($entity->isNew()) {
      $filename_components = [];

      // Extract the project id from the value returned by
      // autocomplete field (Project Name (Id)).
      $project_name = $form_state->getValue('project_name');
      preg_match('/\((\d+)\)$/', $project_name, $matches);
      $project_id = trim($matches[1]);
      if (isset($project_id)) {
        $this->entity->set('project_id', $project_id);
      }
      $filename_components[] = $project_id;

      $user_id = $this->user->id();
      $this->entity->set('user_id', $user_id);
      $filename_components[] = $user_id;

      $backup_date = date('Y-M-d H:i:s');
      $this->entity->set('backup_date', $backup_date);
      $filename_components[] = $backup_date;

      $backup_file = $form_state->getValue('backup_file');
      if (!empty($backup_file)) {
        $file_obj = $this->service_EntityTypeManager
          ->getStorage('file')
          ->load($backup_file[0]);

        if ($file_obj) {
          $file_uri = $file_obj->getFileUri();
          $file_filename = $file_obj->getFileName();
          $new_file_name = implode('_', $filename_components) . '.' . pathinfo($file_filename, PATHINFO_EXTENSION);
          $new_file_uri = str_replace($file_filename, $new_file_name, $file_uri);
          rename($file_uri, $new_file_uri);

          $file_obj->setFileName($new_file_name);
          $file_obj->setFileUri($new_file_uri);
          $file_obj->setPermanent();
          $file_obj->save();

          $this->entity->set('file_id', $file_obj->id());
        }
      }
    }

    $this->entity->save();
    $result = parent::save($form, $form_state);

    $this->messenger()->addStatus('Created/Updated Phenodata Backup');
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {

    $element = $this->actions($form, $form_state);
    $entity = $this->getEntity();

    // The listing does not provide a delete action, catch the delete button
    // in edit mode and remove it.
    if (isset($element['delete'])) {
      unset($element['delete']);
    }

    // A user is attempting to modify an entity that belongs to someone else.
    if (!$entity->isNew() && $entity->get('user_id') != $this->user->id()) {
      // Submit == Save.
      unset($element['submit']);
    }

    return $element;
  }

}
