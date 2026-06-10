<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * @param \Drupal\trpcultivate_phenotypes\Service\PhenoIntegrationSettings $service_PhenoIntegration
   *   Phenotypes Integration settings service.
   */
  public function __construct(protected PhenoIntegrationSettings $service_PhenoIntegration) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static($container->get('trpcultivate_phenotypes.pheno_integration'));
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
    // $project_content_types = $this->service_PhenoIntegration->getProjectBasedContentTypes();

    $test_options = [
      'research_experiment' => 'research_experiment',
      'research_study' => 'research_study',
    ];

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

    foreach ($content_type_integrations as $integration => $content_types) {
      $form['integration_detail'][$integration] = [
        '#type' => 'select',
        '#title' => $integration_metadata[$integration]['title'],
        '#description' => $integration_metadata[$integration]['description'],
        '#required' => TRUE,
        '#options' => $test_options,
        '#empty_option' => 'Please select content types',
        '#empty_value' => 0,
        '#multiple' => TRUE,
        '#default_value' => 0,
        '#size' => 10,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Comfiguration'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
