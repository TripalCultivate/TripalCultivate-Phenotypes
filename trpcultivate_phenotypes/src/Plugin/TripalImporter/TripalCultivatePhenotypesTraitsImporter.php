<?php

namespace Drupal\trpcultivate_phenotypes\Plugin\TripalImporter;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesFileTemplateService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tripal Cultivate Phenotypes - Traits Importer.
 *
 * An importer for traits with a defined method and unit.
 *
 * @TripalImporter(
 *   id = "trpcultivate-phenotypes-traits-importer",
 *   label = @Translation("Tripal Cultivate: Phenotypic Trait Importer"),
 *   description = @Translation("Loads Traits for phenotypic data into the system. This is useful for large phenotypic datasets to ease the upload process."),
 *   file_types = {"tsv"},
 *   upload_description = @Translation("Please provide a txt or tsv data file."),
 *   upload_title = @Translation("Phenotypic Trait Data File*"),
 *   use_analysis = FALSE,
 *   require_analysis = FALSE,
 *   use_button = True,
 *   submit_disabled = FALSE,
 *   button_text = "Import",
 *   file_upload = TRUE,
 *   file_local = FALSE,
 *   file_remote = FALSE,
 *   file_required = TRUE,
 *   cardinality = 1,
 *   menu_path = "",
 *   callback = "",
 *   callback_module = "",
 *   callback_path = "",
 * )
 */
