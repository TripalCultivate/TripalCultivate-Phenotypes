<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Phenotypic Data Backup form.
 */
final class PhenodataBackupForm extends EntityForm {

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

    $form['backup_file'] = [
      '#type' => 'managed_file',
      '#title' => 'Data File',
      '#description' => $this->t('Select data file to backup. Only tsv and txt file extensions are allowed.'),
      '#upload_location' => 'public://phenotype-backups/',
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'tsv txt',
        ],
      ],
    ];

    $form['project'] = [
      '#type' => 'textfield',
      '#title' => 'Project/Experiment Name',
      '#description' => $this->t('Select the project name the data file is specific to.'),
      '#description_display' => 'after',
      '#required' => TRUE,
      '#attributes' => ['placeholder' => 'Project/Experiment Name'],
      '#autocomplete_route_name' => 'tripal_chado.generic_autocomplete',
      '#autocomplete_route_parameters' => [
        'type_id' => 0,
        'match_limit' => 5,
        'base_table' => 'project',
        'column_name' => 'name',
        'type_column' => 'x',
        'property_table' => 'project',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {

    $backup_file = $form_state->getValue('backup_file');

    if (!empty($backup_file)) {
      $file_obj = FILE::load($backup_file[0]);

      if ($file_obj) {
        // @todo Rename the file.
        $file_obj->setPermanent();
        $file_obj->save();

        $this->entity->set('file_id', $file_obj->id());
      }
    }

    $project = $form_state->getValue('project');

    preg_match('/\((\d+)\)$/', $project, $matches);
    if (isset($matches[1])) {
      $this->entity->set('project_id', trim($matches[1]));
    }

    $result = parent::save($form, $form_state);
    // $message_args = ['%label' => $this->entity->label()];
    // $this->messenger()->addStatus(
    //   match($result) {
    //     \SAVED_NEW => $this->t('Created new example %label.', $message_args),
    //     \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
    //   }
    // );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
