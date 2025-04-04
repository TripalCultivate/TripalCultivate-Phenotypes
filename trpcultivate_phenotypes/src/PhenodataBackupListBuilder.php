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

    $header['project_id'] = $this->t('Project/Experiment');
    $header['comments'] = $this->t('Notes/Comments');
    $header['backup_date'] = $this->t('Date Created');
    $header['file_id'] = $this->t('Data File');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {

    $row['project_id'] = '--';
    $project_id = $entity->get('project_id');

    if ($project_id) {
      $project_name = ChadoProjectAutocompleteController::getProjectName((int) $project_id);
      $row['project_id'] = [];
      $row['project_id']['data'] = [
        '#markup' => '<a target="_blank" href="/experiment/' . str_replace(' ', '-', $project_name) . '">' . $project_name . '</a>',
      ];
    }

    $row['comments'] = '--';
    $comments = $entity->get('comments');

    if (!empty($comments)) {
      $row['comments'] = [];
      $row['comments']['data'] = [
        '#markup' => '<small>' . $comments . '</small>',
      ];
    }

    $row['backup_date'] = $entity->get('backup_date');

    $row['file_id'] = '--';
    $file_id = $entity->get('file_id');

    if ($file_id) {
      $file_obj = File::load($file_id[0]);
      if ($file_obj) {
        $url = $file_obj->createFileUrl();

        $row['file_id'] = [];
        $row['file_id']['data'] = [
          '#markup' => '<a class="button" target="_blank" href="' . $url . '">Download File</a>',
        ];
      }
    }

    return $row + parent::buildRow($entity);
  }

}
