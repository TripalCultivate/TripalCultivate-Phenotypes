<?php

/**
 * @file
 * Tripal Cultivate Phenotypes Trait service definition.
 *
 * This service handles all processes involving traits/unit/method or
 * cvterms that are used as phenotypic traits/unit/method.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use \Drupal\tripal_chado\Database\ChadoConnection;
use \Drupal\tripal\Services\TripalLogger;

/**
 * Class TripalCultivatePhenotypesTraitsService.
 */
class TripalCultivatePhenotypesTraitsService {
  // Genus ontology service.
  protected $service_genus_ontology;

  // Terms service.
  protected $service_terms;

  // Chado database connection.
  protected $chado;

  // Tripal logger.
  protected $logger;

  // Genus ontology configuration values.
  private $config = [];

  // Terms configuration values.
  private $terms = [
    'method_to_trait_relationship_type' => NULL,
    'unit_to_method_relationship_type' => NULL,
    'trait_to_synonym_relationship_type' => NULL,
    'unit_type' => NULL,
  ];

  /**
   * Constructor.
   */
  public function __construct(TripalCultivatePhenotypesGenusOntologyService $genus_ontology, TripalCultivatePhenotypesTermsService $terms, ChadoConnection $chado, TripalLogger $logger) {
    // Genus ontology service.
    $this->service_genus_ontology = $genus_ontology;
    // Terms service.
    $this->service_terms = $terms;
    // Chado connection.
    $this->chado = $chado;
    // Tripal logger service.
    $this->logger = $logger;

    // Terms configurations.
    // Terms focused on adding traits, required by this service.
    foreach($this->terms as $config_key => $value) {
      $this->terms[ $config_key ] = $this->service_terms->getTermId($config_key);
    }
  }

  /**
   * Set the genus configuration values to which all
   * methods will use to restrict any trait data operations.
   *
   * @param $genus
   *   String, a specific genus to reference in genus ontology configurations.
   *
   * @return void
   */
  public function setTraitGenus($genus) {
    // Fetch all configured genus (active genus).
    $active_genus = $this->service_genus_ontology->getConfiguredGenusList();

    if ($active_genus && in_array($genus, $active_genus)) {
      $genus_config = $this->service_genus_ontology->getGenusOntologyConfigValues($genus);

      // Resolve each configuration entry id number to names.
      $cv_sql = "SELECT name FROM {1:cv} WHERE cv_id = :id";
      $db_sql = "SELECT name FROM {1:db} WHERE db_id = :id";

      foreach($genus_config as $config => $value) {
        if ($value > 0) {
          // Ontology might be set to 0 - as it is optional in the config page.
          // Reference only values that have been set.
          if ($config == 'database') {
            // DB configuration.
            $name = $this->chado->query($db_sql, [':id' => $value])
              ->fetchField();
          }
          else {
            // CV configuration.
            // Configurations: traits, method, unit and crop_ontology.
            $name = $this->chado->query($cv_sql, [':id' => $value])
              ->fetchField();
          }

          if ($name) {
            $this->config[ $config ] = ['id' => $value, 'name' => $name];
          }
          else {
            $schema = $this->chado->getSchemaName();
            $table = ($config == 'database') ? $schema . '.db' : $schema . '.cv';
            throw new \Exception(t('We were unable to retrieve the name for the Genus @type in the @table where the primary key is @id.',
              ['@type' => $config, '@id' => $value, '@table' => $table]));
          }
        }
      }
    }
    else {
      throw new \Exception(t('The genus "@genus" was not configured for use with Tripal Cultivate Phenotypes. To configure this genus, go to @url and set the controlled vocabularies associated with this genus.',
      ['@genus' => $genus, '@url' => \Drupal\Core\Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]));
    }
  }

