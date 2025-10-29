<?php

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Phenotypes experiment configuration trait operations.
 */
class PhenoExperimentTraitHandler extends ControllerBase {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   Drupal database connection.
   */
  public function __construct(Connection $db) {
    $this->db = $db;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * Handle AJAX request to assign trait to experiment.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   Message Ok.
   */
  public function assignTrait(Request $request) {

    $data = $request->request->all();
    foreach ($data as $value) {
      if (trim($value) == '') {
        throw new AccessDeniedHttpException('Invalid data provided.');
      }
    }

    $table = 'trpcultivate_phenocombo';

    // No same labels in an experiment.
    $label_exists = $this->db->select($table, 'tc')
      ->fields('tc', ['combo_id'])
      ->condition('tc.label', $data['label'], '=')
      ->condition('tc.project_id', $data['project'], '=')
      ->execute()
      ->fetchField();

    if ($label_exists) {
      return new JsonResponse(['error' => 'Label already exists'], 400);
    }

    [$attr_id, $observable_id, $unit_id] = explode(':', $data['combo']);

    $transaction = $this->db->startTransaction();
    try {
      $this->db
        ->insert($table)
        ->fields([
          'project_id' => $data['project'],
          'attr_id' => $attr_id,
          'observable_id' => $observable_id,
          'unit_id' => $unit_id,
          'label' => $data['label'],
          'is_archived' => 0,
          'is_required' => 0,
          'was_shared' => 0,
          'was_collected' => 0,
          'uid' => $data['user'],
          'timestamp' => time(),
        ])
        ->execute();
    }
    catch (Exception $e) {
      $transaction->rollback();
    }

    return new JsonResponse(['success' => 'Ok', 200]);
  }

}
