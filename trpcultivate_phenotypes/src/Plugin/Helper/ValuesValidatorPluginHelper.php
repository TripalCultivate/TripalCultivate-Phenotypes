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
   * @param string $trait
   *   Trait name (cvterm.name).
   * 
   * @return boolean
   *   True, trait exists in the genus - cv configuration for traits. False otherwise.
   */
  public function validatorTraitExists($trait) {
    $trait = $service_traits->getTrait(['name' => $trait]);

    return ($trait) ? TRUE : FALSE;
  }

  /**
   * Test that a trait method name exists in the genus container.
   * 
   * @param string $trait
   *   Trait name (cvterm.name).
   * @param string $method_name
   *   Trait method name (cvterm.name).
   * 
   * @return boolean
   *   True, trait method name exists in the genus - cv configuration for method. False otherwise.
   */
  public function validatorMethodNameExists($trait, $method_name) {
    $found = FALSE;
    $trait_method = $service_traits->getTraitMethod(['name' => $trait]);

    foreach($trait_method as $method) {
      if ($method->name == $method_name) {
        $found = TRUE;
        break;
      }
    }

    return $found;
  }

  /**
   * Test that a trait method unit exists in the genus container.
   * 
   * @param string $trait
   *   Trait name (cvterm.name).
   * @param string $method_name
   *   Trait method name (cvterm.name).
   * @param string $unit_name
   *   Trait method unit name (cvterm.name).
   * 
   * @return boolean
   *   True, trait method name exists in the genus - cv configuration for method. False otherwise.
   */
  public function validatorUnitNameExists($trait, $method_name, $unit_name) {
    $method_id = 0;
    $trait_method = $service_traits->getTraitMethod(['name' => $trait]);

    foreach($trait_method as $method) {
      if ($method->name == $method_name) {
        $method_id = $method->cvterm_id;
        break;
      }
    }

    $trait_method_unit = $service_traits->getMethodUnit($method_id);
    return ($trait_method_unit) ? TRUE : FALSE;
  }


}