  /**
   * Create/Insert phenotypic trait.
   *
   * @param array $trait
   *   Trait added to this module has the following data keys:
   *   - Trait Name: the name of the trait (e.g. Plant Height).
   *   - Trait Description: the description of the trait (e.g. the height of the plant from the
   *     ground to it's tallest point without stretching the plant.)
   *   - Method Short Name: a short name for the method (e.g. Average of 5 plants)
   *   - Collection Method: a description of the method used to collect the phenotypic data
   *     (e.g. measured 5 plants from the plot and then averaged them.)
   *   - Unit: the full word describing the unit used in the method (e.g. centimeters)
   *   - Type: Quantitative or Qualitative.
   * @param object $schema
   *   Tripal DBX Chado Connection object
   *
   * @see Header property and Trait Importer.
   * NOTE:
   *   - Tripal Importer Plugin loading of file is performed using database transaction.
   *   - Although Genus is collected in the Importer form, It is not saved as part of the terms/traits.
   *     Genus functions as a pointer to which CV to save the terms as set in the Genus-ontology configuration.
   *
   * @return
   *   An array with the following keys where each value is the id of new cvterm:
   *   trait, method, unit.
   */
  public function insertTrait($trait, $schema = NULL) {
    // Configuration settings of the genus.
    $genus_config = $this->config;

    if (!$genus_config) {
      throw new \Exception(t('No genus has been set. To configure a genus, go to @url and set the controlled vocabularies associated with a genus.',
        ['@url' => \Drupal\Core\Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]));
    }

    // Query term.
    $sql = "SELECT cvterm_id AS id FROM {1:cvterm} INNER JOIN {1:cv} USING (cv_id)
      WHERE cvterm.name = :value AND cv.cv_id = :id";

    // TRAIT, METHOD and UNIT data array.
    $arr_trait = [
      'trait' =>  [
        'name' =>  $trait['Trait Name'],
        'description' => $trait['Trait Description'],
      ],
      'method' => [
        'name' => $trait['Method Short Name'],
        'description' => $trait['Collection Method'],
      ],
      'unit'  =>  [
        'name' => $trait['Unit'],
        'description' => $trait['Unit'],
      ]
    ];

    // Create trait.
    foreach($arr_trait as $type => $values) {
      $value = ($type == 'method') ? $values['description'] : $values['name'];
      $id = $this->chado->query($sql, [':value' => $value, ':id' => $genus_config[ $type ]['id']])
        ->fetchAll();

      if (count($id) > 1) {
        // Check if a record exists in the same Genus. If the term already existed,
        // the query should only return one record for it to be re-used.
        // Anything more than 1 should trigger an error (duplicate) to admin.
        $this->logger->error('Error. Failed to insert term @type : @term. Term has multiple copies in @cv.', 
          ['@type' => $type, '@term' => $values['name'], '@cv' => $genus_config[ $type ]['name']], 
          ['drupal_set_message' => TRUE]
        );
        throw new \Exception(t('A database error occurred while inserting a term.'));
      }

      if ($id) {
        $arr_trait[ $type ]['id'] = $id[0]->id;
      }
      else {
        $rec = [
          'id' => $genus_config['database']['name'] . ':' . $values['name'],
          'name' => $values['name'],
          'cv_name' => $genus_config[ $type ]['name'],
          'definition' => $values['description']
        ];

        $ins = chado_insert_cvterm($rec, [], $schema);
        if (!$ins) {
          // Could not insert cvterm.
          $this->logger->error('Error. Failed to insert term @type : @term.', 
            ['@type' => $type, '@term' => $values['name']], 
            ['drupal_set_message' => TRUE]
          );
          throw new \Exception(t('A database error occurred while inserting a term.'));
        }

        $arr_trait[ $type ]['id'] = $ins->cvterm_id;
      }
    }

    // RELATIONSHIPS: trait-method and method-unit.
    // Query relationship.
    $sql = "SELECT cvterm_relationship_id FROM {1:cvterm_relationship}
      WHERE subject_id = :s_id AND type_id = :t_id AND object_id = :o_id";

    $arr_rel = [
      'method-trait' => $this->terms['method_to_trait_relationship_type'],
      'method-unit'  => $this->terms['unit_to_method_relationship_type']
    ];

    // Create relationships.
    foreach($arr_rel as $type => $rel) {
      // Check if relationship exists.
      if ($type == 'method-trait') {
        $subject = $arr_trait['trait']['id'];
        $object  = $arr_trait['method']['id'];
      }
      else {
        $subject = $arr_trait['method']['id'];
        $object  = $arr_trait['unit']['id'];
      }

      $exists = $this->chado->query($sql, [':s_id' => $subject, ':t_id' => $rel, ':o_id' => $object])
        ->fetchField();

      if (!$exists) {
        $ins_rel = $this->chado->insert('1:cvterm_relationship')
          ->fields([
            'subject_id' => $subject,
            'type_id' => $rel,
            'object_id' => $object
          ])
          ->execute();
        
        if (!$ins_rel) {
          $this->logger->error('Error. Failed to create term relationship @type : subject id - @subject object id - @object.', 
            ['@type' => $type, '@subject' => $subject, '@object' => $object], 
            ['drupal_set_message' => TRUE]
          );
          throw new \Exception(t('A database error occurred while inserting a term relationship.'));
        }
      }
    }

    // UNIT DATA TYPE:
    $sql = "SELECT cvtermprop_id FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado->query($sql, [':c_id' => $arr_trait['unit']['id'], ':t_id' => $this->terms['unit_type']])
      ->fetchField();

    if (!$data_type) {
      $ins_type = $this->chado->insert('1:cvtermprop')
        ->fields([
          'cvterm_id' => $arr_trait['unit']['id'],
          'type_id' => $this->terms['unit_type'],
          'value' => $trait['Type']
        ])
        ->execute();

      if (!$ins_type) {
        $this->logger->error('Error. Failed to insert unit data type @unit : @data_type.', ['@unit' => $type, '@data_type' => $trait['Unit']], ['drupal_set_message' => TRUE]);
        throw new \Exception(t('A database error occurred while inserting a unit data type.'));
      }
    }

    // Return record id created.
    if (isset($arr_trait['trait']['id']) && isset($arr_trait['method']['id']) && isset($arr_trait['unit']['id'])) {
      return [
        'trait' => $arr_trait['trait']['id'],
        'method' => $arr_trait['method']['id'],
        'unit'  => $arr_trait['unit']['id']
      ];
    }
  }

