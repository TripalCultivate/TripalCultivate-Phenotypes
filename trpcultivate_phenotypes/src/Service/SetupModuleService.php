<?php

namespace Drupal\trpcultivate_phenotypes\Service;

/**
 * Service class for setup module functions.
 */
class SetupModuleService {

  /**
   * Install the ontology terms.
   *
   * Expected to be run by a Tripal Job.
   */
  public static function installOntologyTerms() {
    // We need the schema to ensure we are inserting into the right one.
    $connection = \Drupal::service('tripal_chado.database');
    $schema = $connection->getSchemaName();

    // Create terms defined in config entity.
    \Drupal::service('trpcultivate_phenotypes.terms')
      ->loadTerms($schema);

    // Create genus-ontology configuration.
    \Drupal::service('trpcultivate_phenotypes.genus_ontology')
      ->loadGenusOntology();
  }

}
