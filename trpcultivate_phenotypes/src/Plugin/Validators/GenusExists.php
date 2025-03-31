<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that genus exists and is configured.
 *
 * @TripalCultivateValidator(
 *   id = "genus_exists",
 *   validator_name = @Translation("Genus Exists and Configured Validator"),
 *   input_types = {"metadata"}
 * )
 */
class GenusExists extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * Genus Ontology Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Constructs an instance of the Genus Exists validator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   The genus ontology service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->chado_connection = $chado_connection;
    $this->service_PhenoGenusOntology = $service_PhenoGenusOntology;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
      $container->get('trpcultivate_phenotypes.genus_ontology'),
    );
  }

  /**
   * Validate that genus provided exists and is configured.
   *
   * @param array $form_values
   *   An array of values from the submitted form where each key maps to a form
   *   element and the value is what the user entered.
   *   Each form element value can be accessed using the field element key
   *   ie. field name/key genus - $form_values['genus'].
   *
   *   This array is the result of calling $form_state->getValues().
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the provided genus is valid, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the genus was valid.
   *     - 'genus_provided': The name of the genus provided.
   *
   * @throws \Exception
   *   - If the 'genus' key does not exist in $form_values.
   */
  public function validateMetadata(array $form_values) {
    // This genus validator assumes that a field with name/key genus was
    // implemented in the Importer form.
    $expected_field_key = 'genus';

    // Failed to locate the genus field element.
    if (!array_key_exists($expected_field_key, $form_values)) {
      throw new \Exception('Failed to locate genus field element. GenusExists validator expects a form field element name genus.');
    }

    // Validator response values for a valid genus value.
    $case = 'Genus exists and is configured with phenotypes';
    $valid = TRUE;
    $failed_items = [];

    $genus = trim($form_values[$expected_field_key]);

    // Query genus to check if the genus provided exists in the database.
    $query = "SELECT genus FROM {1:organism} WHERE genus = :genus";
    $genus_exists = $this->chado_connection
      ->query($query, [':genus' => $genus])
      ->fetchField();

    if (!$genus_exists) {
      // Report that the genus provided does not exist.
      $case = 'Genus does not exist';
      $valid = FALSE;
      $failed_items = ['genus_provided' => $genus];
    }
    else {
      // The genus provided is in the database, now test that the genus has
      // configuration values set.
      // This method has now curated all genus available in the organism table,
      // configured and non-configured. Grab the configuration for this genus.
      $genus_config = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);

      if (!$genus_config || $genus_config['trait'] <= 0) {
        // Report that genus was not configured.
        $case = 'Genus exists but is not configured';
        $valid = FALSE;
        $failed_items = ['genus_provided' => $genus];
      }
    }

    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items,
    ];
  }

}