  /**
   * Get trait.
   *
   * @param string|int $trait
   *   A string value is the trait name (cvterm.name), whereas an integer value 
   *   is the trait id number (cvterm.cvterm_id).
   *
   * @return object
   *   Matching record/line in cvterm table or 0 if trait record Was not found.
   */
  public function getTrait($trait) {
    // Configuration settings of the genus.
    $genus_config = $this->config;
    if (!$genus_config) {
      // Genus not set.
      throw new \Exception(t('No genus has been set. See setting a genus in the Traits Service.'));
    }
    
    // Parameter check.
    if (empty($trait) || (is_numeric($trait) && (int) $trait < 0)) {
      // Not a valid parameter trait value (0, null or an empty string).
      throw new \Exception(t('Not a valid trait value provided. Trait getter expects a string trait name or an integer trait id.'));
    }
    
    // Query trait.
    if (is_numeric($trait)) {
      // Trait id number.
      $trait_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id = :value",
        [':value' => (int) $trait]
      )
        ->fetchAll();
    }
    else {
      // Trait name.
      $trait_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE name = :value AND cv_id = :cv",
        [':value' => $trait, ':cv' => $genus_config['trait']['id']]
      )
        ->fetchAll();
      
      if (count($trait_rec) > 1) {
        // Trait appears to have copies in the cv:trait the genus is configured.
        $this->logger->error(
          'Error. Failed to retrieve trait : @trait in cv : @cv. Multiple copies of the same term found in the CV', 
          ['@trait' => $trait, '@cv' => $genus_config['method']['name']], 
          ['drupal_set_message' => TRUE]
        );
        throw new \Exception(t('A duplicate term error occurred while retrieving a trait.'));
      }
    }

    return ($trait_rec) ? reset($trait_rec) : 0;
  }

  /**
   * Get trait method.
   *
   * @param string|int $trait
   *   A string value is the trait name (cvterm.name), whereas an integer value 
   *   is the trait id number (cvterm.cvterm_id).
   *
   * @return object
   *   Matching record/line in cvterm table (method) or 0 if trait/method record was not found.
   * 
   * @dependencies
   *   getTrait()
   */
  public function getTraitMethod($trait) {
    // Configuration settings of the genus.
    $genus_config = $this->config;
    if (!$genus_config) {
      // Genus not set.
      throw new \Exception(t('No genus has been set. See setting a genus in the Traits Service.'));
    }

    if (!$this->terms['method_to_trait_relationship_type']) {
      // Method configuration (trait-method relationship) term is not set.
      throw new \Exception(t('The term used to create trait - method relationship is not configured. To configure, got to @url and set the term Method.',
        ['@url' => \Drupal\Core\Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]));
    }
    
    // Parameter check.
    if (empty($trait) || (is_numeric($trait) && (int) $trait < 0)) {
      // Not a valid parameter trait value (0, null or an empty string).
      throw new \Exception(t('Not a valid trait value provided. Trait method getter expects a string trait name or an integer trait id.'));
    }

    // Query methods.
    $trait_param = (is_numeric($trait)) ? (int) $trait : $trait;
    $trait_rec = $this->getTrait($trait_param);

    if (!$trait_rec) {
      // Trait was not found.
      return 0;
    }
     
    // Inspect the relationship table where the trait has a trait - method relationship.
    $sql = "SELECT object_id AS id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = :t_id";

    $args = [
      ':s_id' => (int) $trait_rec->cvterm_id,
      ':t_id' => $this->terms['method_to_trait_relationship_type']
    ];

    $method_ids = $this->chado->query($sql, $args)
      ->fetchCol();

    if (count($method_ids) > 0) {
      // Has methods.
      $methods = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id IN (:ids[])", 
        [':ids[]' => array_values($method_ids)]
      )
        ->fetchAll();
    }
    
    // If there is just one item in the result, simplify the array so
    // no need to to index zero to access the one and only record.
    if (count($methods) == 1) {
      $methods = reset($methods);
    }

    return ($methods) ? $methods : 0;
  }

  /**
   * Get trait method unit.
   *
   * @param string|int $method
   *   A string value is the method name (cvterm.name), whereas an integer value 
   *   is the method id number (cvterm.cvterm_id).
   *
   * @return object
   *   Matching record/line in cvterm table (unit) or 0 if method/unit record was not found.
   */
  public function getMethodUnit($method) {
    // Configuration settings of the genus.
    $genus_config = $this->config;
    if (!$genus_config) {
      // Genus not set.
      throw new \Exception(t('No genus has been set. See setting a genus in the Traits Service.'));
    }

    if (!$this->terms['unit_to_method_relationship_type']) {
      // Unit configuration (method-unit relationship) term is not set.
      throw new \Exception(t('The term used to create method - unit relationship is not configured. To configure, got to @url and set the term Unit.',
        ['@url' => \Drupal\Core\Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]));
    }
    
    // Parameter check.
    if (empty($method) || (is_numeric($method) && (int) $method < 0)) {
      // Not a valid parameter method value (0, null or an empty string).
      throw new \Exception(t('Not a valid method value provided. Method unit getter expects a string method name or an integer method id.'));
    }
    
    // Query trait method.
    if (is_numeric($method)) {
      // Method id number.
      $method_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id = :value",
        [':value' => (int) $method]
      )
        ->fetchAll();
    }
    else {
      // Method name.
      $method_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE name = :value AND cv_id = :cv",
        [':value' => $method, ':cv' => $genus_config['method']['id']]
      )
        ->fetchAll();
      
      if (count($method_rec) > 1) {
        // Method appears to have copies in the cv:method the genus is configured.
        $this->logger->error(
          'Error. Failed to retrieve method : @method in cv : @cv. Multiple copies of the same term found in the CV', 
          ['@trait' => $method, '@cv' => $genus_config['method']['name']], 
          ['drupal_set_message' => TRUE]
        );
        throw new \Exception(t('A duplicate term error occurred while retrieving a method.'));
      }
    }

    if (!$method_rec) {
      // Method was not found.
      return 0;
    }
    
    // Query method units.
    $method_rec = reset($method_rec);
    $method_id = $method_rec->cvterm_id;
    
    $units = [];
    
    // Inspect the relationship table where method has a method - unit relationship.
    $sql = "SELECT object_id AS id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = :t_id";

    $args = [
      ':s_id' => (int) $method_id,
      ':t_id' => $this->terms['unit_to_method_relationship_type']
    ];

    $unit_ids = $this->chado->query($sql, $args)
      ->fetchCol();
    
    if (count($unit_ids) > 0) {
      // Has units.
      $units = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id IN (:ids[])",
        [':ids[]' => array_values($unit_ids)]
      )
        ->fetchAll();
    }

    // If there is just one item in the result, simplify the array so
    // no need to to index zero to access the one and only record.
    if (count($units) == 1) {
      $units = reset($units);
    }
    
    return ($units) ? $units : 0;
  }

  /**
   * Get Trait Method Unit data type.
   * 
   * @param string|int $unit
   *   A string value is the unit name (cvterm.name), whereas an integer value 
   *   is the unit id number (cvterm.cvterm_id).
   *
   * @return string
   *   The data type of the unit, either quantitative or qualitative or 0 when the unit record was not found 
   *   and was not set a data type.
   */
  public function getMethodUnitDataType($unit) {
    // Configuration settings of the genus.
    $genus_config = $this->config;
    if (!$genus_config) {
      // Genus not set.
      throw new \Exception(t('No genus has been set. See setting a genus in the Traits Service.'));
    }

    if (!$this->terms['unit_type']) {
      // AdditionalType configuration (unit - data type relationship) term is not set.
      throw new \Exception(t('The term used to create unit - data type relationship is not configured. To configure, got to @url and set the term AdditionalType.',
        ['@url' => \Drupal\Core\Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]));
    }
    
    // Parameter check.
    if (empty($unit) || (is_numeric($unit) && (int) $unit < 0)) {
      // Not a valid parameter method value (0, null or an empty string).
      throw new \Exception(t('Not a valid unit value provided. Unit data type getter expects a string unit name or an integer unit id.'));
    }

    // Query method unit.
    if (is_numeric($unit)) {
      // Unit id number.
      $unit_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id = :value",
        [':value' => (int) $unit]
      )
        ->fetchAll();
    }
    else {
      // Unit name.
      $unit_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE name = :value AND cv_id = :cv",
        [':value' => $unit, ':cv' => $genus_config['unit']['id']]
      )
        ->fetchAll();
      
      if (count($unit_rec) > 1) {
        // Unit appears to have copies in the cv:unit the genus is configured.
        $this->logger->error(
          'Error. Failed to retrieve unit : @unit in cv : @cv. Multiple copies of the same term found in the CV', 
          ['@unit' => $unit, '@cv' => $genus_config['unit']['name']], 
          ['drupal_set_message' => TRUE]
        );
        throw new \Exception(t('A duplicate term error occurred while retrieving a unit.'));
      }
    }
    
    if(!$unit_rec) {
      // Unit was not found.
      return 0;
    }

    $unit_id = $unit_rec->cvterm_id;

    $sql = "SELECT value FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id";
    $data_type = $this->chado->query($sql, [':c_id' => $unit_id, ':t_id' => $this->terms['unit_type']])
      ->fetchAll();
    
    if (count($data_type) > 1) {
      // Unit appears to multiple data types.
      $this->logger->error(
        'Error. Failed to retrieve data type for unit : @unit in cv : @cv. Multiple data types found', 
        ['@unit' => $unit, '@cv' => $genus_config['unit']['name']], 
        ['drupal_set_message' => TRUE]
      );
      throw new \Exception(t('A multiple data type error occurred while retrieving a unit data type.'));
    }

    return ($data_type) ? reset($data_type) : 0;
  }






  





   ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////










  

  /**
   * Get trait, method and unit combination.
   * 
   * @param string|int $trait
   *   A string value is the trait name, whereas an integer value is the trait id number.
   * @param string|int $method
   *   A string value is the trait method short name, whereas an integer value is the method id number.
   * @param string|int $unit
   *   A string value is the method unit name, whereas an integer value is the unit id number.
   * 
   * @return array
   *   An associative array where the keys are trait, method and unit and the values
   *   are the cvterm records for each key.
   * 
   *   0 if any one of trait, method or unit did not return any record.
   * 
   * @dependencies
   *   getTrait(), getTraitMethod(), getMethodUnit() and getMethodUnitDataType().
   */
  public function getTraitMethodUnitCombo(string|int $trait, string|int $method, string|int $unit) {
    // Make sure that each parameter has a valid value.
    foreach([$trait, $method, $unit] as $param) {
      if (empty($param) || $param <= 0) {
        throw \Exception('The trait, method and unit passed into getTraitMethodUnitCombo() is required; however, one of them was empty.');
      }
    }

    // Trait:
    $key = (is_numeric($trait)) ? 'id' : 'name';
    $value = ($key == 'id') ? (int) $trait : $trait;

    $arr_trait = [$key => $value];
    $trait_val = $this->getTrait($arr_trait);
    
    if (!$trait_val) {
      return 0;
    }
    
    // Method:
    $method_val = null;
    if ($trait) {
      $trait_methods = $this->getTraitMethod($arr_trait);

      if ($trait_methods) {
        $key = (is_numeric($method)) ? 'cvterm_id' : 'name';

        foreach($trait_methods as $method_obj) {
          if ($method_obj->{$key} == $method) {
            $method_val = $method_obj;
            break;
          }
        }

        if (!$method_val) {
          return 0;
        }
      }
    }

    // Unit:
    $unit_val = null;
    if ($method_val) {
      $method_id = $method_val->cvterm_id;
      $method_units = $this->getMethodUnit($method_id);
      
      if ($method_units) {
        $key = (is_numeric($unit)) ? 'cvterm_id' : 'name';

        foreach($method_units as $unit_obj) {
          if ($unit_obj->{$key} == $unit) {
            $unit_val = $unit_obj;
            break;
          }
        }
      }
      
      if (!$unit_val) {
        return 0;
      }

      // Append unit data type to the unit data object.
      $unit_id = $unit_val->cvterm_id;
      $unit_data_type = $this->getMethodUnitDataType($unit_id);
      $unit_val->{'data_type'} = $unit_data_type;
    }
    
    return [
      'trait' => $trait_val,
      'method' => $method_val,
      'unit'  => $unit_val
    ];
  }
}