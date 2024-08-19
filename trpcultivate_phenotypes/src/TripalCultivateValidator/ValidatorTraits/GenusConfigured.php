<?php

namespace Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;

/**
 * Provides setters focused on configuring a validator to use a specific genus
 * configured to work with TripalCultivate Phenotypes.
 */
trait GenusConfigured {

  /**
   * An instance of the Genus Ontology service for use in the methods in this
   * trait.
   *
   * Services should be injected via depenency injection in your validator class
   * and then assigned to this variable in your constructor.
   *
   * @var TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Sets the genus configured to work with TripalCultivate Phenotypes for
   * this validator.
   *
   * @param string $genus
   *   The genus of a chado.organism record which has configured
   *   Trait - Method - Unit cvs + dbs.
   * @return void
   *
   * @throws \Exception
   *  - If the genus does not match at least one record in the chado.organism table.
   *  - If the genus is not configured to work with this module.
   */
  public function setConfiguredGenus(string $genus) {

    // Check we have the services we need.
    if (!isset($this->service_PhenoGenusOntology)) {
      throw new \Exception('The GenusConfigured Trait needs the Genus ontology (trpcultivate_phenotypes.genus_ontology) service injected via the create() and set to $this->service_PhenoGenusOntology in the constructor.');
    }
    if (!isset($this->chado_connection)) {
      throw new \Exception('The GenusConfigured Trait needs an instance of ChadoConnection (tripal_chado.database) injected via the create() and set to $this->chado_connection.');
    }

    // Check that the genus is present in at least one chado organism.
    $query = $this->chado_connection->select('1:organism', 'o')
      ->fields('o', ['organism_id'])
      ->condition('o.genus', $genus);
    $exists = $query->execute()->fetchObject();
    if (!is_object($exists)) {
      throw new \Exception("The genus '$genus' does not exist in chado and GenusConfigured Trait requires it both exist and be configured to work with phenotypes. The validators using this trait should not be called if previous validators checking for a configured genus fail.");
    }

    // Check that the genus is configured + get that configuration while we are at it.
    $configuration_values = $this->service_PhenoGenusOntology->getGenusOntologyConfigValues($genus);
    if (!is_array($configuration_values) OR empty($configuration_values)) {
      throw new \Exception("The genus '$genus' is not configured and GenusConfigured Trait requires it both exist and be configured to work with phenotypes. The validators using this trait should not be called if previous validators checking for a configured genus fail.");
    }

    // Now we finally get to set things up for the validator!
    // Set configured values
    $this->context['genus']['ontology_terms'] = $configuration_values;

    // Set the configured genus
    $this->context['genus']['name'] = $genus;
  }

  /**
   * Returns a genus which has been configured
   *
   * @return string
   *   The genus name
   *
   * @throws \Exception
   *  - If the 'genus' key does not exist in the context array (ie. the genus has
   *    NOT been set)
   */
  public function getConfiguredGenus() {

    if (array_key_exists('genus', $this->context)) {
      if (array_key_exists('name', $this->context['genus'])) {
        return $this->context['genus']['name'];
      }
      else {
        throw new \Exception("Cannot retrieve the genus name as one has not been set by the setConfiguredGenus() method.");
      }
    }
    else {
      throw new \Exception("Cannot retrieve anything to do with genus as one has not been set by the setConfiguredGenus() method.");
    }
  }

  /**
   * Returns the ontology term IDs that have been configured for a genus
   *
   * @return array
   *   An array of ontology cvterm IDs associated with the following keys:
   *     trait,
   *     unit,
   *     method,
   *     crop_ontology,
   *     database
   *
   * @throws \Exception
   *  - If the 'genus' key does not exist in the context array (ie. the genus has
   *    NOT been set)
   */
  public function getConfiguredGenusOntologyTerms() {
    if (array_key_exists('genus', $this->context)) {
      if (array_key_exists('ontology_terms', $this->context['genus'])) {
        return $this->context['genus']['ontology_terms'];
      }
      else {
        throw new \Exception("Cannot retrieve the ontology terms of the genus as one has not been set by the setConfiguredGenus() method.");
      }
    }
    else {
      throw new \Exception("Cannot retrieve anything to do with genus as one has not been set by the setConfiguredGenus() method.");
    }

  }
}
