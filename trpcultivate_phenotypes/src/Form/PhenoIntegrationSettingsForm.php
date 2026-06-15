<?php

namespace Drupal\trpcultivate_phenotypes\Form;

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
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   A Database query interface for querying Chado using Tripal DBX.
   *
   * @param \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings $service_PhenoIntegration
   *   Phenotypes Integration settings service.
   */
  public function __construct(
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

    $content_type_integrations = $this->service_PhenoIntegration->getPhenoIntegratedContentTypes();
    $project_content_types = $this->service_PhenoIntegration->getProjectBasedContentTypes();

    $integration_metadata = [
      'pheno_backup_support' => [
        'title' => 'Phenotypic Data File Backup',
        'description' => 'Choose the Content Types you would like to support phenotypic data file backups. This will add a tab beside Edit on pages of this type that links to the Phenotypic Data Backup page.',
      ],
      'pheno_combo_support' => [
        'title' => 'Configure trait-method-unit Combinations for Data Import',
        'description' => 'Choose the Content Types you would like to suppor phenotypic data import. This will add a tab beside Edit on pages of this type allowing you to indicate what type of phenotypic data will be collected for this project.',
      ],
    ];

    $form['integration_detail'] = [
      '#type' => 'details',
      '#title' => 'Integration with Project-based Pages',
      '#open' => TRUE,
    ];

    foreach ($content_type_integrations as $integration_config => $content_types) {
      $field_metadata = $integration_metadata[$integration_config];

      $form['integration_detail'][$integration_config] = [
        '#type' => 'select',
        '#title' => $field_metadata['title'],
        '#description' => $field_metadata['description'],
        '#required' => TRUE,
        '#options' => $project_content_types,
        '#default_value' => $content_types,
        '#empty_option' => 'Please select content types',
        '#empty_value' => 0,
        '#multiple' => TRUE,
        '#size' => 10,
      ];
    }

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

    // With reference to all project_ids in the phenotypes-combo table,
    // determine if an entity of a specific bundle returns a content. This will
    // indicate that a bundle has a project content with pheno-combo record.
    $exp_with_pheno = $this->chado_connection->select('trpcultivate_phenocombo', 'tp')
      ->distinct()
      ->fields('tp', ['project_id'])
      ->execute()
      ->fetchCol();

    // This only applies to pheno_combo integration.
    $pheno_combo_support = $this->service_PhenoIntegration::INTEGRATION_CONFIG['pheno_combo'];
    $pheno_combo_values = $form_state->getValue($pheno_combo_support);

    $project_bundles = array_keys($this->service_PhenoIntegration->getProjectBasedContentTypes());

    foreach ($exp_with_pheno as $project_id) {
      foreach ($project_bundles as $bundle) {

        // Trigger an error if it could not locate the bundle (with pheno) in
        // the field selected values.
        if ($this->tripal_entity_lookup->getEntityIdFromRecordId($project_id, $bundle, 'tripal_entity') &&
            !in_array($bundle, $pheno_combo_values)
          ) {

          $form_state->setErrorByName(
            $pheno_combo_support,
            'The bundle ' . $bundle  . ' has associated phenotypes and must be a selected item in Data Import integration.'
          );
        }
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
