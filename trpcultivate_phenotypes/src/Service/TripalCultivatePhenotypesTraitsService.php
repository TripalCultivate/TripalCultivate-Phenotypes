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
use Drupal\Core\Url;

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
    // For this setter to work both Genus and Terms configuration must be configured.
    // Term configuration:
    $not_set = [];
    foreach($this->terms as $config_key => $value) {
      if ($this->terms[ $config_key ] <= 0) {
        // A term was not configured.
        $not_set[] = $config_key;
      }
    }

    if ($not_set) {
      $terms_not_set = implode(', ', $not_set);
      throw new \Exception(t('Term(s) [@term] used to create trait asset relationships was not configured.
        To configure terms, go to @url and set the controlled vocabulary associated with the term.',
        ['@term' => $terms_not_set, '@url' => Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]
      ));
    }

    // Genus configuration:
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
      ['@genus' => $genus, '@url' => Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]));
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
   *
   * @dependencies
   *   getTraitAsset(), getMethodUnitDataType().
   */
  public function insertTrait($trait, $schema = NULL) {
    // Configuration settings of the genus.
    $genus_config = $this->config;
    if (!$genus_config) {
      // Genus not set.
      throw new \Exception(t('No genus has been set. See setting a genus in the Traits Service and make sure to
        use a configured genus. To configure a genus or see all configured genus, go to @url.',
        ['@url' => Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]
      ));
    }

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

    // Create trait: Trait, Method and Unit.
    foreach($arr_trait as $type => $values) {
      // Inspect cvterm to see if the trait asset already existed.
      $trait_asset_rec = $this->getTraitAsset($values['name'], $type);

      if ($trait_asset_rec) {
        // Trait asset found, reference the record and re-use.
        $arr_trait[ $type ]['id'] = $trait_asset_rec->cvterm_id;
      }
      else {
        // A new trait asset, create a record.
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
    $arr_rel = [
      'method-trait' => $this->terms['method_to_trait_relationship_type'],
      'method-unit'  => $this->terms['unit_to_method_relationship_type']
    ];

    foreach($arr_rel as $type => $rel) {
      // Check if relationship exists.
      if ($type == 'method-trait') {
        $subject = $arr_trait['trait']['id'];
        $object  = $arr_trait['method']['id'];

        // Fetch all method(s) linked to the trait.
        $asset_rec = $this->getTraitMethod($subject);
      }
      else {
        $subject = $arr_trait['method']['id'];
        $object  = $arr_trait['unit']['id'];

        // Fetch all unit(s) linked to the method.
        $asset_rec = $this->getMethodUnit($subject);
      }

      $exists = FALSE;
      if ($asset_rec) {
        if (is_array($asset_rec)) {
          foreach($asset_rec as $rec) {
            if ($rec->cvterm_id == $object) {
              $exists = TRUE;
              break;
            }
          }
        }
        else {
          if ($asset_rec->cvterm_id == $object) {
            $exists = TRUE;
          }
        }
      }

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
    $data_type = $this->getMethodUnitDataType($arr_trait['unit']['id']);
    if (!$data_type) {
      $ins_type = $this->chado->insert('1:cvtermprop')
        ->fields([
          'cvterm_id' => $arr_trait['unit']['id'],
          'type_id' => $this->terms['unit_type'],
          'value' => $trait['Type']
        ])
        ->execute();

      if (!$ins_type) {
        $this->logger->error('Error. Failed to insert unit data type @unit : @data_type.',
          ['@unit' => $type, '@data_type' => $trait['Unit']], ['drupal_set_message' => TRUE]
        );
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
   *
   * @dependencies
   *   getTraitAsset()
   */
  public function getTrait($trait) {
    $trait_rec = $this->getTraitAsset($trait, 'trait');
    return $trait_rec;
  }

  /**
   * Get trait method.
   *
   * @param string|int $trait
   *   A string value is the trait name (cvterm.name), whereas an integer value
   *   is the trait id number (cvterm.cvterm_id).
   *
   * @return array
   *   All matching records (object) in an array. 0 if no methods were found.
   *   If there is only one result (method) returned access the value using index 0.
   *
   * @dependencies
   *   getTraitAsset()
   */
  public function getTraitMethod($trait) {
    $trait_rec = $this->getTraitAsset($trait, 'trait');
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

    $methods = [];
    if (count($method_ids) > 0) {
      // Has methods.
      $methods = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id IN (:ids[])",
        [':ids[]' => array_values($method_ids)]
      )
        ->fetchAll();
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
   * @return array
   *   All matching records (object) in an array. 0 if no units were found.
   *   If there is only one result (unit) returned access the value using index 0.
   */
  public function getMethodUnit($method) {
    $method_rec = $this->getTraitAsset($method, 'method');
    if (!$method_rec) {
      // Method was not found.
      return 0;
    }

    // Inspect the relationship table where method has a method - unit relationship.
    $sql = "SELECT object_id AS id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = :t_id";

    $args = [
      ':s_id' => (int) $method_rec->cvterm_id,
      ':t_id' => $this->terms['unit_to_method_relationship_type']
    ];

    $unit_ids = $this->chado->query($sql, $args)
      ->fetchCol();

    $units = [];
    if (count($unit_ids) > 0) {
      // Has units.
      $units = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id IN (:ids[])",
        [':ids[]' => array_values($unit_ids)]
      )
        ->fetchAll();
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
    $unit_rec = $this->getTraitAsset($unit, 'unit');
    if (!$unit_rec) {
      // Unit was not found.
      return 0;
    }

    // Inspect the relationship table where unit has a unit - data type relationship.
    $sql = "SELECT value FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id";

    $args = [
      ':c_id' => $unit_rec->cvterm_id,
      ':t_id' => $this->terms['unit_type']
    ];

    $data_type = $this->chado->query($sql, $args)
      ->fetchCol();

    if (count($data_type) > 1) {
      // Unit appears to multiple data types.
      $this->logger->error(
        'Error. Failed to retrieve data type for unit : @unit in cv : @cv. Multiple data types found for the same unit.',
        ['@unit' => $unit, '@cv' => $genus_config['unit']['name']],
        ['drupal_set_message' => TRUE]
      );
      throw new \Exception(t('A multiple data type error occurred while retrieving a unit data type.'));
    }

    return ($data_type) ? reset($data_type) : 0;
  }

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
   *   getTraitAsset(), getMethodUnitDataType().
   */
  public function getTraitMethodUnitCombo(string|int $trait, string|int $method, string|int $unit) {
    // Trait.
    $trait_rec = $this->getTraitAsset($trait, 'trait');
    if (!$trait_rec) {
      return 0;
    }

    // Method.
    $method_rec = $this->getTraitAsset($method, 'method');
    if (!$method_rec) {
      return 0;
    }

    // Unit.
    $unit_rec = $this->getTraitAsset($unit, 'unit');
    if (!$unit_rec) {
      return 0;
    }

    // Append unit data type to the unit data object.
    $unit_id = $unit_rec->cvterm_id;
    $unit_data_type = $this->getMethodUnitDataType($unit_id);
    $unit_rec->{'data_type'} = $unit_data_type;

    return [
      'trait' => $trait_rec,
      'method' => $method_rec,
      'unit'  => $unit_rec
    ];
  }

  /**
   * Get trait asset - trait, method or unit.
   *
   * @param string|int $key
   *   A string value is the name (cvterm.name), whereas an integer value
   *   is the id number (cvterm.cvterm_id).
   * @param string $type
   *   trait, method or unit asset type. Trait is the default.
   *
   * @return object
   *   Trait asset record object or 0 if not found.
   */
  public function getTraitAsset($key, $type = 'trait') {
    // Configuration check.
    $genus_config = $this->config;
    if (!$genus_config) {
      // Genus not set.
      throw new \Exception(t('No genus has been set. See setting a genus in the Traits Service and make sure to
        use a configured genus. To configure a genus or see all configured genus, go to @url.',
        ['@url' => Url::fromRoute('trpcultivate_phenotypes.settings_ontology')->toString()]
      ));
    }

    // Parameter check.
    if (!in_array($type, ['trait', 'method', 'unit'])) {
      // Not a valid parameter asset type value..
      throw new \Exception(t('Not a valid trait asset type value provided. Trait asset getter expects type to be the
        string trait, method or unit.'
      ));
    }

    if (empty($key) || (is_numeric($key) && (int) $key < 0)) {
      // Not a valid asset key value (0, negative values or an empty string).
      throw new \Exception(t('Not a valid @type key value provided. The trait asset getter expects a string name or
        an integer id key value.', ['@type' => $type]
      ));
    }


    // Query trait asset.
    if (is_numeric($key)) {
      // Asset parameter key is the id number.
      $asset_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE cvterm_id = :value",
        [':value' => (int) $key]
      )
        ->fetchAll();

      if ($asset_rec && (int) $asset_rec[0]->cv_id != $genus_config[ $type ]['id']) {
        // The id number seems to be of a trait asset that is outside of
        // the cv: asset type (trait, method or unit) the genus was configured.
        throw new \Exception(t('The requested trait asset @type : id @key CV value does not match the CV the genus was configured.',
          ['@type' => $type, '@key' => $key]
        ));
      }
    }
    else {
      // Asses parameter key is the name.
      $asset_rec = $this->chado->query(
        "SELECT * FROM {1:cvterm} WHERE name = :value AND cv_id = :cv",
        [':value' => $key, ':cv' => $genus_config[ $type ]['id']]
      )
        ->fetchAll();

      if ($asset_rec && count($asset_rec) > 1) {
        // Trait asset name requested appears to have copies in the cv: asset type (trait, method or unit) the genus was configured.
        // Log error for admin to resolve.
        $this->logger->error(
          'Error. Failed to retrieve @type : @key in cv : @cv. Multiple copies of the same term found in the CV',
          ['@type' => $type, '@key' => $key, '@cv' => $genus_config[ $type ]['name']],
          ['drupal_set_message' => TRUE]
        );
        throw new \Exception(t('A duplicate term error occurred while retrieving a trait asset.'));
      }
    }

    if(!$asset_rec) {
      // Trait asset was not found.
      return 0;
    }

    return reset($asset_rec);
  }
}
