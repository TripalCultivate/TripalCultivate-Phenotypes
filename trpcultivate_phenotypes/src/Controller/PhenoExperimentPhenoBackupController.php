<?php

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class definition of PhenoExperimentPhenoBackupController.
 */
class PhenoExperimentPhenoBackupController extends ControllerBase {

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routeing\RouteMatchInterface
   */
  protected RouteMatchInterface $service_RouteMatch;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match service.
   */
  public function __construct(RouteMatchInterface $route_match) {

    $this->service_RouteMatch = $route_match;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('current_route_match'),
    );
  }

  /**
   * Retrieve backups for a research experiment.
   */
  public function loadBackups() {

    ['record_id' => $exp_id, 'value' => $exp_name] = $this->service_RouteMatch
      ->getParameter('tripal_entity')
      ->get('exp_name')
      ->getValue()[0];

    $build['#title'] = 'Phenotypes Backup for ' . $exp_name;

    $build['backup_table'] = [
      '#type' => 'table',
      '#header' => [
        'datetime' => [
          'data' => 'Date Created',
          'style' => 'width: 20%',
        ],
        'notes' => [
          'data' => 'Notes/Comments',
        ],
        'author' => [
          'data' => 'Created By',
          'style' => 'width: 15%',
        ],
        'file' => [
          'data' => 'Data File',
          'style' => 'width: 5%',
        ],
      ],
      '#rows' => [],
      '#empty' => 'No Phenotypes Backup file found for this experiment.',
    ];

    $phenobackup_storage = $this->entityTypeManager()
      ->getStorage('phenodata_backup');

    $backup_ids = $phenobackup_storage
      ->getQuery()
      ->condition('project_id', $exp_id, '=')
      ->sort('backup_date', 'DESC')
      ->execute();

    foreach ($phenobackup_storage->loadMultiple($backup_ids) as $backup) {
      $created_by = $this->entityTypeManager()
        ->getStorage('user')
        ->load($backup->user_id)
        ->getAccountName();

      $file_obj = $this->entityTypeManager()
        ->getStorage('file')
        ->load($backup->file_id);

      if ($file_obj) {
        $file_download['data'] = [
          '#type' => 'button',
          '#value' => 'Download',
          '#button_type' => 'primary',
          '#attributes' => [
            'onClick' => 'window.location.href="' . $file_obj->createFileUrl() . '"; return false;',
          ],
        ];
      }

      $build['backup_table']['#rows'][] = [
        $backup->backup_date,
        $backup->comments ?: 'No notes/comments placed on this file',
        $created_by,
        $file_download,
      ];
    }

    return $build;
  }

}
