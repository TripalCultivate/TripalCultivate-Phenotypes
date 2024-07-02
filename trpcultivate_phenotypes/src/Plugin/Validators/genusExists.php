<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate that genus exists and is configured.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_genus_exists",
 *   validator_name = @Translation("Genus Exists and Configured Validator"),
 *   input_types = {"metadata"}
 * )
 */
class genusExists extends TripalCultivatePhenotypesValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * Genus Ontology Service;
   */
  protected $service_PhenoGenusOntology;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    TripalCultivatePhenotypesGenusOntologyService $service_genus_ontology) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    
    // Genus ontology service.
    $this->service_PhenoGenusOntology = $service_genus_ontology;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('trpcultivate_phenotypes.genus_ontology')
    );
  }

  /**
   * Validate that genus provided exists and configured.
   * 
   * @param array $form_values
   *   The values entered to any form field elements implemented by the importer.
   *   Each form element value can be accessed using the field element key
   *   ie. field name/key genus - $form_values['genus'].
   * 
   *   This array is the result of calling $form_state->getValues().
   *
   * @return array
   *   An associative array with the following keys.
   *     - case: a developer focused string describing the case checked.
   *     - valid: either TRUE or FALSE depending on if the data is valid or not.
   *     - failedItems: the failed genus value provided. This is empty if the value was valid.
   */
  public function validateMetadata($form_values) {
    // This genus validator assumes that a field with name/key genus was
    // implemented in the Importer form.
    $expected_field_key = 'genus';
  
    // An object was given as parameter to this method.
    if (is_object($form_values)) {
      throw new \Exception(t('Unexpected object type parameter was passed to genusExists validator.'));
    }
    
    // Failed to locate the expected field element.
    if (is_array($form_values) && !array_key_exists($expected_field_key, $form_values)) {
      throw new \Exception(t('Failed to locate genus field element. genusExists validator expects a form field element name genus.'));
    }
  
    // Validator response values.
    $valid = TRUE;
    $failed_items = '';
    
    // Genus.
    $genus = trim($form_values[ $expected_field_key ]);

    // This method has now curated all genus available in the organism table,
    // both configured and non-configured genus.
    $genus_config = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);
    
    if (!$genus_config) {
      // The genus provided does not exist.
      $valid = FALSE;
      $failed_items = $genus . ' (Not found)';
    }
    else {
      // The genus provided does exist, test that it was
      // fully configured, that is, a cv is set for trait, method and unit.

      if ($genus_config['trait'] <= 0) {
        // Not configured genus.
        $valid = FALSE;
        $failed_items = $genus . ' (Not configured)';
      }
    }
    
    return [
      'case' => 'Genus exists and is configured with phenotypes',
      'valid' => $valid,
      'failedItems' => $failed_items
    ];
  }
}