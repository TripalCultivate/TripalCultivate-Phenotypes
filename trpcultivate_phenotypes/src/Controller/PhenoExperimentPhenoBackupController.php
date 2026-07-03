<?php

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\tripal\Services\TripalLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class definition of PhenoExperimentPhenoBackupController.
 *
 * NOTE: this controller supports Phenotypes Integration and is set to 'backup'
 * integration in the route definition.
 *
 * @see trpcultivate_phenotypes.routing.yml
 */
class PhenoExperimentPhenoBackupController extends ControllerBase {

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routeing\RouteMatchInterface
   */
  protected RouteMatchInterface $service_RouteMatch;

  /**
   * Tripal logger service.
   *
   * @var \Drupal\tripal\Services\TripalLogger
   */
  protected TripalLogger $tripal_logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match service.
   * @param \Drupal\tripal\Services\TripalLogger $tripal_logger
   *   Tripal logger service.
   */
  public function __construct(RouteMatchInterface $route_match, TripalLogger $tripal_logger) {

    $this->service_RouteMatch = $route_match;
    $this->tripal_logger = $tripal_logger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('current_route_match'),
      $container->get('tripal.logger'),
    );
  }

  /**
   * Retrieve backups for a research experiment.
   */
  public function loadBackups() {

    if (!$tripal_entity = $this->service_RouteMatch->getParameter('tripal_entity')) {
      $this->tripal_logger->error('The research experiment entity does not exist.');
      throw new NotFoundHttpException();
    }

    $exp_id = $tripal_entity->getBackendRecordId('chado_storage');
    if ($exp_id === NULL) {
      $this->tripal_logger->error('The research experiment entity does not contain backend storage values or it cannot be found.');
      throw new NotFoundHttpException();
    }

    $build['#title'] = 'Phenotypes Backup for ' . $tripal_entity->label();

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
        ->load($backup->get('user_id'))
        ->getAccountName();

      $file_obj = $this->entityTypeManager()
        ->getStorage('file')
        ->load($backup->get('file_id'));

      if ($file_obj) {
        $file_download['data'] = [
          '#type' => 'link',
          '#title' => 'Download',
          '#url' => Url::fromUri($file_obj->createFileUrl($relative = FALSE)),
          '#attributes' => [
            'class' => [
              'button',
              'button--primary',
            ],
          ],
        ];
      }

      $build['backup_table']['#rows'][] = [
        date('Y-M-d H:i:s', strtotime($backup->get('backup_date'))),
        $backup->get('comments') ?: 'No notes/comments placed on this file',
        $created_by,
        $file_download,
      ];
    }

    return $build;
  }

}
