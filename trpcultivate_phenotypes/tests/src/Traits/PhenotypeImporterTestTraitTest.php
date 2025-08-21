<?php

namespace Drupal\Tests\tripalcultivate_phenotypes\Traits;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test importer test trait.
 *
 * @group trpcultivate_phenotypes_test_trait
 */
#[Group('trpcultivate_phenotypes_test_trait')]
class PhenotypeImporterTestTraitTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

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
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    \Drupal::state()->set('is_a_test_environment', TRUE);
    $this->installConfig(['trpcultivate_phenotypes']);
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
  }

  /**
   * Test setOntologyConfig() method in the test trait.
   */
  public function testSetOntologyConfig() {

    $test_genus = 'Tripalus';
    $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $test_genus,
        'species' => 'some species',
      ])
      ->execute();

    $this->setOntologyConfig($test_genus);

    $genus_config = $this->config->get('trpcultivate.phenotypes.ontology.cvdbon');
    $genus_key_config = array_keys($genus_config);

    // There must be an item in the configuration with the genus as the key.
    $this->assertEquals(
      strtolower($test_genus),
      reset($genus_key_config),
      'The trait method setOntologyConfig() failed to create a configuration item for the genus ' . $test_genus
    );

    // Check config keys trait, method, unit, crop ontology and db were set.
    foreach (reset($genus_config) as $key => $value) {
      $this->assertGreaterThan(
        0,
        $value,
        'The method setOntologyConfig() failed to set a value for the key ' . $key
      );
    }
  }

  /**
   * Test setTermConfig() method in the test trait.
   */
  public function testSetTermConfig() {

    $this->setTermConfig();

    $terms_config = $this->config->get('trpcultivate.phenotypes.ontology.terms');

    // Check each term was set a value.
    foreach ($terms_config as $term => $value) {
      $this->assertGreaterThan(
        0,
        $value,
        'The method setTermConfig() failed to set a value for the term ' . $term
      );
    }
  }

}
