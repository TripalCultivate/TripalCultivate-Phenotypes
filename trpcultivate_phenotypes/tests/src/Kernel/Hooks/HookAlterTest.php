<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Hooks;

use Drupal\Core\Form\FormState;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntity;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Hook\TripalCultivatePhenotypesAlterHooks;
use Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint\LockExperimentGenusWithPhenotypes;
use Drupal\trpcultivate_phenotypes\Plugin\Validation\Constraint\LockExperimentGenusWithPhenotypesValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

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
  public const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * The name of the field that contains the genus.
   *
   * @var string
   */
  public const FIELD_ORGANISM = 'exp_organism';

  /**
   * The test genus name.
   *
   * @var string
   */
  public const GENUS = 'Tripalus';

  /**
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $exp_entity;

  /**
   * Stores recent field constraint violation message.
   *
   * Used to capture the validation failure message for either an invalid genus
   * value or removal of all genus.
   *
   * @see Plugin/Validation/Constraint/LockExperimentGenusWithPhenotypes
   *
   * @var string
   */
  private string $violation_message = '';

  /**
   * Mock execution context used by the field constraint validator.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  private ExecutionContextInterface $constraint_execution_context;

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

    $this->container->get('trpcultivate.setup_module_service')
      ->installTerms();

    $this->container->get('tripal_chado.terms_init')
      ->installTerms();

    $this->container->get('trpcultivate.setup_module_service')
      ->importContenttypes();

    $config_terms = $this->setTermConfig();
    $genus_term = $config_terms['genus'];

    // Create test research experiment entity with phenotypes.
    $exp_name = 'Awesome Research Experiment';
    $genus = self::GENUS;
    $species = 'databasica';

    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name' => $exp_name])
      ->execute();

    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields(['genus' => $genus, 'species' => $species, 'type_id' => 1])
      ->execute();

    // Configure the genus and link to project.
    $this->setOntologyConfig($genus);
    $org_prj_prop = $this->chado_connection->insert('1:projectprop')
      ->fields([
        'project_id' => $project_id,
        'type_id' => $genus_term,
        'value' => $genus,
        'rank' => 1,
      ])
      ->execute();

    // The research experiment Tripal entity used for these tests.
    $this->exp_entity = TripalEntity::create([
      'type' => 'research_experiment',
      'exp_name' => [
        'record_id' => $project_id,
        'value' => $exp_name,
      ],
      self::FIELD_ORGANISM => [
        'record_id' => $project_id,
        'organism_id' => $organism_id,
        'genus_type_id' => $genus_term,
        'genus_value' => $genus,
        'genus_prop_id' => $org_prj_prop,
        'genus_prop_fkey' => $project_id,
        'genus_rank' => 1,
        'sciname_value' => $genus . ' ' . $species,
      ],
    ]);

    $this->exp_entity->save();

    // Associate phenotypes to genus-experiment.
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

    // Constraint violations are stored in execution context.
    // Mock constraint buildViolation()->atPath()->addViolation().
    $builder = $this->getMockBuilder(ConstraintViolationBuilderInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $builder->method('atPath')
      ->willReturnSelf();

    $builder->method('addViolation')
      ->willReturnCallback(function () {
        return NULL;
      });

    $this->constraint_execution_context = $this->getMockBuilder(ExecutionContextInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->constraint_execution_context
      ->method('buildViolation')
      ->willReturnCallback(function ($message) use ($builder) {
        $this->violation_message = $message;
        return $builder;
      }
    );

    // Setup an admin user.
    $this->setCurrentUser($this->createUser(['administer tripal']));
  }

  /**
   * Test alter hook constructor phenotypes check.
   */
  public function testPhenotypeCheck() {

    // AlterHook class property name that indicates phenotype exists.
    $has_pheno_property = 'has_pheno';

    $service_alterhook = $this->container->get('trpcultivate_phenotypes.alter_hooks');
    $reflection = new \ReflectionClass($service_alterhook);
    $property = $reflection->getProperty($has_pheno_property);

    $this->assertFalse(
      $property->getValue($service_alterhook),
      'AlterHook service class property that determines if a phenotype exists is set to FALSE by default.',
    );

    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->expects($this->once())
      ->method('getParameters')
      ->willReturn(
        new ParameterBag(['tripal_entity' => $this->exp_entity])
      );

    $service_alterhook = new TripalCultivatePhenotypesAlterHooks(
      $route_match,
      $this->chado_connection
    );

    $reflection = new \ReflectionClass($service_alterhook);
    $property = $reflection->getProperty($has_pheno_property);

    $this->assertTrue(
      $property->getValue($service_alterhook),
      'AlterHook service failed to set (TRUE) a class property that determines if a phenotype exists.',
    );
  }

  /**
   * Test that the delete button is diabled for research entity with phenotypes.
   */
  public function testDisableDeleteButton() {

    $exp_etity_baseuri = '/bio_data/' . $this->exp_entity->id();

    $request = Request::create($exp_etity_baseuri . '/edit?destination=/admin/content/bio_data');
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

    $constraint = new LockExperimentGenusWithPhenotypes();
    $constraint_validator = new LockExperimentGenusWithPhenotypesValidator(
      $this->container->get('tripal_chado.database'),
      $this->container->get('trpcultivate_phenotypes.genus_ontology'),
      $this->container->get('trpcultivate_phenotypes.terms'),
      $this->container->get('trpcultivate_phenotypes.pheno_combo'),
    );

    // Genus-experiemnt relationship is maintained.
    $constraint_validator->initialize($this->constraint_execution_context);
    $constraint_validator->validate($this->exp_entity, $constraint);

    $this->assertEmpty(
      $this->violation_message,
      'No field constraint violation is expected if genus-experiment with phenotypes is maintained.'
    );

    $organism_field = $this->exp_entity->get(self::FIELD_ORGANISM);

    // Genus in genus-experiment has been altered (specific genus) and
    // removed (all genus).
    foreach (['genus_failed', 'all_genus_failed'] as $i => $failed_key) {
      if ($i > 0) {
        // Remove all genus.
        $organism_field->setValue([]);
      }
      else {
        // Alter the genus.
        $organism_field->first()->set('genus_value', 'Not Lens');
      }

      $this->exp_entity->save();

      $constraint_validator->initialize($this->constraint_execution_context);
      $constraint_validator->validate($this->exp_entity, $constraint);

      $constraint_failed_message = strtr($constraint->{$failed_key}, [
        '%genus' => self::GENUS,
        '%content-type' => $this->exp_entity->getBundle()->label(),
        '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
      ]);

      $this->assertSame(
        $constraint_failed_message,
        $this->violation_message,
        'The validation field constraint error message does not match expected error message text with key ' . $failed_key,
      );
    }

    // Reset project genus and ontology configuration of the test genus to test
    // validator will skip if no configured genus.
    $this->chado_connection->truncate('1:projectprop')->execute();
    $this->container->get('trpcultivate_phenotypes.genus_ontology')
      ->loadGenusOntology();

    $constraint_validator->initialize($this->constraint_execution_context);
    $this->assertNull(
      $constraint_validator->validate($this->exp_entity, $constraint),
      'Validation is expected to exit when no configured genus in the system.',
    );

    // Verify that constraint is bypassed for entity with chado_base_table not
    // set to project table.
    $this->exp_entity->getBundle()
      ->setThirdPartySetting('tripal', 'chado_base_table', 'chado.organism')
      ->save();

    $constraint_validator->initialize($this->constraint_execution_context);
    $this->assertNull(
      $constraint_validator->validate($this->exp_entity, $constraint),
      'Validation is expected to exit when content type is non-project-based.',
    );
  }

}
