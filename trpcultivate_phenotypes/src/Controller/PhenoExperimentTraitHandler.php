<?php

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
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
  protected array $request;

  /**
   * The table name.
   *
   * @var string
   */
  private const TABLE_NAME = 'trpcultivate_phenocombo';

  /**
   * Expected parameter per action.
   *
   * @var array
   */
  private const ACTION_PARAMS = [
    'assign' => [
      'combo',
      'genus',
      'label',
      'project',
      'required',
      'user',
    ],
    'remove' => [
      'combo_id',
    ],
  ];

  /**
   * Table row class name.
   *
   * @var string
   */
  private const TABLE_ROW_CLASS = 'trait-combbo';

  /**
   * Good response code.
   *
   * @var int
   */
  private const GOOD_REQUEST = 200;

  /**
   * Bad response code.
   *
   * @var int
   */
  private const BAD_REQUEST = 400;

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

    if (!in_array($action, array_keys(self::ACTION_PARAMS))) {
      throw new AccessDeniedHttpException('Invalid request: Unsupported handler action.');
    }

    if ($request->getMethod() != 'POST') {
      throw new AccessDeniedHttpException('Invalid request: Unsupported request method.');
    }

    // Check that a request has the expected query payload and each key is not
    // empty value.
    $this->request = $request->query->all();
    $request_params_keys = array_keys($this->request);

    foreach (self::ACTION_PARAMS[$action] as $key) {
      if (!in_array($key, $request_params_keys)) {
        throw new \InvalidArgumentException('Invalid request: Missing parameter - ' . $key);
      }

      if (empty(trim($this->request[$key]))) {
        throw new \InvalidArgumentException('Invalid request: Parameter with empty value - ' . $key);
      }
    }

    $method = $action . 'Trait';
    return $this->$method();
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
        'message' => 'Label is already used in the experiment.',
        'status_code' => self::BAD_REQUEST,
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
        'message' => 'Failed to assign trait to experiment.',
        'status_code' => self::BAD_REQUEST,
      ];
    }

    return [
      'message' => 'Ok',
      'status_code' => self::GOOD_REQUEST,
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

    $combo_id = (int) $this->request['combo_id'];

    $combo = $this->db->select(self::TABLE_NAME, 'tc')
      ->fields('tc', ['combo_id', 'is_archived', 'was_shared', 'was_collected'])
      ->condition('tc.combo_id', $combo_id, '=')
      ->execute()
      ->fetchObject();

    if (!$combo) {
      throw new AccessDeniedHttpException('Invalid request: Could not find trait combo.');
    }

    if ($combo->is_archived == 1 || $combo->was_shared == 1 || $combo->was_collected == 1) {
      throw new AccessDeniedHttpException('Invalid request: Not allowed to delete trait marked is_archived, was_shared, or was_collected.');
    }

    $transaction = $this->db->startTransaction();
    try {
      $this->db
        ->delete(self::TABLE_NAME)
        ->condition('combo_id', $combo_id, '=')
        ->execute();
    }
    catch (Exception $e) {
      $transaction->rollback();

      throw new AccessDeniedHttpException('Invalid request: Failed to remove trait from experiment.');
    }

    $response = new AjaxResponse();
    $response
      ->addCommand(new RemoveCommand('.' . self::TABLE_ROW_CLASS . $combo_id))
      ->addCommand(new CloseModalDialogCommand());

    return $response;
  }

}