class TripalCultivatePhenotypesTraitsImporter extends ChadoImporterBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Headers required by this importer.
   *
   * @var array
   *
   * The following keys are required:
   * - 'name': The column header name as it should appear in the input file.
   * - 'description': A user-friendly description of the header that will be
   *   displayed to the user through the form.
   * - 'type': one of "required" or "optional" to indicate whether the column
   *   needs to have values present or not.
   *
   * NOTE: Order MUST reflect the desired order of headers in the input file.
   */
  private $headers = [
    [
      'name' => 'Trait Name',
      'description' => 'The name of the trait, as you would like it to appear to the user (e.g. Days to Flower)',
      'type' => 'required',
    ],
    [
      'name' => 'Trait Description',
      'description' => 'A full description of the trait. This is recommended to be at least one paragraph.',
      'type' => 'required',
    ],
    [
      'name' => 'Method Short Name',
      'description' => 'A full, unique title for the method (e.g. Days till 10% of plants/plot have flowers)',
      'type' => 'required',
    ],
    [
      'name' => 'Collection Method',
      'description' => 'A full description of how the trait was collected. This is also recommended to be at least one paragraph.',
      'type' => 'required',
    ],
    [
      'name' => 'Unit',
      'description' => 'The full name of the unit used (e.g. days, centimeters)',
      'type' => 'required',
    ],
    [
      'name' => 'Type',
      'description' => 'One of "Qualitative" or "Quantitative".',
      'type' => 'required',
    ],
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Genus Ontology service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService
   */
  protected TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology;

  /**
   * Traits service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits;

  /**
   * The Validator Plugin Manager.
   *
   * @var Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorManager
   */
  protected TripalCultivatePhenotypesValidatorManager $service_validatorPluginManager;

  /**
   * The Entity Type Manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $service_entityTypeManager;

  /**
   * The TripalCultivatePhenotypes File Template Service.
   *
   * @var Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesFileTemplateService
   */
  protected TripalCultivatePhenotypesFileTemplateService $service_FileTemplate;

  /**
   * The Drupal Renderer.
   *
   * @var Drupal\Core\Render\Renderer
   */
  protected Renderer $service_Renderer;

  /**
   * The Drupal Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $service_Messenger;

  /**
   * Used to reference the validation result summary in the form.
   *
   * @var string
   */
  private $validation_result = 'validation_result';

  /**
   * Constructs the traits importer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology
   *   The genus ontology service.
   * @param Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   The traits service.
   * @param Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorManager $service_validatorPluginManager
   *   The validator plugin manager.
   * @param Drupal\Core\Entity\EntityTypeManager $service_entityTypeManager
   *   The entity type manager.
   * @param Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesFileTemplateService $service_FileTemplate
   *   The service used to generate the termplate file.
   * @param Drupal\Core\Render\Renderer $renderer
   *   The Drupal renderer service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ChadoConnection $chado_connection,
    TripalCultivatePhenotypesGenusOntologyService $service_PhenoGenusOntology,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
    TripalCultivatePhenotypesValidatorManager $service_validatorPluginManager,
    EntityTypeManager $service_entityTypeManager,
    TripalCultivatePhenotypesFileTemplateService $service_FileTemplate,
    Renderer $renderer,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $chado_connection);

    // Call service setter method to set the service.
    $this->setServiceGenusOntology($service_PhenoGenusOntology);
    $this->setServiceTraits($service_PhenoTraits);

    $this->service_validatorPluginManager = $service_validatorPluginManager;
    $this->service_entityTypeManager = $service_entityTypeManager;
    $this->service_FileTemplate = $service_FileTemplate;
    $this->service_Renderer = $renderer;
    $this->service_Messenger = $messenger;
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
      $container->get('trpcultivate_phenotypes.traits'),
      $container->get('plugin.manager.trpcultivate_validator'),
      $container->get('entity_type.manager'),
      $container->get('trpcultivate_phenotypes.template_generator'),
      $container->get('renderer'),
      $container->get('messenger'),
    );
  }

  /**
   * Configure all the validators this importer uses.
   *
   * @param array $form_values
   *   An array of the importer form values provided to formValidate.
   * @param string $file_mime_type
   *   A string of the MIME type of the input file, usually grabbed from the
   *   file object using $file->getMimeType()
   *
   * @return array
   *   A listing of configured validator objects first keyed by their inputType.
   *   More specifically:
   *   - [inputType]: and array of validator instances. Not an
   *     associative array although the keys do indicate what
   *     order they should be run in.
   */
  public function configureValidators(array $form_values, string $file_mime_type) {

    $validators = [];

    // Grab the genus from our form to use in configuring some validators.
    $genus = $form_values['genus'];

    // Make the header columns into a simplified array for easy reference:
    // - Keyed by the column header name.
    // - Values are the column header's position in the $headers property (ie.
    //   its index if we assume no keys were assigned).
    $header_index = [];
    $headers = $this->headers;
    foreach ($headers as $i => $column_details) {
      $header_index[$column_details['name']] = $i;
    }

    // -----------------------------------------------------
    // Metadata
    // - Genus exists and is configured
    $instance = $this->service_validatorPluginManager->createInstance('genus_exists');
    $validators['metadata']['genus_exists'] = $instance;

    // -----------------------------------------------------
    // File level
    // - File exists and is the expected type
    $instance = $this->service_validatorPluginManager->createInstance('valid_data_file');
    // Set supported mime-types using the valid file extensions (file_types) as
    // defined in the annotation for this importer on line 25.
    $supported_file_extensions = $this->plugin_definition['file_types'];
    $instance->setSupportedMimeTypes($supported_file_extensions);
    $validators['file']['valid_data_file'] = $instance;

    // -----------------------------------------------------
    // Raw row level
    // - File rows are properly delimited
    $instance = $this->service_validatorPluginManager->createInstance('valid_delimited_file');
    // Count the number of columns and configure it for this validator. We want
    // this number to be strict = TRUE, thus no extra columns are allowed.
    $num_columns = count($this->headers);
    $instance->setExpectedColumns($num_columns, TRUE);
    // Set the MIME type of this input file.
    $instance->setFileMimeType($file_mime_type);
    $validators['raw-row']['valid_delimited_file'] = $instance;

    // -----------------------------------------------------
    // Header Level
    // - All column headers match expected header format
    $instance = $this->service_validatorPluginManager->createInstance('valid_headers');
    // Use our $headers property to configure what we expect for a header in the
    // input file.
    $instance->setHeaders($this->headers);
    // Configure the expected number of columns and set it to be strict.
    $instance->setExpectedColumns($num_columns, TRUE);
    $validators['header-row']['valid_header'] = $instance;

    // -----------------------------------------------------
    // Data Row Level
    // - All data row cells in columns 0,2,4 are not empty
    $instance = $this->service_validatorPluginManager->createInstance('empty_cell');
    $indices = [
      $header_index['Trait Name'],
      $header_index['Method Short Name'],
      $header_index['Unit'],
      $header_index['Type'],
    ];
    $instance->setIndices($indices);
    $validators['data-row']['empty_cell'] = $instance;

    // - The column 'Type' is one of "Qualitative" and "Quantitative"
    $instance = $this->service_validatorPluginManager->createInstance('value_in_list');
    $instance->setIndices([$header_index['Type']]);
    $instance->setValidValues([
      'Quantitative',
      'Qualitative',
    ]);
    $validators['data-row']['valid_data_type'] = $instance;

    // - The combination of Trait Name, Method Short Name and Unit is unique
    $instance = $this->service_validatorPluginManager->createInstance('duplicate_traits');
    // Set the logger since this validator uses a setter (setConfiguredGenus)
    // which may log messages.
    $instance->setLogger($this->logger);
    $instance->setConfiguredGenus($genus);
    $instance->setIndices([
      'Trait Name' => $header_index['Trait Name'],
      'Method Short Name' => $header_index['Method Short Name'],
      'Unit' => $header_index['Unit'],
    ]);
    $validators['data-row']['duplicate_traits'] = $instance;

    return $validators;
  }

  /**
   * {@inheritDoc}
   */
  public function form($form, &$form_state) {
    // Always call the parent form to ensure Chado is handled properly.
    $form = parent::form($form, $form_state);

    // Validation result.
    $storage = $form_state->getStorage();

    // Full validation result summary.
    if (isset($storage[$this->validation_result])) {
      $validation_result = $storage[$this->validation_result];

      $form['validation_result'] = [
        '#type' => 'inline_template',
        '#theme' => 'result_window',
        '#data' => [
          'validation_result' => $validation_result,
        ],
        '#weight' => -100,
      ];
    }

    // This is a reminder to user about expected trait data.
    $phenotypes_minder = $this->t('This importer allows for the upload of phenotypic trait dictionaries in preparation
      for uploading phenotypic data. <br /><strong>This importer Does NOT upload phenotypic measurements.</strong>');
    $this->service_Messenger->addWarning($phenotypes_minder);

    // Field Genus:
    // Prepare select options with only active genus.
    $all_genus = $this->service_PhenoGenusOntology->getConfiguredGenusList();
    $active_genus = array_combine($all_genus, $all_genus);

    if (!$active_genus) {
      $phenotypes_minder = $this->t('This module is <strong>NOT configured</strong> to import Traits for analyzed phenotypes.');
      $this->service_Messenger->addWarning($phenotypes_minder);
    }

    // If there is only one genus, it should be the default.
    $default_genus = 0;
    if ($active_genus && count($active_genus) == 1) {
      $default_genus = reset($active_genus);
    }

    // Field genus.
    $form['genus'] = [
      '#type' => 'select',
      '#title' => 'Genus',
      '#description' => $this->t('The genus of the germplasm being phenotyped with the supplied traits.
        Traits in this system are specific to the genus in order to ensure they are specific enough to accurately describe the phenotypes.
        In order for genus to be available here, it must be first configured in the Analyzed Phenotypes configuration.'),
      '#empty_option' => '- Select -',
      '#options' => $active_genus,
      '#default_value' => $default_genus,
      '#weight' => -99,
      '#required' => TRUE,
    ];

    // This importer does not support using file sources from existing field.
    // #access: (bool) Whether the element is accessible or not; when FALSE,
    // the element is not rendered and the user submitted value is not taken
    // into consideration.
    $form['file']['file_upload_existing']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formSubmit($form, &$form_state) {
    // Display successful message to user if file import was without any error.
    $this->service_Messenger
      ->addStatus($this->t('<b>Your file import was successful and a Job Process Request has been created to securely save your data.</b>'));
  }

  /**
   * {@inheritdoc}
   */
  public function formValidate($form, &$form_state) {

    $form_values = $form_state->getValues();

    $file_id = $form_values['file_upload'];

    // Load our file object.
    $file = $this->service_entityTypeManager->getStorage('file')->load($file_id);

    // Get the mime type which is used to validate the file and split the rows.
    $file_mime_type = $file->getMimeType();

    // Configure the validators.
    $validators = $this->configureValidators($form_values, $file_mime_type);

    // A FLAG to keep track if any validator fails.
    // We will only continue to the next input-type if all validators of the
    // current input-type pass.
    $failed_validator = FALSE;

    // Keep track of failed items. This is a nested array keyed as follows:
    // - The unique name of a validator instance, which maps to the second level
    //   of the $validators array.
    //   - For row-level input-type validators, this is further keyed by the
    //     row number that the failure for this validator instance occurred.
    // The value (level 1 for non row-level validators, level 2 for row-level
    // validators) is the validation results array returned by the validator.
    $failures = [];

    // ************************************************************************
    // Metadata Validation
    // ************************************************************************
    foreach ($validators['metadata'] as $validator_name => $validator) {
      // Set failures for this validator name to an empty array to signal that
      // this validator has been run.
      $failures[$validator_name] = [];
      // Validate metadata input value.
      $result = $validator->validateMetadata($form_values);

      // Check if validation failed and save the results if it did.
      if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
        $failed_validator = TRUE;
        $failures[$validator_name] = $result;
      }
    }

    // Check if any previous validators failed before moving on to the next
    // input-type validation.
    if ($failed_validator === FALSE) {
      // **********************************************************************
      // File Validation
      // **********************************************************************
      foreach ($validators['file'] as $validator_name => $validator) {
        // Set failures for this validator name to an empty array to signal that
        // this validator has been run.
        $failures[$validator_name] = [];
        $result = $validator->validateFile($file_id);

        // Check if validation failed and save the results if it did.
        if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
          $failed_validator = TRUE;
          $failures[$validator_name] = $result;
        }
      }
    }

    // Check if any previous validators failed before moving on to the next
    // input-type validation.
    if ($failed_validator === FALSE) {

      // Open and read file in this uri.
      $file_uri = $file->getFileUri();
      $handle = fopen($file_uri, 'r');

      // Line counter.
      $line_no = 0;

      // Begin column and row validation.
      while (!feof($handle)) {
        // This variable will indicate if the validator has failed. It is set to
        // FALSE for every row to indicate that the line is valid to start with,
        // then execute the tests below to prove otherwise.
        $row_has_failed = FALSE;

        // Current row.
        $line = fgets($handle);
        $line_no++;
        // Skip this line if its empty, but line numbers should remain accurate.
        if (empty(trim($line))) {
          continue;
        }

        // ********************************************************************
        // Raw Row Validation
        // ********************************************************************
        foreach ($validators['raw-row'] as $validator_name => $validator) {
          // Set failures for this validator name to an empty array to signal
          // that this validator has been run.
          if (!array_key_exists($validator_name, $failures)) {
            $failures[$validator_name] = [];
          }

          $result = $validator->validateRawRow($line);

          // Check if validation failed and save the results if it did.
          if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
            $row_has_failed = TRUE;
            $failures[$validator_name][$line_no] = $result;
          }
        }

        // If any raw-row validators failed, skip further validation and move
        // on to the next row in the data file.
        if ($row_has_failed === TRUE) {
          $failed_validator = TRUE;
          continue;
        }

        // ********************************************************************
        // Header Row Validation
        // ********************************************************************
        if ($line_no == 1) {
          // Split line into an array of values.
          $header_row = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($line, $file_mime_type);

          foreach ($validators['header-row'] as $validator_name => $validator) {
            // Set failures for this validator name to an empty array to signal
            // that this validator has been run.
            if (!array_key_exists($validator_name, $failures)) {
              $failures[$validator_name] = [];
            }

            $result = $validator->validateRow($header_row);

            // Check if validation failed and save the results if it did.
            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $row_has_failed = TRUE;
              $failures[$validator_name] = $result;
            }
          }

          // If any header-row validators failed, skip validation of the data
          // rows.
          if ($row_has_failed === TRUE) {
            $failed_validator = TRUE;
            break;
          }
        }

        // ********************************************************************
        // Data Row Validation
        // ********************************************************************
        elseif ($line_no > 1) {
          // Split line into an array using the delimiter supported by this
          // importer when it was configured.
          $data_row = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($line, $file_mime_type);

          // Call each validator on this row of the file.
          foreach ($validators['data-row'] as $validator_name => $validator) {
            // Set failures for this validator name to an empty array to signal
            // that this validator has been run, but ONLY if it doesn't exist.
            // (ie. this validator may have already failed on a previous row, so
            // we don't want to overwrite previous validation failures.)
            if (!array_key_exists($validator_name, $failures)) {
              $failures[$validator_name] = [];
            }
            $result = $validator->validateRow($data_row);
            // Check if validation failed.
            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $row_has_failed = TRUE;
              $failed_validator = TRUE;
              $failures[$validator_name][$line_no] = $result;
            }
          }
        }
      }
      // Close the file.
      fclose($handle);
    }

    $validation_feedback = $this->processValidationMessages($failures);

    // Save all validation results in Drupal storage to create a summary report.
    $storage = $form_state->getStorage();
    $storage[$this->validation_result] = $validation_feedback;
    $form_state->setStorage($storage);

    // Check if the $validation_feedback contains 'fail' or 'todo' status.
    // If either is found, prevent form submission.
    $submit_form = TRUE;

    foreach ($validation_feedback as $feedback_item) {
      if ($feedback_item['status'] == 'todo' || $feedback_item['status'] == 'fail') {
        $submit_form = FALSE;

        // No need to inspect other validators, a single instance of fail/todo
        // is sufficient to prevent form submission.
        break;
      }
    }

    if ($submit_form === FALSE) {
      // Provide a general error message indicating that input values and/or the
      // data file may contain one or more errors.
      $this->service_Messenger
        ->addError($this->t('Your file import was not successful. Please check the Validation Result Window for errors and try again.'));

      // Prevent this form from submitting and reload form with all the
      // validation failures in the storage system.
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Configures and processes validation messages for the user.
   *
   * @param array $failures
   *   An array containing the return values from any failed validators. If
   *   validation was run for a validator instance, this is keyed by the unique
   *   name assigned to each validator-input type combination. This key will
   *   only contain values IF validation failed at any point that it was run. It
   *   is further keyed by row number IF the validator failed on that row as a
   *   row-level validator.
   *   Specifically:
   *   - [VALIDATOR INSTANCE NAME]
   *     - [ROW NUMBER (only if row-level validator)]
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': an array of items that failed. Structure of this
   *         array is dependent on the validator.
   *
   * @return array
   *   An array of feedback to provide to the user. It summarizes the validation
   *   results reported by the validators in formValidate (i.e. $failures). This
   *   array is keyed by a validation line, which is a string that is associated
   *   with a line in the validate UI dispalyed to the user. Specifically:
   *   - [VALIDATION LINE]:
   *     - 'title': A user-focused message describing the validation that took
   *       place.
   *     - 'status': One of: 'todo', 'pass', 'fail'.
   *     - 'details': A render array that will display details of any failures
   *       to guide the user to fix problems with their input file. The type of
   *       render array depends on the validator, but the most common types are
   *       item list and table.
   */
  public function processValidationMessages($failures) {
    // Array to hold all the user feedback. Currently this includes an entry for
    // each validator. However, in future designs we may combine more then one
    // validator into a single line in the validate UI and, thus, a single entry
    // in this array. Everything is set to status of 'todo' to start and will
    // only change to one of 'pass' or 'fail' if the $failures[] array is
    // defined for that validator, indicating that validation did take place.
    $messages = [
      // ----------------------------- METADATA --------------------------------
      'genus_exists' => [
        'title' => 'The genus is valid',
        'status' => 'todo',
        'details' => '',
      ],
      // ------------------------------- FILE ----------------------------------
      'valid_data_file' => [
        'title' => 'File is valid and not empty',
        'status' => 'todo',
        'details' => '',
      ],
      // ----------------------------- RAW ROW ---------------------------------
      'valid_delimited_file' => [
        'title' => 'Lines are properly delimited',
        'status' => 'todo',
        'details' => '',
      ],
      // ---------------------------- HEADER ROW -------------------------------
      'valid_header' => [
        'title' => 'File has all of the column headers expected',
        'status' => 'todo',
        'details' => '',
      ],
      // ----------------------------- DATA ROW --------------------------------
      'empty_cell' => [
        'title' => 'Required cells contain a value',
        'status' => 'todo',
        'details' => '',
      ],
      'valid_data_type' => [
        'title' => 'Values in required cells are valid',
        'status' => 'todo',
        'details' => '',
      ],
      'duplicate_traits' => [
        'title' => 'All trait-method-unit combinations are unique',
        'status' => 'todo',
        'details' => '',
      ],
    ];

    // A flag to indicate whether any data row level validation can be set to
    // pass or remains as 'todo' if there are no failures at that stage. This is
    // because we don't want to mislead the user to think all data rows pass
    // validation if there are raw rows that failed, since they haven't been
    // looked at yet by data row validators.
    $raw_row_failed = FALSE;

    // ---------------------- Process Validation Results -----------------------
    // For each validator:
    // 1. Check if $failures[$validator_name] exists, which indicates it was
    // run. If it was not run, then do nothing since it has already been marked
    // as "todo" in the $messages array.
    // 2. Check if $failures[$validator_name] is empty, which indicates that
    // validation passed and there are no errors to report for this validator.
    // 3. Otherwise, process failures for this validator with a dedicated method
    // that will build a render array of the feedback for the user.
    // -------------------------------------------------------------------------
    // GenusExists.
    $validator_name = 'genus_exists';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processGenusExistsFailures($failures[$validator_name]);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // ValidDataFile.
    $validator_name = 'valid_data_file';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processValidDataFileFailures($failures[$validator_name]);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // ValidDelimitedFile.
    $validator_name = 'valid_delimited_file';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        // Set this flag so that data row-level validation doesn't pass.
        $raw_row_failed = TRUE;
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processValidDelimitedFileFailures($failures[$validator_name]);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // ValidHeaders.
    $validator_name = 'valid_header';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processValidHeadersFailures($failures[$validator_name]);
      }
      else {
        $messages[$validator_name]['status'] = 'pass';
      }
    }

    // EmptyCell.
    $validator_name = 'empty_cell';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processEmptyCellFailures($failures[$validator_name]);
      }
      // Only pass if raw row validation didn't fail.
      elseif (!$raw_row_failed) {
        $messages[$validator_name]['status'] = 'pass';
      }
      // Otherwise, leave status as 'todo' since 1+ raw rows failed.
    }

    // Valid Data Type using the ValueInList validator.
    $validator_name = 'valid_data_type';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processValueInListFailures(
          $failures[$validator_name],
          ['Quantitative', 'Qualitative']
        );
      }
      // Only pass if raw row validation didn't fail.
      elseif (!$raw_row_failed) {
        $messages[$validator_name]['status'] = 'pass';
      }
      // Otherwise, leave status as 'todo' since 1+ raw rows failed.
    }

    // DuplicateTraits.
    $validator_name = 'duplicate_traits';
    if (array_key_exists($validator_name, $failures)) {
      if (!empty($failures[$validator_name])) {
        $messages[$validator_name]['status'] = 'fail';
        $messages[$validator_name]['details'] = $this->processDuplicateTraitsFailures($failures[$validator_name]);
      }
      // Only pass if raw row validation didn't fail.
      elseif (!$raw_row_failed) {
        $messages[$validator_name]['status'] = 'pass';
      }
      // Otherwise, leave status as 'todo' since 1+ raw rows failed.
    }

    return $messages;
  }

  /**
   * Processes failed validation from GenusExists into a render array.
   *
   * @param array $validation_result
   *   An associative array that was returned by the GenusExists validator in
   *   the event of failed validation. It contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'genus_provided': The name of the genus provided.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. Each item in the list contains the genus that was selected
   *   in the form which failed validation.
   */
  public function processGenusExistsFailures(array $validation_result) {
    if ($validation_result['case'] == 'Genus does not exist') {
      $message = 'The selected genus does not exist in this site. Please contact your administrator to have this added.';
    }
    elseif ($validation_result['case'] == 'Genus exists but is not configured') {
      $message = 'The selected genus has not yet been configured for use with phenotypic data. Please contact your administrator to have this set up.';
    }

    // Build the render array.
    $render_array = [
      '#type' => 'item',
      '#title' => $message,
      '#wrapper_attributes' => [
        'class' => [
          'tcp-genus-exists-failures',
        ],
      ],
      'items' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => [
          [
            '#markup' => $validation_result['failedItems']['genus_provided'],
          ],
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Processes failed validation from ValidDataFile into a render array.
   *
   * @param array $validation_result
   *   An associative array that was returned by the ValidDataFile validator in
   *   the event of failed validation. It contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with one or more of the
   *     following keys:
   *     - 'filename': The provided name of the file.
   *     - 'fid': The fid of the provided file.
   *     - 'mime': The mime type of the input file if it is not supported.
   *     - 'extension': The extension of the input file if not supported.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. The one item in the list is either the filename, as below:
   *   - Filename: $validation_result['failedItems']['filename']
   *   OR it is a message informing the user that their file's extension and
   *   mime type are not compatible.
   */
  public function processValidDataFileFailures(array $validation_result) {
    // Get the current user in case we trigger a case that needs to log a
    // message to the administrator.
    $current_user = \Drupal::currentUser();
    $username = $current_user->getAccountName();
    // Define our items array.
    $items = [];

    if (($validation_result['case'] == 'Invalid file id number') ||
        ($validation_result['case'] == 'File id failed to load a file object')) {
      $message = 'A problem occurred in between uploading the file and submitting it for validation. Please try uploading and submitting it again, or contact your administrator if the problem persists.';

      // Get the fid of the uploaded file.
      $fid = $validation_result['failedItems']['fid'];
      // Log a message for the administrator to help with debugging the issue.
      $this->logger->info("The user $username uploaded a file with FID $fid using the Traits Importer, but could not import it as something is wrong with the filename/FID. More specifically, the case message '" . $validation_result['case'] . "' was reported.");
    }
    elseif ($validation_result['case'] == 'The file has no data and is an empty file') {
      $message = 'The file provided has no contents in it to import. Please ensure your file has the expected header row and at least one row of data.';
      $items = [
        'Filename: ' . $validation_result['failedItems']['filename'],
      ];
    }
    elseif (($validation_result['case'] == 'Unsupported file MIME type') ||
            ($validation_result['case'] == 'Unsupported file mime type and unsupported extension')) {
      $message = "The type of file uploaded is not supported by this importer. Please ensure your file has one of the supported file extensions and was saved using software that supports that type of file. For example, a 'tsv' file should be saved as such by a spreadsheet editor such as Microsoft Excel.";
      // Give more info to the user AND log a message to the administrator using
      // these failed items:
      $file_mime = $validation_result['failedItems']['mime'];
      $file_extension = $validation_result['failedItems']['extension'];
      $items = [
        "The file extension indicates the file is \"$file_extension\" but our system detected the file is of type \"$file_mime\"",
      ];
      $this->logger->info("The user $username uploaded a file to the Traits Importer with file extension \"$file_extension\" and mime type \"$file_mime\"");
    }
    elseif ($validation_result['case'] == 'Data file cannot be opened') {
      $message = 'The file provided could not be opened. Please contact your administrator for help.';
      $filename = $validation_result['failedItems']['filename'];
      $fid = $validation_result['failedItems']['fid'];
      $items = [
        'Filename: ' . $filename,
      ];
      // Log more info for the administrator.
      $this->logger->info("The user $username uploaded a file with FID $fid using the Traits Importer, but the file could not be opened using \'@fopen\'. Filename was '$filename'.");
    }

    // Build the render array.
    $render_array = [
      '#type' => 'item',
      '#title' => $message,
      '#wrapper_attributes' => [
        'class' => [
          'tcp-valid-data-file-failures',
        ],
      ],
      'items' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => $items,
      ],
    ];

    return $render_array;
  }

  /**
   * Processes failed validation from ValidHeaders into a render array.
   *
   * @param array $validation_result
   *   An associative array that was returned by the ValidHeaders validator in
   *   the event of failed validation. It contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed, either:
   *     - 'headers': A string indicating the header row is empty.
   *     - an array of column headers that was in the input file.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. This unordered list will include a table with a row of the
   *   expected headers followed by a row of the provided headers.
   */
  public function processValidHeadersFailures(array $validation_result) {
    if ($validation_result['case'] == 'Header row is an empty value') {
      $message = 'The file has an empty row where the header was expected.';
      $provided_headers = [];
    }
    elseif ($validation_result['case'] == 'Headers do not match expected headers') {
      $message = 'One or more of the column headers in the input file does not match what was expected. Please check if your column header is in the correct order and matches the template exactly.';
      $provided_headers = $validation_result['failedItems'];
    }
    elseif ($validation_result['case'] == 'Headers provided does not have the expected number of headers') {
      $num_expected_columns = count($this->headers);
      $message = "This importer requires a strict number of $num_expected_columns column headers. Please ensure your column header matches the template exactly and remove any additional column headers from the file.";
      $provided_headers = $validation_result['failedItems'];
    }
    // Get the expected and actual headers to build the rows in our table render
    // array.
    $expected_headers = array_column($this->headers, 'name');

    // Build the render array.
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tcp-valid-headers-failures',
        ],
      ],
      '#items' => [
        [
          [
            '#prefix' => '<div class="case-message">',
            '#markup' => $message,
            '#suffix' => '</div>',
          ],
          [
            '#type' => 'table',
            '#attributes' => [],
            '#rows' => [
              [
                'data' => [
                  'header' => [
                    'data' => 'Expected Headers',
                    'header' => TRUE,
                  ],
                ] + $expected_headers,
                'class' => ['expected-headers'],
              ],
              [
                'data' => [
                  'header' => [
                    'data' => 'Provided Headers',
                    'header' => TRUE,
                  ],
                ] + $provided_headers,
                'class' => ['provided-headers'],
              ],
            ],
          ],
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Processes failed validation from ValidDelimitedFile into a render array.
   *
   * @param array $failures
   *   An associative array that stores the validation failures by the
   *   ValidDelimitedFile validator. It is keyed by the line number of the input
   *   file where validation failed, and the value is an associative array
   *   returned by the validator. Here is the overall structure of $failures:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed:
   *       - 'raw_row': A string indicating the row is empty OR the contents of
   *         the row as it appears in the file.
   *       - 'expected_columns': The number of columns expected in the input
   *         file as determined by calling getExpectedColumns().
   *       - 'strict': A boolean indicating whether the number of expected
   *         columns by the validator is strict (TRUE) or is the minimum number
   *         required (FALSE).
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case(s) that failed and the failed items from the
   *   input file. This unordered list will include a table for each potential
   *   case in the $failures array:
   *   - A table for lines that are empty or contain unsupported delimiters
   *   - A table for lines that once delimited, do not contain the expected
   *     number of columns.
   *   Both tables contain the following headers:
   *   - 'Line Number'
   *   - 'Line Contents'
   */
  public function processValidDelimitedFileFailures(array $failures) {
    // Define our table headers.
    $table_header = ['Line Number', 'Line Contents'];

    // For this validator there can be up to 2 tables:
    // - 'table'->'unsupported': Empty rows or no supported delimiters present.
    // - 'table'->'delimited': Rows that don't delimit to the expected number of
    //   columns.
    $table = [];
    // Loop through each row in the $failures array and piece apart the
    // different cases into different tables.
    foreach ($failures as $line_no => $validation_result) {
      // Keeps track of which table this one line's validation result gets added
      // to based on the case it triggered.
      $table_case = '';
      if (($validation_result['case'] == 'Raw row is empty') ||
          ($validation_result['case'] == 'None of the delimiters supported by the file type was used')) {
        $table_case = 'unsupported';
      }
      elseif (($validation_result['case'] == 'Raw row exceeds number of strict columns') ||
            ($validation_result['case'] == 'Raw row has insufficient number of columns')) {
        $table_case = 'delimited';
        if (!isset($num_expected_columns)) {
          $num_expected_columns = $validation_result['failedItems']['expected_columns'];
          $strict = $validation_result['failedItems']['strict'];
        }
      }

      // Checked all cases, now add a row to our appropriate table.
      if (!array_key_exists($table_case, $table)) {
        // Declare the array storing rows for this table, if not already.
        $table[$table_case]['rows'] = [];
      }
      $table[$table_case]['rows'][] = [
        $line_no,
        $validation_result['failedItems']['raw_row'],
      ];
    }
    // Check which tables were created, and assign the correct message.
    // Note that both tables can exist at the same time.
    if (array_key_exists('unsupported', $table)) {
      $table['unsupported']['message'] = 'The following lines in the input file do not contain a valid delimiter supported by this importer.';
    }
    if (array_key_exists('delimited', $table)) {
      // Check if number of columns is strict, then set the message accordingly.
      if ($strict) {
        $strict_or_min = 'strict';
      }
      else {
        $strict_or_min = 'minimum';
      }
      $message = "This importer requires a $strict_or_min number of $num_expected_columns columns for each line. The following lines do not contain the expected number of columns.";
      $table['delimited']['message'] = $message;
    }

    // Finally, loop through our tables and build our render array.
    $tables = [];
    foreach ($table as $table_key => $table_case) {
      $tables[] = [
        [
          '#prefix' => '<div class="case-message case-' . $table_key . '">',
          '#markup' => $table_case['message'],
          '#suffix' => '</div>',
        ],
        [
          '#type' => 'table',
          '#header' => $table_header,
          '#attributes' => [
            'class' => [
              'tcp-raw-row',
              'table-case-' . $table_key,
            ],
          ],
          '#rows' => $table_case['rows'],
        ],
      ];
    }
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tcp-valid-delimited-file-failures',
        ],
      ],
      '#items' => $tables,
    ];

    return $render_array;
  }

  /**
   * Processes failed validation from EmptyCell into a render array.
   *
   * @param array $failures
   *   An associative array that stores the validation failures by the
   *   EmptyCell validator. It is keyed by the line number of the input
   *   file where validation failed, and the value is an associative array
   *   returned by the validator. Here is the overall structure of $failures:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed:
   *       - 'empty_indices': A list of column indices in the line which were
   *         checked and found to be empty.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case(s) that failed and the failed items from the
   *   input file. This unordered list will include a table that lists the row
   *   and column combinations with empty cells. It has the following headers:
   *   - 'Line Number'
   *   - 'Column(s) with empty value'
   */
  public function processEmptyCellFailures(array $failures) {
    // Define our table header.
    $table_header = ['Line Number', 'Column(s) with empty value'];
    $table['rows'] = [];

    foreach ($failures as $line_no => $validation_result) {
      if ($validation_result['case'] == 'Empty value found in required column(s)') {
        $table['message'] = 'The following line number and column header combinations were empty, but a value is required.';
        // Convert indices in failedItems to column headers.
        $failed_indices = $validation_result['failedItems']['empty_indices'];
        // For each index with an empty value, grab the column name from our
        // $headers property and add to an array of header names.
        $empty_headers = [];
        foreach ($failed_indices as $index) {
          array_push($empty_headers, $this->headers[$index]['name']);
        }
        // Implode the empty headers array into a string and then add it as a
        // row to our table.
        $columns_string = implode(", ", $empty_headers);
        array_push($table['rows'], [
          $line_no,
          $columns_string,
        ]);
      }
    }

    // Build the render array for our table.
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tcp-empty-cell-failures',
        ],
      ],
      '#items' => [
        [
          [
            '#prefix' => '<div class="case-message">',
            '#markup' => $table['message'],
            '#suffix' => '</div>',
          ],
          [
            '#type' => 'table',
            '#header' => $table_header,
            '#attributes' => [],
            '#rows' => $table['rows'],
          ],
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Processes failed validation from ValueInList into a render array.
   *
   * @param array $failures
   *   An associative array that stores the validation failures by the
   *   ValueinList validator. It is keyed by the line number of the input
   *   file where validation failed, and the value is an associative array
   *   returned by the validator. Here is the overall structure of $failures:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   * @param array $expected_values
   *   A list of valid values that was provided to the ValueinList validator
   *   instance that is being processed for feedback to the user.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case(s) that failed and the failed items from the
   *   input file. This unordered list will include a table that lists the row
   *   and column combinations with an invalid value. It has the following
   *   headers:
   *   - 'Line Number'
   *   - Column Header(s) of the cell(s) that has/have an invalid value
   */
  public function processValueInListFailures(array $failures, array $expected_values) {
    // Define our table header.
    // We will start with the line number and build the header from there as we
    // go through the failures. There will be a column for each column checked
    // by this validator instance and the column header will be the same as it
    // appears in the file.
    $table_header = [-1 => 'Line Number'];
    $table['rows'] = [];

    foreach ($failures as $line_no => $validation_result) {
      if ($validation_result['case'] == 'Invalid value(s) in required column(s)') {
        $table['message'] = 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $expected_values) . '". Note that values should be case sensitive. <strong>If any cell in the table below is empty, then the value given in the file for that cell was one of the allowed values.</strong>';

        // Define a new row in our table for this line number.
        $table['rows'][$line_no][-1] = $line_no;
        // For each index with an invalid value, grab the column name from our
        // $headers property and add it to our table header.
        foreach ($validation_result['failedItems'] as $index => $failed_value) {
          // Grab the column name based on the index of the invalid value
          // and add it to this table header if it's not already there.
          $column_name = $this->headers[$index]['name'];
          if (!array_key_exists($column_name, $table_header)) {
            $table_header[$index] = $column_name;
          }
          // Now add a cell to the table to indicate this invalid value.
          // We reuse the index from the original file as the key to preserve
          // the same order of the columns. We also key the row with the line
          // number to ensure that a line with more then one failure is
          // compiled into a single row.
          $table['rows'][$line_no][$index] = $failed_value;
        }
      }
    }

    // If our table has more than 2 columns with failed values, then iterate
    // through and pad the table with empty strings where necessary.
    if (count($table_header) > 2) {
      foreach (array_keys($table['rows']) as $line_no) {
        foreach (array_keys($table_header) as $index) {
          if (!array_key_exists($index, $table['rows'][$line_no])) {
            $table['rows'][$line_no][$index] = '';
          }
        }
        // Finally, sort the row by keys.
        ksort($table['rows'][$line_no]);
      }
    }

    // Sort the table header.
    ksort($table_header);

    // Build the render array for our table.
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tcp-value-in-list-failures',
        ],
      ],
      '#items' => [
        [
          [
            '#prefix' => '<div class="case-message">',
            '#markup' => $table['message'],
            '#suffix' => '</div>',
          ],
          [
            '#type' => 'table',
            '#header' => $table_header,
            '#attributes' => [],
            '#rows' => $table['rows'],
          ],
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Processes failed validation from DuplicateTraits into a render array.
   *
   * @param array $failures
   *   An associative array that stores the validation failures by the
   *   DuplicateTraits validator. It is keyed by the line number of the input
   *   file where validation failed, and the value is an associative array
   *   returned by the validator. Here is the overall structure of $failures:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, keyed by:
   *       - 'combo_provided': The combination of trait, method, and unit
   *         provided in the file. The keys used are the same name of the column
   *         header for the cell containing the failed value.
   *         - 'Trait Name': The trait name provided in the file.
   *         - 'Method Short Name': The method name provided in the file.
   *         - 'Unit': The unit provided in the file.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case(s) that failed and the failed items from the
   *   input file. This unordered list will include a table for each potential
   *   case in the $failures array:
   *   - A table for duplicate trait-method-unit combos seen in the input file
   *   - A table for duplicate trait-method-unit combos found in the database
   *   Both tables contain the following headers:
   *   - 'Line Number'
   *   - 'Trait Name'
   *   - 'Method Short Name'
   *   - 'Unit'
   */
  public function processDuplicateTraitsFailures(array $failures) {
    // Define our table headers.
    $trait = 'Trait Name';
    $method = 'Method Short Name';
    $unit = 'Unit';
    $table_header = ['Line Number', $trait, $method, $unit];

    // For this validator there are can be up to 2 tables:
    // - 'table'->'file': Duplicates found within the input file.
    // - 'table'->'database': Duplicates found within the database.
    $table = [];
    // Loop through each row in the $failures array and piece apart the
    // different cases into different tables.
    foreach ($failures as $line_no => $validation_result) {
      // Keeps track of which table this one line's validation result gets added
      // to based on the case it triggered.
      $table_case = [];
      if ($validation_result['case'] == 'A duplicate trait was found within the input file') {
        $table_case = ['file'];
      }
      elseif ($validation_result['case'] == 'A duplicate trait was found in the database') {
        $table_case = ['database'];
      }
      elseif ($validation_result['case'] == 'A duplicate trait was found within both the input file and the database') {
        $table_case = ['file', 'database'];
      }
      // Now set values that should appear for this row in the table(s) for this
      // particular case.
      foreach ($table_case as $case) {
        // Declare the array storing rows for this table, if not already.
        if (!array_key_exists($case, $table)) {
          $table[$case]['rows'] = [];
        }
        array_push($table[$case]['rows'], [
          $line_no,
          $validation_result['failedItems']['combo_provided'][$trait],
          $validation_result['failedItems']['combo_provided'][$method],
          $validation_result['failedItems']['combo_provided'][$unit],
        ]);
      }
    }
    // Check which tables were created, and assign the correct message.
    // Note that both tables can exist at the same time, hence not an 'elseif'.
    if (array_key_exists('file', $table)) {
      $table['file']['message'] = 'These trait-method-unit combinations occurred multiple times within your input file. The line number indicates the duplicated occurrence(s).';
    }
    if (array_key_exists('database', $table)) {
      $table['database']['message'] = 'These trait-method-unit combinations have already been imported into this site. Please confirm the existing version fully represents your data. If it does, then you can remove it from your input file. If not then you need to make the names more specific.';
    }

    // Finally, loop through our tables and build our render array.
    $tables = [];
    foreach ($table as $table_key => $table_case) {
      array_push($tables, [
        [
          '#prefix' => '<div class="case-message case-' . $table_key . '">',
          '#markup' => $table_case['message'],
          '#suffix' => '</div>',
        ],
        [
          '#type' => 'table',
          '#header' => $table_header,
          '#attributes' => [
            'class' => [
              'table-case-' . $table_key,
            ],
          ],
          '#rows' => $table_case['rows'],
        ],
      ]);
    }
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tcp-duplicate-traits-failures',
        ],
      ],
      '#items' => $tables,
    ];

    return $render_array;
  }

  /**
   * {@inheritDoc}
   */
  public function run() {
    // Values provided by user in the importer page.
    $genus = $this->arguments['run_args']['genus'];
    // Tell trait service that all trait assets will be contained in this genus.
    $this->service_PhenoTraits->setTraitGenus($genus);
    // Traits data file id.
    $file_id = $this->arguments['files'][0]['fid'];
    // Load file object.
    $file = $this->service_entityTypeManager->getStorage('file')->load($file_id);
    // Open and read file in this uri.
    $file_uri = $file->getFileUri();
    $handle = fopen($file_uri, 'r');

    // Line counter.
    $line_no = 0;
    // Headers.
    // Only the header names are needed, so pull them out into a new array.
    $headers = array_column($this->headers, 'name');
    $headers_count = count($headers);

    while (!feof($handle)) {
      // Current row.
      $line = fgets($handle);

      if ($line_no > 0 && !empty(trim($line))) {
        // Line split into individual data point.
        $data_columns = str_getcsv($line, "\t");
        // Sanitize every data in rows and columns.
        $data = array_map(function ($col) {
          return isset($col) ? trim(str_replace(['"', '\''], '', $col)) : '';
        }, $data_columns);

        // Build trait array so that each data (value) in a line/row corresponds
        // to the column header (key).
        // ie. ['Trait Name' => data 1, 'Trait Description' => data 2 ...].
        $trait = [];
        // Fill trait metadata: name, description, method, unit and type.
        for ($i = 0; $i < $headers_count; $i++) {
          $trait[$headers[$i]] = $data[$i];
        }

        // Create the trait.
        // NOTE: Loading of this file is performed using a database transaction.
        // If it fails or is terminated prematurely, then all insertions and
        // updates are rolled back and will not be found in the database.
        $this->service_PhenoTraits->insertTrait($trait);

        unset($data);
      }

      // Next line.
      $line_no++;
    }

    // Close the file.
    fclose($handle);
  }

  /**
   * {@inheritdoc}
   */
  public function postRun() {

  }

  /**
   * {@inheritdoc}
   */
  public function describeUploadFileFormat() {
    // A template file has been generated and is ready for download.
    $importer_id = $this->pluginDefinition['id'];
    // Only the header names are needed for making the template file, so pull
    // them out into a new array.
    $column_headers = array_column($this->headers, 'name');

    $file_link = $this->service_FileTemplate
      ->generateFile($importer_id, $column_headers);

    // Additional notes to the headers.
    $notes = $this->t('The order of the above columns is important and your file must include a header!
    If you have a single trait measured in more than one way (i.e. with multiple collection
    methods), then you should have one line per collection method with the trait repeated.');

    // Render the header notes/lists template and use the file link as
    // the value to href attribute of the link to download a template file.
    $build = [
      '#theme' => 'importer_header',
      '#data' => [
        'headers' => $this->headers,
        'notes' => $notes,
        'template_file' => $file_link,
      ],
    ];

    return $this->service_Renderer->renderPlain($build);
  }

  /**
   * Set phenotype genus ontology configuration service.
   *
   * @param Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesGenusOntologyService $service
   *   The PhenoGenoOntology service as created/injected through create method.
   */
  public function setServiceGenusOntology(TripalCultivatePhenotypesGenusOntologyService $service) {
    if ($service) {
      $this->service_PhenoGenusOntology = $service;
    }
  }

  /**
   * Set phenotype traits service.
   *
   * @param \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService $service
   *   The PhenoTraits service as created/injected through create method.
   */
  public function setServiceTraits($service) {
    if ($service) {
      $this->service_PhenoTraits = $service;
    }
  }

}
