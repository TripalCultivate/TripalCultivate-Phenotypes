<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Hooks;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint\LockExperimentGenusWithPhenotypes;
use Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint\LockExperimentGenusWithPhenotypesValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Test alter hooks.
 */
#[Group('hooks')]
#[RunTestsInSeparateProcesses]
class HookAlterTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;
  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'field_ui',
    'field_group',
    'filter',
    'datetime',
    'text',
    'node',
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
   * The table name that holds the experiment trait combos.
   *
   * @var string
   */
  const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $exp_entity;

  /**
   * Field constraint violation message.
   *
   * @var string
   */
  private string $violation_message = '';

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setup();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Create a test chado instance as needed by our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig([
      'system',
      'tripal_chado',
      'trpcultivate_phenotypes',
      'trpcultivate',
    ]);
    $this->installSchema('trpcultivate_phenotypes', [self::PHENO_COMBO_TABLE]);
    $this->installEntitySchema('user');

    \trpcultivate_install_terms();
    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    \trpcultivate_import_contenttypes();
    $config_terms = $this->setTermConfig();

    // Create test records - A research experiment entity with genus set to
    // Lens and a trait-method-unit combo added.
    $exp_name = 'Awesome Research Experiment';
    $exp_entity_id = 1;
    $genus = 'Lens';

    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $exp_name])
      ->execute();

    $entity = TripalEntity::create([
      'id' => $exp_entity_id,
      'type' => 'research_experiment',
      'label' => $exp_name,
    ]);

    $entity
      ->set('exp_name', ['record_id' => $project_id, 'value' => $exp_name]);

    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $genus,
        'species' => 'Culinaris',
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

    $entity
      ->set('exp_germgenus', ['record_id' => $project_id, 'value' => $genus]);

    $entity->save();
    $this->exp_entity = $entity;

    $trait_service = $this->container->get('trpcultivate_phenotypes.traits');

    $trait_service->setTraitGenus($genus);
    $ids = $trait_service->insertTrait($combo = [
      'Trait Name' => 'Days to Flower',
      'Trait Description' => 'DTF trait description text',
      'Method Short Name' => 'DTF',
      'Collection Method' => 'DTF trait collection method text',
      'Unit' => 'days',
      'Type' => 'Quantitative',
    ]);

    $this->container->get('database')
      ->insert(self::PHENO_COMBO_TABLE)
      ->fields([
        'project_id',
        'attr_id',
        'observable_id',
        'unit_id',
        'label',
        'is_archived',
        'is_required',
        'was_collected',
        'was_shared',
        'uid',
        'timestamp',
      ])
      ->values([
        $project_id,
        $ids['trait'],
        $ids['method'],
        $ids['unit'],
        $combo['Trait Name'] . ':' . $combo['Method Short Name'],
        mt_rand(0, 1),
        mt_rand(0, 1),
        mt_rand(0, 1),
        mt_rand(0, 1),
        $this->container->get('current_user')->id(),
        time(),
      ])
      ->execute();
  }

  /**
   * Test that the delete button is diabled for research entity with phenotypes.
   */
  public function testDisableDeleteButton() {

    $this->setCurrentUser($this->createUser(['administer tripal']));
    $exp_etity_baseuri = '/bio_data/' . $this->exp_entity->id();

    $request = Request::create($exp_etity_baseuri . '/edit');
    $page_edit = $this->container->get('http_kernel')
      ->handle($request)
      ->getContent();

    // Verify that this research entity configured with phenotypes has the
    // in-page delete action button unavailable/disabled.
    $this->assertStringContainsString(
      'Delete',
      (string) $page_edit,
      'The Tripal research entity edit page is expected to contain a delete button.',
    );

    $this->setRawContent($page_edit);
    $el_delete = $this->cssSelect('a[href="' . $exp_etity_baseuri . '/delete"]');

    foreach ($el_delete as $el) {
      $this->assertStringContainsString(
        'class="visually-hidden"',
        (string) $el->asXML(),
        'The Delete action button in edit page is expected to be disabled for experiment configured with phenotypes.',
      );
    }
  }

  /**
   * Test the validator that maintains genus-experiment relationship.
   */
  public function testGenusExperimentValidator() {

    // Constraint violations are stored in execution context.
    $exe_context = $this->getMockBuilder(ExecutionContextInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $exe_context->method('addViolation')
      ->willReturnCallback(function ($message) {
        $this->violation_message = $message;
        return NULL;
      }
    );

    $entity_field = 'exp_germgenus';
    $exp_genus = $this->exp_entity->get($entity_field)[0]
      ->getValue()['value'];

    $constraint = new LockExperimentGenusWithPhenotypes();
    $constraint_validator = new LockExperimentGenusWithPhenotypesValidator();
    $constraint_validator->initialize($exe_context);

    // Genus-experiemnt is maintained.
    $constraint_validator->validate($this->exp_entity->get($entity_field), $constraint);
    $this->assertEmpty(
      $this->violation_message,
      'No field constraint violation is expected if genus-experiment with phenotypes is maintained.'
    );

    $constraint_message = 'Update failed: Genus "' . $exp_genus . '" of this research experiment is linked to the Phenotypes module and must be a unique entry in the Germplasm Genus field. Click ' . Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString() . ' to restore form values if you have removed or altered a genus';

    // Remove the genus with phenotypes from the experiment.
    $this->exp_entity
      ->set($entity_field, [])
      ->save();
    $constraint_validator->validate($this->exp_entity->get($entity_field), $constraint);

    $this->assertStringContainsString(
      $constraint_message,
      $this->violation_message,
      'The validation error does not match expected error message text',
    );

    // Alter the genus (is equivalent to missing/removing).
    $this->violation_message = '';

    $this->exp_entity
      ->set('exp_germgenus', [
        'record_id' => $this->exp_entity->getBackendRecordId('chado_storage'),
        'value' => $exp_genus . 'ALTERED',
      ])
      ->save();
    $constraint_validator->validate($this->exp_entity->get($entity_field), $constraint);

    $this->assertStringContainsString(
      $constraint_message,
      $this->violation_message,
      'The validation error does not match expected error message text',
    );
  }

}
