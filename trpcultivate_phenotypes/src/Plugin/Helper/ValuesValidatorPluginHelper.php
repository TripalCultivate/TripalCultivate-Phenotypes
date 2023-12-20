<?php

/**
 * @file
 * Contains common values validators rules shared when validating values.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Helper;

use \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use \Drupal\tripal_chado\Database\ChadoConnection;

class ValuesValidatorPluginHelper {  
  // Traits service;
  protected $service_traits;

  // Chado connection.
  protected $chado;

  /**
   * Constructor.
   */
  public function __construct(TripalCultivatePhenotypesTraitsService $service_traits, ChadoConnection $chado) {
    // Traits service.
    $this->service_traits = $service_traits;
    
    // Chado database connection.
    $this->chado = $chado;
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
      // Set the genus to restrict methods in trait service.
      $this->service_traits->setTraitGenus($genus);
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
    $trait = $this->service_traits->getTrait(['name' => $trait]);

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
    $trait_method = $this->service_traits->getTraitMethod(['name' => $trait]);
    
    if ($trait_method) {
      foreach($trait_method as $method) {
        if ($method->name == $method_name) {
          $found = TRUE;
          break;
        }
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
    $trait_method_unit = 0;

    $trait_method = $this->service_traits->getTraitMethod(['name' => $trait]);
    
    if ($trait_method) {
      foreach($trait_method as $method) {
        if ($method->name == $method_name) {
          $method_id = $method->cvterm_id;
          break;
        }
      }
    
      $trait_method_unit = $this->service_traits->getMethodUnit($method_id);
    }

    return ($trait_method_unit) ? TRUE : FALSE;
  }
  
  /**
   * Test that germplasm accession + germplasm name exists in stock table.
   *
   * @param string $name
   *   Germplasm/stock name.
   * @param string $accession
   *   Germplasm/stock uniquename (Accession). Optional.
   * 
   * @return boolean
   *   True, stock with the given accession and name exists. False otherwise.
   */
  public function validatorGermplasmExists($name, $accession = NULL) {
    $found = 0;
    
    if ($name) {
      $args = [];
      $args[':g_name'] = $name;

      // Additional filter using stock uniquename value.
      $uniquename = '';
      if (!is_null($accession)) {
        $args[':u_name'] = $accession;
        $uniquename = ' AND uniquename = :u_name ';
      }

      $sql = 'SELECT stock_id FROM {1:stock} WHERE name = :g_name %s LIMIT 1';
      $sql = sprintf($sql, $uniquename);

      $stock_id = $this->chado->query($sql, $args)
        ->fetchField();
      
      if ($stock_id) {
        $found = 1;
      }
    }
   
    return ($found) ? TRUE : FALSE;
  }

  /**
   * Test a value if it matches the expected data type.
   *
   * @param $value
   *   Value to be examined.
   * @param $data_type
   *   The data type the value parameter must conform. 
   *
   * @return array
   *   Keys:
   *     status - Boolean value where true indicates that value is of the correct data type.
   *     info - contains the information about the type it tested the value against.
   */
  public function validatorMatchDataType($value, $data_type) {
    $is_valid = [
      'status' => TRUE,
      'info' => ''
    ];

    switch($data_type) {
      //
      case 'FOUR_DIGIT_YEAR':
        // Present years and in the past but not beyond 1900.
        $value = (int) $value;
        if ($value < 1900 || $value > date('Y')) {
          $is_valid = [
            'status' => FALSE,
            'info' => 'Four digit year from 1990 - Present Year'
          ];
        }
  
        break;
  
      //
      case 'NO_ZERO_NUMBER':
        // Numbers, no 0.
        $value = (int) $value;
        if ($value <= 0) {
          $is_valid = [
            'status' => FALSE,
            'info' => 'Number greater than 0'
          ];
        }
  
        break;
      
      //
      case 'QUALITATIVE':
        // Qualitative - text value.
        // Letters, numbers and any 0 or more characters.
        if (preg_match('/[a-z0-9.*]/i', $value) !== 1) {
          $is_valid = [
            'status' => FALSE,
            'info' => 'Qualitative (text) value'
          ];
        }

        break;
      
      //
      case 'NUMBER':
      case 'QUANTITATIVE':
        // Quantitative - numerical value.
        // Numbers including 0.
        if (!is_numeric($value)) {
          $is_valid = [
            'status' => FALSE,
            'info' => 'Quantitative (number) value'
          ];
        }
  
        break;
    }

    return $is_valid;
  }

  /**
   * Validate value data type matches the unit data type.
   * 
   * @param string $trait
   *   Trait name (cvterm.name).
   * @param string $method_name
   *   Trait method name (cvterm.name).
   * @param string $unit_name
   *   Trait method unit name (cvterm.name).
   * 
   * @return array
   *   Keys:
   *     status - Boolean value where true indicates that value is of the correct data type.
   *     info - contains the information about the type it tested the value against.
   */
  public function validatorMatchValueToUnit($trait, $method_name, $unit_name, $value) {
    $is_valid = [
      'status' => TRUE,
      'info' => ''
    ];

    $trait_method = $this->service_traits->getTraitMethod(['name' => $trait]);
    
    if($trait_method) {
      foreach($trait_method as $method) {
        if ($method->name == $method_name) {
          $method_id = $method->cvterm_id;
          break;
        }
      }
    
      if (isset($method_id)) {
        $trait_method_unit = $this->service_traits->getMethodUnit($method_id);

        if ($trait_method_unit->name == $unit_name) {
          $unit_type = $this->service_traits->getMethodUnitDataType($trait_method_unit->cvterm_id);
          
          if ($unit_type) {
            // QUALITATIVE or QUANTITATIVE:
            $data_type = strtoupper($unit_type);
            $is_valid = $this->validatorMatchDataType($value, $data_type);
          }
        }
        else {
          $is_valid = [
            'status' => FALSE,
            'info' => 'Unit not found'
          ];  
        }
      }
      else {
        $is_valid = [
          'status' => FALSE,
          'info' => 'Method not found'
        ];   
      }
    }
    else {
      $is_valid = [
        'status' => TRUE,
        'info' => 'Trait has no method'
      ];  
    }

    return $is_valid;
  }
}