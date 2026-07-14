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
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTermsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
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
  const PHENO_COMBO_TABLE = 'trpcultivate_phenocombo';

  /**
   * Research experiment entity.
   *
   * @var \Drupal\tripal\Entity\TripalEntity
   */
  private TripalEntity $exp_entity;

  /**
   * The name of the field that contains the genus.
   *
   * @var string
   */
  public const FIELD_ORGANISM = 'exp_organism';

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

    $terms_config = $this->setTermConfig();

    $default_terms = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings')
      ->get('trpcultivate.default_terms.term_set');

    $terms = [];
    foreach ($default_terms as $cv) {
      foreach ($cv['terms'] as $term_set) {
        $term_set['cv'] = ['name' => $cv['name'], 'definition' => $cv['definition']];
        $terms[$term_set['config_map']] = $term_set;
      }
    }

    $mock_terms_service = $this->getMockBuilder(TripalCultivatePhenotypesTermsService::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('tripal_chado.chado_buddy'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['defineTerms', 'getTermId'])
      ->getMock();

    $mock_terms_service->method('defineTerms')
      ->willReturn($terms);

    $mock_terms_service->method('getTermId')
      ->willReturn(1);

    $this->container->set('trpcultivate_phenotypes.terms', $mock_terms_service);

    // Create test records - A research experiment entity with genus set to
    // Lens and a trait-method-unit combo added.
    $exp_name = 'Awesome Research Experiment';
    $genus = 'Lens';

    // Create research experiment content.
    $exp_name = 'Test Research Experiment';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields(['name'])
      ->values(['name' => $exp_name])
      ->execute();

    $genus = 'Lens';
    $this->chado_connection->insert('1:organism')
      ->fields(['genus', 'species', 'type_id'])
      ->values([
        'genus' => $genus,
        'species' => 'Culinaris',
        'type_id' => 1,
      ])
      ->execute();

    $mock_ontology_service = $this->getMockBuilder(TripalCultivatePhenotypesGenusOntologyService::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('tripal_chado.database'),
        $this->container->get('tripal.logger'),
      ])
      ->onlyMethods(['getGenusOntologyConfigValues'])
      ->getMock();

    $entity
      ->set(self::FIELD_ORGANISM, ['record_id' => $project_id, 'genus_value' => $genus]);

    $config_genus = $this->setOntologyConfig($genus);

    $genus_config = [];
    foreach ($config_genus as $config => $config_value) {
      $genus_config[$config] = ($config == 'database') ? $config_value['db_id'] : $config_value['cv_id'];
    }

    $mock_return_map[$genus] = $genus_config;
    $mock_ontology_service->method('getGenusOntologyConfigValues')
      ->willReturnMap([[$genus, $mock_return_map[$genus]]]);

    $this->container->set('trpcultivate_phenotypes.genus_ontology', $mock_ontology_service);

    $this->exp_entity = TripalEntity::create([
      'type' => 'research_experiment',
      'exp_name' => [
        'record_id' => $project_id,
        'value' => $exp_name,
      ],
      'exp_germgenus' => [
        'value' => $genus,
        'type_id' => $terms_config['genus'],
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

    $this->constraint_execontext = $this->getMockBuilder(ExecutionContextInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->constraint_execontext
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
    );

    // Genus-experiemnt is maintained.
    $constraint_validator->initialize($this->constraint_execontext);
    $constraint_validator->validate($this->exp_entity, $constraint);

    $this->assertEmpty(
      $this->violation_message,
      'No field constraint violation is expected if genus-experiment with phenotypes is maintained.'
    );

    $form_state = new FormState();
    $form = [];

    $exp_genus = $this->exp_entity->get(self::FIELD_ORGANISM)
      ->getValue()[0]['genus_value'];

    // The entity has Lens genus and with phenotypes. Omitting said genus will
    // trigger the validation error.
    $form_state->setValue([self::FIELD_ORGANISM, 0, 'organism_id'], 'Lenz culinaris');

    // The cverm_id of the configuration term - genus.
    $type_id = $this->container->get('trpcultivate_phenotypes.terms')->getTermId('genus');

    // Altered.
    $this->exp_entity->get($entity_field)->first()
      ->setValue([
        'value' => $exp_genus . 'IS ALTERED',
        'type_id' => $type_id,
      ]);

    $this->exp_entity->save();

    $constraint_validator->initialize($this->constraint_execontext);
    $constraint_validator->validate($this->exp_entity, $constraint);

    $this->assertStringContainsString(
      $constraint_message,
      $this->violation_message,
      'Altered: The validation error does not match expected error message text',
    );

    // Removed all.
    $this->exp_entity->get($entity_field)->first()->setValue([]);
    $this->exp_entity->save();

    $constraint_validator->initialize($this->constraint_execontext);
    $constraint_validator->validate($this->exp_entity, $constraint);

    $constraint_message = strtr($constraint->all_genus_failed, [
      '%content-type' => $this->exp_entity->getBundle()->label(),
      '@reload' => Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
    ]);

    $this->assertStringContainsString(
      'Update failed: Genus "' . $exp_genus . '" of this research experiment is linked to the Phenotypes module and must be a unique entry in the Germplasm Genus field.',
      $errors[self::FIELD_ORGANISM],
      'The validation error does not match expected error message text',
    );

    // Duplicate.
    $this->exp_entity->get($entity_field)
      ->setValue(
        [
          'value' => $exp_genus,
          'type_id' => $type_id,
        ],
        [
          'value' => $exp_genus,
          'type_id' => $type_id,
        ]
      );
    $this->exp_entity->save();

    $constraint_validator->initialize($this->constraint_execontext);
    $constraint_validator->validate($this->exp_entity, $constraint);

    $this->assertStringContainsString(
      Link::fromTextAndUrl('Restore Values', Url::fromRoute('<current>'))->toString(),
      $errors[self::FIELD_ORGANISM],
      'The validation does not contain the expected link to restore form values.',
    );
  }

}
