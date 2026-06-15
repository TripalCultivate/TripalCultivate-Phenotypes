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
   * Class constructor.
   *
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $service_EntityTypeManager
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
      'Any content type associated with Phenotypic data must remain selected in Phenotypes Data Import integration multi-select field.'
    );

    $form['integration_detail'] = [
      '#type' => 'details',
      '#title' => 'Integration with Project-based Pages',
      '#open' => TRUE,
    ];

    // Identify content type that has phenotypes associated.
    // With reference to all project_ids in the phenotypes-combo table,
    // determine if an entity of a specific bundle returns a content. This will
    // indicate that a bundle has a project content with pheno-combo record.
    $exp_with_pheno = $this->chado_connection->select('trpcultivate_phenocombo', 'tp')
      ->distinct()
      ->fields('tp', ['project_id'])
      ->execute()
      ->fetchCol();

    $project_bundles = array_keys($this->service_PhenoIntegration->getProjectBasedContentTypes());
    $project_backups_storage = $this->service_EntityTypeManager->getStorage('phenodata_backup');

    // Protected (has associated data) content types in both integrations.
    $protected_in_backups = [];
    $protected_in_phenocombo = [];

    foreach ($exp_with_pheno as $project_id) {
      foreach ($project_bundles as $bundle) {

        $has_entity = $this->tripal_entity_lookup->getEntityIdFromRecordId($project_id, $bundle, 'tripal_entity');

        if ($has_entity && $project_backups_storage->loadByProperties(['project_id' => $project_id])) {
          array_push($protected_in_backups, $bundle);
        }

        if ($has_entity) {
          array_push($protected_in_phenocombo, $bundle);
        }
      }
    }

    $form_state->set('proected_in_backups', $protected_in_backups);
    $form_state->set('proected_in_backups', $protected_in_phenocombo);

    $content_type_integrations = $this->service_PhenoIntegration->getPhenoIntegratedContentTypes();
    $project_content_types = $this->service_PhenoIntegration->getProjectBasedContentTypes();

    $backup_config = $this->service_PhenoIntegration::INTEGRATION_CONFIG['backup'];

    // Mark protected content types with asterisk.
    $backup_options = $project_content_types;
    array_walk($backup_options, function(&$name, $config) use ($protected_in_backups) {
      if (in_array($config, $protected_in_backups)) {
        $name = '*' . $name;
      }
    });

    $form['integration_detail'][$backup_config] = [
      '#type' => 'select',
      '#title' => 'Phenotypic Data File Backup',
      '#description' => 'Choose the Content Types you would like to support phenotypic data file backups.
        This will add a tab beside Edit on pages of this type that links to the Phenotypic Data Backup page.',
      '#required' => TRUE,
      '#options' => $backup_options,
      '#default_value' => $content_type_integrations[$backup_config],
      '#empty_option' => 'Please select content types',
      '#empty_value' => 0,
      '#multiple' => TRUE,
      '#size' => count($project_content_types)
    ];

    $pheno_combo_config = $this->service_PhenoIntegration::INTEGRATION_CONFIG['pheno_combo'];

    // Mark protected content types with asterisk.
    $phenocombo_options = $project_content_types;
    array_walk($phenocombo_options, function(&$name, $config) use ($protected_in_phenocombo) {
      if (in_array($config, $protected_in_phenocombo)) {
        $name = '*' . $name;
      }
    });

    $form['integration_detail'][$pheno_combo_config] = [
      '#type' => 'select',
      '#title' => 'Configure trait-method-unit Combinations for Data Import',
      '#description' => 'Choose the Content Types you would like to suppor phenotypic data import.
        This will add a tab beside Edit on pages of this type allowing you to indicate what type of phenotypic data will be collected for this project.',
      '#required' => TRUE,
      '#options' => $phenocombo_options,
      '#default_value' => $content_type_integrations[$pheno_combo_config],
      '#empty_option' => 'Please select content types',
      '#empty_value' => 0,
      '#multiple' => TRUE,
      '#size' => count($project_content_types)
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Comfiguration'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // This only applies to pheno_combo integration.
    $pheno_combo_support = $this->service_PhenoIntegration::INTEGRATION_CONFIG['pheno_combo'];
    $pheno_combo_values = $form_state->getValue($pheno_combo_support);

    $project_bundles = array_keys($this->service_PhenoIntegration->getProjectBasedContentTypes());

    $protected_content_types = $form_state->get('protected_in_phenocombo');
    foreach ($protected_content_types as $content_type) {
      if (!in_array($content_type, $pheno_combo_values)) {
        $form_state->setErrorByName(
          $pheno_combo_support,
          'The content type ' . $name . ' has associated phenotypes and must be a selected item in Data Import integration.'
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    foreach ($this->service_PhenoIntegration::INTEGRATION_CONFIG as $integration => $integration_config) {
      $this->service_PhenoIntegration->setPhenoIntegratedContentTypes(
        $integration,
        array_values($form_state->getValue($integration_config))
      );
    }
  }

}
