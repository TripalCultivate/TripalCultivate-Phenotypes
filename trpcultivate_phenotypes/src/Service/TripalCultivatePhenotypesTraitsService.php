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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('trpcultivate_phenotypes.genus_ontology'),
      $container->get('trpcultivate_phenotypes.terms'),
      $container->get('tripal_chado.database'),
      $container->get('tripal.logger')
    );
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
      $cv_sql = "SELECT name FROM {1:cv} WHERE cv_id = :id LIMIT 1";
      $db_sql = "SELECT name FROM {1:db} WHERE db_id = :id LIMIT 1";

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
      return 0;
    }

    // Query term.
    $sql = "SELECT cvterm_id FROM {1:cvterm} INNER JOIN {1:cv} USING (cv_id)
      WHERE cvterm.name = :value AND cv.cv_id = :id LIMIT 1";

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
      // Check if a record exists in the same Genus.
      $value = ($type == 'method') ? $values['description'] : $values['name'];
      $id = $this->chado->query($sql, [':value' => $value, ':id' => $genus_config[ $type ]['id']])
        ->fetchField();

      if ($id) {
        $arr_trait[ $type ]['id'] = $id;
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
          $this->logger->error('Error. Could not insert unit @key.', ['@key' => $values['name']]);
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
        $this->chado->insert('1:cvterm_relationship')
          ->fields([
            'subject_id' => $subject,
            'type_id' => $rel,
            'object_id' => $object
          ])
          ->execute();
      }
    }

    // UNIT DATA TYPE:
    $sql = "SELECT cvtermprop_id FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado->query($sql, [':c_id' => $arr_trait['unit']['id'], ':t_id' => $this->terms['unit_type']])
      ->fetchField();

    if (!$data_type) {
      $this->chado->insert('1:cvtermprop')
        ->fields([
          'cvterm_id' => $arr_trait['unit']['id'],
          'type_id' => $this->terms['unit_type'],
          'value' => $trait['Type']
        ])
        ->execute();
    }

    // Return record id created.
    if (isset($arr_trait['trait']['id']) && isset($arr_trait['method']['id']) && isset($arr_trait['unit']['id'])) {
      return [
        'trait' => $arr_trait['trait']['id'],
        'method' => $arr_trait['method']['id'],
        'unit'  => $arr_trait['unit']['id']
      ];
    }
    else {
      return 0;
    }
  }

  /**
   * Get trait.
   *
   * @param array $trait
   *   Key:
   *     id - get trait by id number (cvterm_id) or
   *     name - get trait by name (cvterm.name).
   *
   * @return object
   *   Matching record/line in cvterm table.
   */
  public function getTrait($trait) {
    // Configuration settings of the genus.
    $genus_config = $this->config;

    if (!isset($genus_config['trait']['id'])) {
      // Genus is not configured.
      return 0;
    }

    $sql = "SELECT cvterm.* FROM {1:cvterm} LEFT JOIN {1:cv} USING (cv_id)
      WHERE cvterm.%s = :value AND cv.cv_id = :id";

    $field = isset($trait['id']) ? 'cvterm_id' : 'name';
    $sql = sprintf($sql, $field);

    // Query values.
    $args = [
      ':value' => $trait['id'] ?? $trait['name'],
      ':id' => $genus_config['trait']['id']
    ];

    // Query.
    $trait = $this->chado->query($sql, $args)
      ->fetchObject();

    return $trait ?? 0;
  }

  /**
   * Get trait method.
   *
   * @param array $trait
   *   Key:
   *     id - get method by trait id number (cvterm_id) or
   *     name - get method by trait name (cvterm.name).
   *
   * @return object
   *   Matching record/line in cvterm table (method).
   */
  public function getTraitMethod($trait) {
    // Configuration settings of the genus.
    $genus_config = $this->config;

    if (!isset($genus_config['method']['id']) && $this->terms['method_to_trait_relationship_type'] <= 0) {
      // Not configured genus and term.
      return 0;
    }

    $methods = [];
    $trait = $this->getTrait($trait);

    if ($trait) {
      // Get the method.
      $sql = "SELECT object_id AS id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = :t_id";

      // Query values.
      $args = [
        ':s_id' => (int) $trait->cvterm_id,
        ':t_id' => $this->terms['method_to_trait_relationship_type']
      ];

      // Query method/s
      $method_ids = $this->chado->query($sql, $args);
      $sql = "SELECT * FROM {1:cvterm} WHERE cvterm_id = :id AND cv_id = :c_id LIMIT 1";

      foreach($method_ids as $method_id) {
        // Resolve the method id.
        $method = $this->chado->query($sql, [':id' => $method_id->id, ':c_id' => $genus_config['method']['id']])
          ->fetchObject();

        if ($method) {
          $methods[] = $method;
        }
      }
    }

    return count($methods) > 0 ? $methods : 0;
  }

  /**
   * Get trait method unit and unit data type.
   *
   * @param integer $method_id
   *   Method id number (method cvterm id).
   *
   * @return object
   *   Matching record/line in cvterm table (unit) and
   *   unit data type.
   */
  public function getMethodUnit($method_id) {
    // Configuration settings of the genus.
    $genus_config = $this->config;

    if (!isset($genus_config['unit']['id']) && $this->terms['unit_to_method_relationship_type'] <= 0) {
      // Not configured genus and term.
      return 0;
    }

    $unit = 0;

    // Get unit.
    $sql = "SELECT object_id FROM {1:cvterm_relationship} WHERE subject_id = :s_id AND type_id = :t_id";

    // Query values.
    $args = [
      ':s_id' => (int) $method_id,
      ':t_id' => $this->terms['unit_to_method_relationship_type']
    ];

    // Query unit.
    $unit_id = $this->chado->query($sql, $args)
      ->fetchField();

    if ($unit_id) {
      $sql = "SELECT * FROM {1:cvterm} WHERE cvterm_id = :id AND cv_id = :c_id LIMIT 1";
      $unit = $this->chado->query($sql, [':id' => $unit_id, ':c_id' => $genus_config['unit']['id']])
        ->fetchObject();
    }

    return $unit;
  }

  /**
   * Get Trait Method Unit data type.
   *
   * @param integer $unit_id
   *   Method unit id number (unit cvterm id).
   *
   * @return string
   *   The data type of the unit, either quantitative or qualitative. False if not set.
   */
  public function getMethodUnitDataType($unit_id) {
    if ($this->terms['unit_type'] <= 0 && !$unit_id) {
      // Not configured term and no method id provided.
      return 0;
    }

    $sql = "SELECT value FROM {1:cvtermprop} WHERE cvterm_id = :c_id AND type_id = :t_id LIMIT 1";
    $data_type = $this->chado->query($sql, [':c_id' => $unit_id, ':t_id' => $this->terms['unit_type']])
      ->fetchField();

    return $data_type ?? 0;
  }
}
