<?php

/**
 * @file
 * Contains common values validators rules shared when validating values.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Helper;

use \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;

class ValuesValidatorPluginHelper {
  // The genus selected.
  private $genus;
  
  // Traits service;
  protected $service_traits;

  /**
   * Constructor.
   */
  public function __construct(TripalCultivatePhenotypesTraitsService $service_traits) {
    $this->service_traits = $service_traits;

    // @TODO: replace this line.
    $this->genus = 'Lens';
  }

  /**
   * Setter method to set the genus.
   * 
   * @param string $genus
   *   Genus as entered/selected from the genus field in the importer form.
   * 
   * @return void
   */
  public function setGenus($genus) {
    if (!empty($genus)) {
      $this->genus = $genus;
    }
  }

  /**
   * Test that a trait name exists in the genus container.
   * 
   * @param string trait
   *   Trait name (cvterm.name)
   * 
   * @return bolean
   *   True, trait exists in the genus - cv configuration for traits. False otherwise.
   */
  public function validatorTraitExists($trait) {
    $trait = $service_traits->getTrait(['name' => $trait], $this->genus);

    return ($trait) ? TRUE : FALSE;
  }  
}