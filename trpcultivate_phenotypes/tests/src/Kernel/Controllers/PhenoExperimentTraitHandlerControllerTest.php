<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Tests\tripal\Traits\TripalTestTrait;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Controller\PhenoExperimentTraitHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests associated with PhenoExperimentTraitPickerForm class.
 *
 * @group trpcultivate_phenotypes
 * @group configuration
 */
#[Group('trpcultivate_phenotypes')]
#[Group('configuration')]
class PhenoExperimentTraitHandlerControllerTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;
  use TripalTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'markup',
    'path',
    'path_alias',
    'system',
    'user',
    'views',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Route name.
   *
   * @var string
   */
  const ROUTE_NAME = 'trpcultivate_phenotypes.experiment_handle_trait';

  /**
   * The table name.
   *
   * @var string
   */
  const TABLE_NAME = 'trpcultivate_phenocombo';

  /**
   * Response code message key.
   *
   * @var array
   */
  const MESSAGE_KEY = [
    200 => 'message',
    400 => 'error',
  ];


  /**
   * Test genus with a set of test traits.
   *
   * @var array
   */
  private $trait_set = [
    'Lens' => [
      [
        'Trait Name' => 'Days to Flower',
        'Trait Description' => 'DTF trait description text',
        'Method Short Name' => 'DTF',
        'Collection Method' => 'DTF trait collection method text',
        'Unit' => 'days',
        'Type' => 'Quantitative',
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig([
      'tripal_chado',
      'trpcultivate_phenotypes',
      'trpcultivate',
    ]);
    $this->installSchema('trpcultivate_phenotypes', [self::TABLE_NAME]);
    $this->installEntitySchema('user');

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $config_terms = $this->setTermConfig();

    // Create test records.
    $experiment = 'A Good Research Experiment';
    $genus = array_keys($this->trait_set)[0];

    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $experiment])
      ->execute();

    // Create and configure genus.
    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $genus,
        'species' => $this->getRandomGenerator()->word(10),
        'type_id' => 1,
      ])
      ->execute();

    $this->setOntologyConfig($genus);

    $this->chado_connection->insert('1:projectprop')
      ->fields([
        'project_id' => $project_id,
        'type_id' => $config_terms['genus'],
        'value' => $genus,
        'rank' => 1,
      ])
      ->execute();

    // Install trait combos to good research experiment.
    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');
    $trait_service->setTraitGenus($genus);

    foreach ($this->trait_set[$genus] as $i => $combo) {
      $ids = $trait_service->insertTrait($combo);

      $this->trait_set[$genus][$i] = [
        'combo' => $ids['trait'] . ':' . $ids['method'] . ':' . $ids['unit'],
        'genus' => $genus,
        'project' => $project_id,
      ];
    }

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $this->setCurrentUser(
      $this->createUser([$route->getRequirements()['_permission']])
    );
  }

  /**
   * Test assign trait handler.
   */
  public function testAssignTraitHandler() {

    $genus = array_keys($this->trait_set)[0];
    $labels = [];

    foreach ($this->trait_set[$genus] as $combo) {
      $request = Request::create(
        'bio_data/experiment/handle_trait/assign',
        'POST', [
          'label' => $labels[] = $this->getRandomGenerator()->word(10),
          'required' => 1,
          'combo' => $combo['combo'],
          'project' => $combo['project'],
          'genus' => $combo['genus'],
          'user' => $this->container->get('current_user')->id(),
        ]
      );

      $response = $this->container->get('http_kernel')
        ->handle($request);

      $this->assertInstanceOf(
        JsonResponse::class,
        $response,
        'The assign trait handler failed to return a response of type JsonResponse object.'
      );

      $this->assertEquals(
        $status_code = 200,
        $response->getStatusCode(),
        'The assign trait handler failed to return the expected status code: ' . $status_code
      );

      $this->assertEquals(
        $status_message = 'Ok',
        json_decode($response->getContent(), TRUE)[self::MESSAGE_KEY[$status_code]],
        'The assign trait handler failed to return the expected status code message: ' . $status_message
      );

      [$attr_id, $observable_id, $unit_id] = explode(':', $combo['combo']);
      $experiment_combo = $this->chado_connection->select(self::TABLE_NAME, 'tc')
        ->fields('tc', ['combo_id', 'project_id', 'attr_id', 'observable_id', 'unit_id'])
        ->condition('tc.project_id', $combo['project'], '=')
        ->condition('tc.attr_id', $attr_id, '=')
        ->condition('tc.observable_id', $observable_id, '=')
        ->condition('tc.unit_id', $unit_id, '=')
        ->execute()
        ->fetchObject();

      $this->assertNotNull(
        $experiment_combo,
        'The assign trait handler failed to assign the trait combo to the experiment.'
      );

      $this->assertEquals(
        $combo['project'],
        $experiment_combo->project_id,
        'The assign trait handler failed to assign the correct combo project id to the experiment.'
      );

      foreach (['attr_id' => $attr_id, 'observable_id' => $observable_id, 'unit_id' => $unit_id] as $key => $id) {
        $this->assertEquals(
          $id,
          $experiment_combo->$key,
          'The assign trait handler failed to assign the correct combo key: ' . $key . ' to the experiment.'
        );
      }
    }

    // Assign a combo with a label that is already in used.
    unset($request, $response);
    $request = Request::create(
      'bio_data/experiment/handle_trait/assign',
      'POST', [
        'label' => $labels[0],
        'required' => 1,
        'combo' => '1:1:1',
        'project' => 1,
        'genus' => 'genus',
        'user' => $this->container->get('current_user')->id(),
      ]
    );

    $response = $this->container->get('http_kernel')
      ->handle($request);

    $this->assertEquals(
      $status_code = 400,
      $response->getStatusCode(),
      'The assign trait handler failed to return the expected status code: ' . $status_code
    );

    $this->assertEquals(
      $status_message = 'Label is already used in the experiment.',
      json_decode($response->getContent(), TRUE)[self::MESSAGE_KEY[$status_code]],
      'The assign trait handler failed to return the expected status code message: ' . $status_message . ' if label is already used.'
    );
  }

  /**
   * Test remove trait handler.
   */
  public function testRemoveTraitHandler() {

    // Removing a combo id does not exist.
    $request = Request::create(
      'bio_data/experiment/handle_trait/remove',
      'POST',
      [
        'combo_id' => 9999,
      ]
    );

    $response = $this->container->get('http_kernel')
      ->handle($request);

    $this->assertInstanceOf(
      JsonResponse::class,
      $response,
      'The remove trait handler failed to return a response of type JsonResponse object.'
    );

    $this->assertEquals(
      $status_code = 400,
      $response->getStatusCode(),
      'The remove trait handler failed to return the expected status code: ' . $status_code . ' if combo id does not exist'
    );

    $this->assertEquals(
      $status_message = 'Could not find combo record.',
      json_decode($response->getContent(), TRUE)[self::MESSAGE_KEY[$status_code]],
      'The remove trait handler failed to return the expected status code message: ' . $status_message . ' if combo id does not exist'
    );

    // Test valid request.
    $combo_ids = $this->chado_connection->select(self::TABLE_NAME, 'tc')
      ->fields('tc', ['combo_id'])
      ->execute()
      ->fetchCol();

    foreach ($combo_ids as $combo_id) {
      $request = Request::create(
        'bio_data/experiment/handle_trait/remove',
        'POST',
        [
          'combo_id' => $combo_id,
        ]
      );

      $response = $this->container->get('http_kernel')
        ->handle($request);

      $this->assertInstanceOf(
        JsonResponse::class,
        $response,
        'The remove trait handler failed to return a response of type JsonResponse object.'
      );

      $this->assertEquals(
        $status_code = 200,
        $response->getStatusCode(),
        'The remove trait handler failed to return the expected status code: ' . $status_code
      );

      $this->assertEquals(
        $status_message = 'Ok',
        json_decode($response->getContent(), TRUE)[self::MESSAGE_KEY[$status_code]],
        'The remove trait handler failed to return the expected status code message: ' . $status_message
      );

      $is_in = $this->chado_connection->select(self::TABLE_NAME, 'tc')
        ->fields('tc', ['combo_id'])
        ->condition('tc.combo_id', $combo_id, '=')
        ->execute()
        ->fetchField();

      $this->assertIsNull(
        $is_in,
        'The remove trait handler failed to remove the trait combo from the experiment.'
      );
    }

    // Combo is marked is_archived.
    $combo_id = 10;
    $genus = array_keys($this->trait_set)[0];
    $rec = $this->trait_set[$genus][0];

    [$attr_id, $observable_id, $unit_id] = explode(':', $rec['combo']);
    $is_set = 1;

    $this->container->get('database')
      ->insert(self::TABLE_NAME)
      ->fields([
        'combo_id' => $combo_id,
        'project_id' => $rec['project'],
        'attr_id' => $attr_id,
        'observable_id' => $observable_id,
        'unit_id' => $unit_id,
        'label' => $this->getRandomGenerator()->word(10),
        'is_archived' => $is_set,
        'is_required' => $is_set,
        'was_shared' => $is_set,
        'was_collected' => $is_set,
        'uid' => $this->container->get('current_user')->id(),
        'timestamp' => time(),
      ])
      ->execute();

    $request = Request::create(
      'bio_data/experiment/handle_trait/remove',
      'POST',
      [
        'combo_id' => $combo_id,
      ]
    );

    $response = $this->container->get('http_kernel')
      ->handle($request);

    $this->assertInstanceOf(
      JsonResponse::class,
      $response,
      'The remove trait handler failed to return a response of type JsonResponse object.'
    );

    $this->assertEquals(
      $status_code = 400,
      $response->getStatusCode(),
      'The remove trait handler failed to return the expected status code: ' . $status_code . ' if combo is marked is_archived, was_shared, or was collected.'
    );

    $this->assertEquals(
      $status_message = 'Not allowed to delete trait marked is_archived, was_shared, or was_collected.',
      json_decode($response->getContent(), TRUE)[self::MESSAGE_KEY[$status_code]],
      'The remove trait handler failed to return the expected status code message: ' . $status_message . ' if combo is marked is_archived, was_shared, or was collected.'
    );

  }

  /**
   * Provide request data to trait action handler to test exceptions.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - A string, trait handler action - assign or remove.
   *   - A string, request method ie. POST, GET.
   *   - An array of key value pairs that make the parameter requirement of a
   *     particular trait handler action.
   *   - An array of expected values, with the following key:
   *     - 'message': the expected message for a every set of parameter values.
   */
  public static function provideActionHandlerValues() {

    return [
      // #0: Not the supported action.
      [
        'not assign nor remove action',
        'something-else',
        'POST',
        [],
        [
          'exception_message' => 'Invalid request: Unsupported handler action.',
        ],
      ],

      // #1: Not the supported request method.
      [
        'a GET method',
        'assign',
        'GET',
        [],
        [
          'exception_message' => 'Invalid request: Unsupported request method.',
        ],
      ],

      // #2: Assign with missing parameters.
      [
        'assign with missing parameters',
        'assign',
        'POST',
        [
          'label' => 'Test Label',
          'required' => 1,
          'user' => 0,
        ],
        [
          'exception_message' => 'Invalid request: Incorrect parameter count.',
        ],
      ],

      // #3: Assign trait with parameters set to empty value.
      [
        'assign with empty parameters',
        'assign',
        'POST',
        [
          'label' => 'Test Label',
          'required' => 1,
          'combo' => '',
          'project' => '',
          'genus' => '',
          'user' => 0,
        ],
        [
          'exception_message' => 'Invalid request: Invalid data provided.',
        ],
      ],

      // #4: Remove with missing parameter.
      [
        'remove with missing parameter',
        'remove',
        'POST',
        [],
        [
          'exception_message' => 'Invalid request: Incorrect parameter count.',
        ],
      ],

      // #5: Remove trait with parameters set to empty value.
      [
        'remove with empty parameters',
        'remove',
        'POST',
        [
          'combo_id' => '',
        ],
        [
          'exception_message' => 'Invalid request: Invalid data provided.',
        ],
      ],
    ];
  }

  /**
   * Test trait action handler.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param string $action
   *   A string, trait handler action - assign or remove.
   * @param string $method
   *   A string, request method ie. POST, GET.
   * @param array $data
   *   An array of key value pairs that make the parameter requirement of a
   *   particular trait handler action.
   * @param array $expected
   *   An array of expected values, with the following key:
   *   - 'message': the expected message for a every set of parameter values.
   *
   * @dataProvider provideActionHandlerValues
   */
  #[DataProvider('provideActionHandlerValues')]
  public function testActionHandler(string $scenario, string $action, string $method, array $data, array $expected) {

    $route = $this->container->get('router.route_provider')
      ->getRouteByName(self::ROUTE_NAME);

    $this->setCurrentUser(
      $this->createUser([$route->getRequirements()['_permission']])
    );

    $request = Request::create(
      'bio_data/experiment/handle_trait/' . $action,
      $method,
      $data
    );

    $action_handler = PhenoExperimentTraitHandler::create($this->container);

    $exception_message = '';

    try {
      $action_handler->handleAction($request, $action);
    }
    catch (\Exception $e) {
      $exception_message = $e->getMessage();
    }

    $this->assertEquals(
      $exception_message,
      $expected['exception_message'],
      'The action handler failed to return the expected message in scenario ' . $scenario
    );
  }

}
