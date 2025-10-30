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
   * Request array.
   *
   * @var array
   */
  private array $request;

  /**
   * The table name.
   *
   * @var string
   */
  private const TABLE_NAME = 'trpcultivate_phenocombo';

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
   * Handle AJAX request to assign/remove trait to/from experiment.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string $action
   *   The requested action - assign or remove a trait.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response array message and status code.
   */
  public function handleAction(Request $request, string $action) {

    // Only actions assign and remove are valid request type.
    if (!in_array($action, ['assign', 'remove'])) {
      throw new AccessDeniedHttpException('Invalid request.');
    }

    // Make sure all request parameters have value.
    $this->request = $request->request->all();
    foreach ($this->request as $value) {
      if (trim($value) == '') {
        throw new AccessDeniedHttpException('Invalid data provided.');
      }
    }

    $method = $action . 'Trait';
    $response = $this->$method();

    return new JsonResponse($response['message'], $response['status_code']);
  }

  /**
   * Handle trait callback: assign a trait.
   *
   * @return array
   *   An array with the following key:
   *   - 'message': the relevant status message.
   *   - 'status_code': the response status code ie. 200 for Ok.
   */
  public function assignTrait() {

    $data = $this->request;

    // No same labels in an experiment.
    $label_exists = $this->db->select(self::TABLE_NAME, 'tc')
      ->fields('tc', ['combo_id'])
      ->condition('tc.label', $data['label'], '=')
      ->condition('tc.project_id', $data['project'], '=')
      ->execute()
      ->fetchField();

    if ($label_exists) {
      return [
        'message' => ['error' => 'Label is already used in the experiment.'],
        'status_code' => 400,
      ];
    }

    [$attr_id, $observable_id, $unit_id] = explode(':', $data['combo']);

    $transaction = $this->db->startTransaction();
    try {
      $this->db
        ->insert(self::TABLE_NAME)
        ->fields([
          'project_id' => $data['project'],
          'attr_id' => $attr_id,
          'observable_id' => $observable_id,
          'unit_id' => $unit_id,
          'label' => $data['label'],
          'is_archived' => 0,
          'is_required' => $data['required'],
          'was_shared' => 0,
          'was_collected' => 0,
          'uid' => $data['user'],
          'timestamp' => time(),
        ])
        ->execute();
    }
    catch (Exception $e) {
      $transaction->rollback();

      return [
        'message' => ['error' => 'Failed to assign trait to experiment.'],
        'status_code' => 400,
      ];
    }

    return [
      'message' => ['message' => 'Ok'],
      'status_code' => 200,
    ];
  }

  /**
   * Handle trait callback: remove a trait.
   *
   * @return array
   *   An array with the following key:
   *   - 'message': the relevant status message.
   *   - 'status_code': the response status code ie. 200 for Ok.
   */
  public function removeTrait() {

    $data = $this->request;

    $transaction = $this->db->startTransaction();
    try {
      $this->db
        ->delete(self::TABLE_NAME)
        ->condition('combo_id', (int) $data['combo_id'], '=')
        ->execute();
    }
    catch (Exception $e) {
      $transaction->rollback();

      return [
        'message' => ['error' => 'Failed to remove trait from experiment.'],
        'status_code' => 400,
      ];
    }

    return [
      'message' => ['message' => 'Ok'],
      'status_code' => 200,
    ];
  }

}
