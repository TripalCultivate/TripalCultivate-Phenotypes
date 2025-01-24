<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

/**
 * Tests associated with the Genus Ontology Service.
 *
 * @group trpcultivate_phenotypes
 */
class ServiceGenusOntologyTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Genus Ontology Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected $service_PhenoGenusOntology;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Fetch module settings.
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // Genus Ontology Service.
    $this->service_PhenoGenusOntology = \Drupal::service('trpcultivate_phenotypes.genus_ontology');
    $this->assertNotNull($this->service_PhenoGenusOntology, 'Failed to instantiate Genus Ontology Service.');
  }

  /**
   * Data Provider: provides genus (organism) to test genus ontology service.
   *
   * @return array
   *   Each genus test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - A string, the genus name.
   *   - A string, the species name.
   *   - An array of expected values, with the following keys:
   *     - 'config_key': A string, configuration key value used to identify a
   *       genus configuration. The key is the lower-cased of the genus and all
   *       spaces converted into underscore character.
   *     - 'config_array': An array of the configuration values keyed by the
   *       genus configuration key.
   */
  public function provideGenusForGenusOntologyService() {
    return [
      // #0: A one-word genus string value.
      [
        'one-word genus name',
        'Lens',
        'species-1',
        [
          'config_key' => 'lens',
          'config_array' => [
            'lens' => [
              'trait',
              'method',
              'unit',
              'database',
              'crop_ontology',
            ],
          ],
        ],
      ],

      // #1: A multi-word genus string value separated by spaces.
      [
        'multi-word genus name',
        'my favourite genus',
        'species-2',
        [
          'config_key' => 'my_favourite_genus',
          'config_array' => [
            'my_favourite_genus' => [
              'trait',
              'method',
              'unit',
              'database',
              'crop_ontology',
            ],
          ],
        ],
      ],

      // #2: A genus string in camel-case.
      [
        'a genus in camel case',
        'sooCool-Genus',
        'species-3',
        [
          'config_key' => 'soocool-genus',
          'config_array' => [
            'soocool-genus' => [
              'trait',
              'method',
              'unit',
              'database',
              'crop_ontology',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Test genus ontology service.
   *
   * @param string $scenario
   *   Human-readable text description of the test scenario.
   * @param string $genus
   *   A string, the genus name.
   * @param string $species
   *   A string, the species name.
   * @param array $expected
   *   An array of expected values, with the following keys:
   *     - 'config_key': A string, configuration key value used to identify a
   *       genus configuration. The key is the lower-cased of the genus and all
   *       spaces converted into underscore character.
   *     - 'config_array': An array of the configuration values keyed by the
   *       genus configuration key.
   *
   * @dataProvider provideGenusForGenusOntologyService
   */
  public function testGenusOntologyService($scenario, $genus, $species, $expected) {
    // Create an organism.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => $species,
      ])
      ->execute();

    $this->assertIsNumeric($organism_id, 'Unable to create organism in scenario ' . $scenario);

    // Test formatGenus().
    $genus_ontology_config_key = $this->service_PhenoGenusOntology->formatGenus($genus);
    $this->assertEquals(
      $expected['config_key'],
      $genus_ontology_config_key,
      'Genus ontology configuration key does not match expected configuration key in scenario ' . $scenario
    );

    // Test defineGenusOntology().
    $genus_ontology_config_definition = $this->service_PhenoGenusOntology->defineGenusOntology();
    $this->assertNotNull(
      $genus_ontology_config_definition,
      'Failed to define genus ontology configuration array in scenario ' . $scenario
    );

    $this->assertTrue(
      is_array($genus_ontology_config_definition),
      'Genus ontology definition array returned an unexpected value type in scenario' . $scenario
    );

    $this->assertEquals(
      $expected['config_array'],
      $genus_ontology_config_definition,
      'Genus ontology definition does not match expected configuration array in sceneario ' . $scenario
    );

    $this->assertEquals(
      $genus_ontology_config_key,
      array_keys($genus_ontology_config_definition)[0],
      'The genus ontology configuration key does not match expected configuration key in scenario ' . $scenario
    );

    // Test loadGenusOntology().
    $is_created = $this->service_PhenoGenusOntology->loadGenusOntology();
    $this->assertTrue($is_created, 'Failed to load genus ontology in scenario ' . $scenario);

    // Compare the values registered in the config settings with the default
    // load value.
    $settings_genus_ontology_values = $this->config->get('trpcultivate.phenotypes.ontology.cvdbon');
    $default_load_value = 0;

    foreach ($settings_genus_ontology_values[$genus_ontology_config_key] as $config_var => $config_val) {
      $this->assertTrue(
        in_array($config_var, ['trait', 'unit', 'method', 'database', 'crop_ontology']),
        'Genus ontology configuration has no property: ' . $genus_ontology_config_key . ' - ' . $config_var . ' in scenario ' . $scenario
      );

      $this->assertEquals(
        $config_val,
        $default_load_value,
        'Genus ontology has unexpected default value (expecting ' . $default_load_value . '): ' . $config_var . ' - ' . $config_val . ' in scenario ' . $scenario
      );
    }

    // Test getGenusOntologyConfigValues().
    // At this point, the genus configuration value still has the default
    // load value of 0 set.
    $default_config_values = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);
    $this->assertEquals(
      $settings_genus_ontology_values[$genus_ontology_config_key],
      $default_config_values,
      'Expected default config values do not match expected configuration values in scenario ' . $scenario
    );

    // Update the default load values of configuration to some values.
    $this->setOntologyConfig($genus);
    $set_config_values = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);

    foreach ($set_config_values as $config_var => $config_val) {
      $this->assertNotEquals(
        $default_load_value,
        $config_val,
        'The config ' . $config_var . ' must have a value greater than 0 (default value) in scenario ' . $scenario
      );
    }

    // Test saveGenusOntologyConfigValues().
    $null_id = 1;
    $config_array = [];

    foreach ($genus_ontology_config_definition as $config_key => $config_vars) {
      foreach ($config_vars as $config_var) {
        // Set all configuration value to 1.
        $config_array[$config_key][$config_var] = $null_id;
      }
    }

    $is_saved = $this->service_PhenoGenusOntology->saveGenusOntologyConfigValues($config_array);
    $this->assertTrue($is_saved, 'Failed to save genus ontology value in scenario ' . $scenario);

    // See if all configuration terms got the null id value of 1.
    $nulled_config_values = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);
    foreach ($nulled_config_values as $null_value) {
      $this->assertEquals(
        $null_value,
        $null_id,
        'Genus ontology configuration has unexpected value (expecting ' . $null_id . '): ' . $null_value . ' in scenario ' . $scenario);
    }

    // Test getConfiguredGenusList().
    $active_genus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    $this->assertTrue(
      in_array($genus, $active_genus),
      'The configured genus could not be found in the list of active genus in scenario ' . $scenario
    );
  }

}
