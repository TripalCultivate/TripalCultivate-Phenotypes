<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;
use Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings;

/**
 * Phenotypic Data Backup form.
 *
 * NOTE: this form supports Phenotypes Integration and is set to 'backup'
 * integration. Experiment selection is limited to content types configured for
 * backup integration.
 * @see PhenoBackupForm::form().
 */
final class PhenodataBackupForm extends EntityForm {

  /**
   * The phenotypes integration this form is specific to.
   *
   * @var string
   */
  const PHENO_BACKUP_INTEGRATION = 'backup';

  /**
   * File types supported.
   *
   * @var array
   *   A list of the supported file endings.
   */
  private static array $BACKUP_FILE_TYPES = [
    'tsv',
    'csv',
    'txt',
    'xlsx',
  ];

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
   * Phenotypes integration service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\PhenoIntegraionSettings
   */
  protected PhenoIntegrationSettings $service_PhenoIntegration;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory service.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Drupal users.
   * @param \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings $service_PhenoIntegration
   *   Phenotypes integration service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $user,
    PhenoIntegrationSettings $service_PhenoIntegration,
  ) {

    $this->service_EntityTypeManager = $entity_type_manager;
    $this->service_ConfigFactory = $config_factory;
    $this->user = $user;
    $this->service_PhenoIntegration = $service_PhenoIntegration;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('trpcultivate_phenotypes.pheno_integration'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $entity = $this->getEntity();

    // Check permissions.
    $view_own = $this->user->hasPermission('view_own phenodata_backup');
    $view_all = $this->user->hasPermission('view_all phenodata_backup');
    $admin = $this->user->hasPermission('administer phenodata_backup');
    // Confirm user should have access to create a backup / edit their own.
    // Access denied if they have none of these permissions.
    if (!$view_own && !$view_all && !$admin) {
      throw new AccessDeniedHttpException();
    }
    // Confirm user should have access to edit someone elses backup.
    if (!$entity->isNew() && $entity->get('user_id') != $this->user->id()) {
      if ($this->user->hasPermission('view_all phenodata_backup') || $this->user->hasPermission('administer phenodata_backup')) {
        // The user can view or modify all backups.
        $this->messenger()
          ->addWarning('This data file belongs to another user. Please consider notifying the owner before making significant updates.');
      }
      else {
        // The user can only view or modify own backups. Unauthorized access.
        throw new AccessDeniedHttpException();
      }
    }

    // Entity configuration ID number.
    $form['id'] = [
      '#type' => 'hidden',
      '#default_value' => $entity->id(),
      '#value' => uniqid(),
    ];

    $importer_file_extension = implode(' ', self::$BACKUP_FILE_TYPES);

    $config_backup_dir = $this->service_ConfigFactory
      ->get('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.phenotypes.directory.data_backup');
    $backup_dir = $config_backup_dir ?? 'public:://phenotype-backups/';

    $form['backup_file'] = [
      '#type' => 'managed_file',
      '#title' => 'Data File',
      '#description' => $this->t('Select data file to backup. Only [@ext] file extensions are allowed. The maximum file size allowed is <strong>@size</strong>. If your file exceeds this size, please contact your administrator.', [
        '@ext' => $importer_file_extension,
        '@size' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
      ]),
      '#upload_location' => $backup_dir,
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => $importer_file_extension,
        ],
      ],
    ];

    if (!$entity->isNew()) {
      $fids = $entity->get('file_id');
      $fids = (is_string($fids)) ? [$fids] : $fids;

      $form['backup_file']['#value'] = ['fids' => $fids];
      $form['backup_file']['#disabled'] = TRUE;
    }

    // Restrict project autocomplete suggestions to projects of content type
    // that match one of content types in backup integration.
    $supported_content_types = $this->service_PhenoIntegration
      ->getPhenoIntegratedContentTypes(self::PHENO_BACKUP_INTEGRATION);

    $entity_type_storage = $this->service_EntityTypeManager
      ->getStorage('tripal_entity_type');

    $content_type_ids = [];
    foreach ($supported_content_types as $content_type) {
      $content_type_ids[] = $entity_type_storage
        ->load($content_type)
        ->getTerm()
        ->getInternalId();
    }

    $content_type_ids = implode(',', $content_type_ids);

    $project_id = (int) $entity->get('project_id');
    $project_name = ($project_id) ? ChadoProjectAutocompleteController::getProjectName($project_id) : '';
    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => 'Research Experiment',
      '#description' => $this->t('<strong>WARNING: Research Experiment cannot be changed later.</strong> Please ensure that you are selecting the specific experiment this data file was generated for. If your research experiment is not listed or if you are unsure, please contact your curator or site administrator.'),
      '#description_display' => 'after',
      '#required' => TRUE,
      '#attributes' => ['placeholder' => 'Experiment Name'],
      '#autocomplete_route_name' => 'tripal_chado.generic_autocomplete',
      '#autocomplete_route_parameters' => [
        'type_id' => $content_type_ids,
        'match_limit' => 5,
        'base_table' => 'project',
        'column_name' => 'name',
        'type_column' => 'type_id',
        'property_table' => 'project',
      ],
      '#default_value' => ($project_id) ? "$project_name ($project_id)" : NULL,
      '#disabled' => ($project_id) ? TRUE : FALSE,
    ];
    // Add an extra warning about the experiment not being changable if this
    // is a new backup only.
    if (!$entity->isNew()) {
      $form['project_name']['#description'] = $this->t('The research experiment of an existing backup cannot be changed. If you made a mistake, please contact your curator or site administrator.');
    }

    $form['comments'] = [
      '#type' => 'textarea',
      '#title' => 'Notes/Comments',
      '#description' => $this->t('Notes or comments about the data file.'),
      '#default_value' => $entity->get('comments'),
      '#rows' => '5',
      '#resizable' => FALSE,
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
        $form_state->setErrorByName('project_name', 'The Research Experiment name is not recognized. Start by typing slowly, select the correct one from the dropdown and try saving again.');
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

      $backup_date = date('Y-m-d H:i:s');
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

    // The listing does not provide a delete action, catch the delete button
    // in edit mode and remove it.
    if (isset($element['delete'])) {
      unset($element['delete']);
    }

    // A user is attempting to modify an entity that belongs to someone else.
    if (isset($form['unauthorized'])) {
      // Submit == Save.
      unset($element['submit']);
    }

    return $element;
  }

}
