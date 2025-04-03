<?php

declare(strict_types=1);

namespace Drupal\trpcultivate_phenotypes;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Drupal\tripal_chado\Controller\ChadoProjectAutocompleteController;

/**
 * Provides a listing of phenotypic data backups.
 */
final class PhenodataBackupListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {

    $header['file_id'] = $this->t('Data File');
    $header['project_id'] = $this->t('Project/Experiment');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {

    // Create a button to download the backup data file.
    $row['file_id'] = '--';
    $file_id = $entity->get('file_id');

    if ($file_id) {
      $file_obj = File::load($file_id[0]);
      if ($file_obj) {
        $url = $file_obj->createFileUrl();

        $row['file_id'] = [];
        $row['file_id']['data']['download'] = [
          '#markup' => '<a class="button" target="_blank" href="' . $url . '">Download File</a>',
        ];
      }
    }

    $row['project_id'] = '--';
    $project_id = $entity->get('project_id');

    if ($project_id) {
      $project_name = ChadoProjectAutocompleteController::getProjectName($project_id);
      $row['project_id'] = $project_name;
    }

    return $row + parent::buildRow($entity);
  }

}
