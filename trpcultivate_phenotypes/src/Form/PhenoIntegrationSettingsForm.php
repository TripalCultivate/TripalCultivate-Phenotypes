<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tripal\Services\TripalEntityLookup;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form to manage Phenotypes Integration settings.
 */
class PhenoIntegrationSettingsForm extends ConfigFormBase {

  const SETTINGS = 'trpcultivate_phenotypes.settings';

  /**
   * Pheno backup integration.
   *
   * @var string
   */
  const PHENO_BACKUP_INTEGRATION = 'backup';

  /**
   * Pheno combo integration.
   *
   * @var string
   */
  const PHENO_COMBO_INTEGRATION = 'pheno_combo';

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $service_EntityTypeManager
   *   Drupal Entity Type Manager service.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   A Database query interface for querying Chado using Tripal DBX.
   * @param \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings $service_PhenoIntegration
   *   Phenotypes Integration settings service.
   * @param \Drupal\tripal\Services\TripalEntityLookup $tripal_entity_lookup
   *   Tripal Entity lookup service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $service_EntityTypeManager,
    protected ChadoConnection $chado_connection,
    protected PhenoIntegrationSettings $service_PhenoIntegration,
    protected TripalEntityLookup $tripal_entity_lookup,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('tripal_chado.database'),
      $container->get('trpcultivate_phenotypes.pheno_integration'),
      $container->get('tripal.tripal_entity.lookup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'trpcultivate_phenotypes_pheno_integration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {

    return [static::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->messenger()->addWarning(
      'Content types associated with phenotypic data or data file backup (marked with *) must remain selected in both integration multi-select fields.'
    );

    $form['integration_detail'] = [
      '#type' => 'details',
      '#title' => 'Integration with Project-based Pages',
      '#open' => TRUE,
    ];

    // Integration multi-select field metadata.
    $integration_field_metadata = [
      self::PHENO_BACKUP_INTEGRATION => [
        'title' => 'Phenotypic Data File Backup',
        'description' => 'Choose the Content Types you would like to support phenotypic data file backups. This will add a tab beside Edit on pages of this type that links to the Phenotypic Data Backup page.',
      ],
      self::PHENO_COMBO_INTEGRATION => [
        'title' => 'Configure trait-method-unit Combinations for Data Import',
        'description' => 'Choose the Content Types you would like to support phenotypic data import. This will add a tab beside Edit on pages of this type allowing you to indicate what type of phenotypic data will be collected for this project.',
      ],
    ];

    $project_content_types = $this->service_PhenoIntegration->getProjectBasedContentTypes();

    // Save protected content type results for use in other stage.
    $protected_content_types = $this->getProtectedContentTypes();
    $form_state->set('protected_content_types', $protected_content_types);

    foreach ($this->service_PhenoIntegration->getPhenoIntegrations() as $integration) {
      $field_options = $project_content_types;
      $marked_types = $protected_content_types[$integration] ?? [];

      // Update the content type to show machine name items and mark protected
      // items with * symbol.
      array_walk($field_options, function (&$name, $key) use ($marked_types) {
        $name = $name . ' [' . $key . ']' . (in_array($key, $marked_types) ? '*' : '');
      });

      $form['integration_detail'][$integration] = [
        '#type' => 'select',
        '#title' => $integration_field_metadata[$integration]['title'],
        '#description' => $integration_field_metadata[$integration]['description'],
        '#options' => $field_options,
        '#required' => TRUE,
        '#multiple' => TRUE,
        '#size' => min(10, count($field_options)),
        '#default_value' => $this->service_PhenoIntegration->getPhenoIntegratedContentTypes($integration),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Ensure protected content types remain selected.
    $protected_content_types = $form_state->get('protected_content_types');

    foreach ($this->service_PhenoIntegration->getPhenoIntegrations() as $integration) {
      $marked_types = $protected_content_types[$integration] ?? [];

      if (array_diff($marked_types, array_keys($form_state->getValue($integration)))) {
        $form_state->setErrorByName(
          '',
          'Content types marked with asterisk symbol (*) have associated phenotypes and must be selected.'
        );

        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    foreach ($this->service_PhenoIntegration->getPhenoIntegrations() as $integration) {
      $this->service_PhenoIntegration->setPhenoIntegratedContentTypes(
        $integration,
        array_values($form_state->getValue($integration))
      );
    }
  }

  /**
   * Get protected (with data associated) content types.
   *
   * @return array
   *   A list of content types that are associated with phenotypes or data file
   *   backups. Content types are grouped and keyed by integration identifier.
   */
  protected function getProtectedContentTypes(): array {

    // Get experiments with associated backup data file or pheno-combo records.
    $backup_entities = $this->service_EntityTypeManager
      ->getStorage('phenodata_backup')
      ->loadMultiple();

    $exp_with_backup = [];
    foreach ($backup_entities as $backup_entity) {
      $exp_with_backup[] = $backup_entity->get('project_id');
    }

    unset($backup_entities);

    $exp_with_pheno = $this->chado_connection->select('trpcultivate_phenocombo', 'tp')
      ->fields('tp', ['project_id'])
      ->distinct()
      ->execute()
      ->fetchCol();

    // All unique project_ids with data associated.
    $exp_with_data = array_unique(
      array_merge($exp_with_backup, $exp_with_pheno)
    );

    $protected_content_types = [];
    $content_type_names = array_keys(
      $this->service_PhenoIntegration->getProjectBasedContentTypes()
    );

    foreach ($exp_with_data as $project_id) {
      foreach ($content_type_names as $content_type) {

        if (!$this->tripal_entity_lookup->getEntityIdFromRecordId($project_id, $content_type, 'tripal_entity')) {
          continue;
        }

        // If the project_id record (that has phenotypes) has entity object
        // and/or has backup data file, then save/protect the content type.
        if (in_array($project_id, $exp_with_backup)) {
          $protected_content_types[self::PHENO_BACKUP_INTEGRATION][] = $content_type;
        }

        if (in_array($project_id, $exp_with_pheno)) {
          $protected_content_types[self::PHENO_COMBO_INTEGRATION][] = $content_type;
        }
      }
    }

    return $protected_content_types;
  }

}
