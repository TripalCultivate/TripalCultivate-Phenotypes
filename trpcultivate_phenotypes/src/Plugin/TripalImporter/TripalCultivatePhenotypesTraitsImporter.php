<?php

/**
 * @file
 * Tripal Importer Plugin implementation for Tripal Cultivate Phenotypes - Traits Importer.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\TripalImporter;

use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;

/**
 * Tripal Cultivate Phenotypes - Traits Importer.
 *
 * An importer for traits with a defined method and unit.
 *
 * @TripalImporter(
 *   id = "trpcultivate-phenotypes-traits-importer",
 *   label = @Translation("Tripal Cultivate: Phenotypic Trait Importer"),
 *   description = @Translation("Loads Traits for phenotypic data into the system. This is useful for large phenotypic datasets to ease the upload process."),
 *   file_types = {"txt","tsv"},
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
  // Reference the validation result summary values in Drupal storage
  // system using this variable.
  private $validation_result = 'validation_result';

  // Headers required by this importer.
  private $headers = [
    'Trait Name' => 'The name of the trait, as you would like it to appear to the user (e.g. Days to Flower)',
    'Trait Description' => 'A full description of the trait. This is recommended to be at least one paragraph.',
    'Method Short Name' => 'A full, unique title for the method (e.g. Days till 10% of plants/plot have flowers)',
    'Collection Method' => 'A full description of how the trait was collected. This is also recommended to be at least one paragraph.',
    'Unit' => 'The full name of the unit used (e.g. days, centimeters)',
    'Type' => 'One of "Qualitative" or "Quantitative".'
  ];

  // Service: Make the following services available to all stages.
  // Genus Ontology configuration service.
  protected $service_genusontology;

  // Traits service.
  protected $service_traits;

  /**
   * Injection of services through setter methods.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Service genus ontology.
    $service_genusontology = $container->get('trpcultivate_phenotypes.genus_ontology');
    // Service traits.
    $service_traits = $container->get('trpcultivate_phenotypes.traits');

    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
      $service_genusontology,
      $service_traits
    );

    // Call service setter method to set the service.
    $instance->setServiceGenusOntology($service_genusontology);
    $instance->setServiceTraits($service_traits);

    return $instance;
  }

  /**
   * Configure all the validators this importer uses.
   *
   * @param array $form_values
   *   An array of the importer form values provided to formValidate.
   * @return array
   *   A listing of configured validator objects first keyed by
   *   their inputType. More specifically,
   *   - [inputType]: and array of validator instances. Not an
   *     associative array although the keys do indicate what
   *     order they should be run in.
   */
  public function configureValidators($form_values) {

    $validators = [];

    // Setup the plugin manager
    $manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Importer assets.
    // All values will be accessible to every instance of the validator Plugin.
    // This importer does not require a project and this variable is set to 0
    // instruct validators Project + Genus that relations project-genus can be ignored.
    $project = 0;
    $genus = $form_values['genus'];
    $file_id = $form_values['file_upload'];

    // Make the header columns into a simplified array where the header names
    // are the values
    $headers = array_keys($this->headers);

    // Take our simplified headers array and flip the array keys and values
    // This is the format that the validators will expect to know which indices
    // in the row of data to act on
    // For example: ['Trait Name'] => 0
    $header_index = array_flip($headers);

    // Set $skip to 0 since no validation is being done within this method
    $skip = 0;

    // -----------------------------------------------------
    // Metadata
    // - Genus Exists
    // @deprecated getValidatorIdWithScope in issue #91
    $validator = $manager->getValidatorIdWithScope('GENUS');
    $instance = $manager->createInstance($validator);
    // @deprecated loadAssets in issue #93
    $instance->loadAssets($project, $genus, $file_id, $headers, $skip);
    // @TODO: Rename according to the new validator_id for scope 'GENUS'
    $validators['metadata']['GENUS'] = $instance;

    // - Genus matches the configured project
    // @TODO: In a future PR, create instance for the genus-project validator
    //        and configure it

    // -----------------------------------------------------
    // File level
    // - File exists
    // @deprecated getValidatorIdWithScope in issue #91
    $validator = $manager->getValidatorIdWithScope('FILE');
    $instance = $manager->createInstance($validator);
    // @deprecated loadAssets in issue #93
    $instance->loadAssets($project, $genus, $file_id, $headers, $skip);
    // @TODO: Rename according to the new validator_id for scope 'FILE'
    $validators['file']['FILE'] = $instance;

    // -----------------------------------------------------
    // Header Level
    // - All header row cells are not empty.
    // @TODO: Uncomment the following code when the Headers validator has been
    //        updated and no longer uses the validate() method.
    // $instance = $manager->createInstance('empty_cell');
    // $context['indices'] = [
    //   $header_index['Trait Name'],
    //   $header_index['Trait Description'],
    //   $header_index['Method Short Name'],
    //   $header_index['Collection Method'],
    //   $header_index['Unit'],
    //   $header_index['Type']
    // ];
    // $instance->context = $context;
    // $validators['header-row']['empty_header_cell'] = $instance;

    // - All column headers match expected header format
    // @deprecated getValidatorIdWithScope in issue #91
    $validator = $manager->getValidatorIdWithScope('HEADERS');
    $instance = $manager->createInstance($validator);
    // @deprecated loadAssets in issue #93
    $instance->loadAssets($project, $genus, $file_id, $headers, $skip);
    // @TODO: Rename according to the new validator_id for scope 'HEADERS'
    $validators['header-row']['HEADERS'] = $instance;

    // -----------------------------------------------------
    // Data Row Level
    // - All data row cells in columns 0,2,4 are not empty
    $instance = $manager->createInstance('empty_cell');
    $indices = [
      $header_index['Trait Name'],
      $header_index['Method Short Name'],
      $header_index['Unit'],
      $header_index['Type']
    ];
    $instance->setIndices($indices);
    $validators['data-row']['empty_cell'] = $instance;

    // - The column 'Type' is one of "Qualitative" and "Quantitative"
    $instance = $manager->createInstance('value_in_list');
    $instance->setIndices([$header_index['Type']]);
    $instance->setValidValues([
      'Quantitative',
      'Qualitative'
    ]);
    $validators['data-row']['valid_data_type'] = $instance;

    // - The combination of Trait Name, Method Short Name and Unit is unique
    $instance = $manager->createInstance('duplicate_traits');
    // Set the logger since this validator uses a setter (setConfiguredGenus)
    // which may log messages
    $instance->setLogger($this->logger);
    $instance->setConfiguredGenus($genus);
    $instance->setIndices([
      'Trait Name' => $header_index['Trait Name'],
      'Method Short Name' => $header_index['Method Short Name'],
      'Unit' => $header_index['Unit']
    ]);
    $validators['data-row']['duplicate_traits'] = $instance;

    //$this->validatorObjects = $validators;

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
    if (isset($storage[ $this->validation_result ])) {
      $validation_result = $storage[ $this->validation_result ];

      $form['validation_result'] = [
        '#type' => 'inline_template',
        '#theme' => 'result_window',
        '#data' => [
          'validation_result' => $validation_result
        ],
        '#weight' => -100
      ];
    }

    // This is a reminder to user about expected trait data.
    $phenotypes_minder = t('This importer allows for the upload of phenotypic trait dictionaries in preparation
      for uploading phenotypic data. <br /><strong>This importer Does NOT upload phenotypic measurements.</strong>');
    \Drupal::messenger()->addWarning($phenotypes_minder);

    // Field Genus:
    // Prepare select options with only active genus.
    $all_genus = $this->service_genusontology->getConfiguredGenusList();
    $active_genus = array_combine($all_genus, $all_genus);

    if (!$active_genus) {
      $phenotypes_minder = t('This module is <strong>NOT configured</strong> to import Traits for analyzed phenotypes.');
      \Drupal::messenger()->addWarning($phenotypes_minder);
    }

    // If there is only one genus, it should be the default.
    $default_genus = 0;
    if ($active_genus && count($active_genus) == 1) {
      $default_genus = reset($active_genus);
    }

    // Field genus.
    $form['genus'] = array(
      '#type' => 'select',
      '#title' => 'Genus',
      '#description' => t('The genus of the germplasm being phenotyped with the supplied traits.
        Traits in this system are specific to the genus in order to ensure they are specific enough to accurately describe the phenotypes.
        In order for genus to be available here, it must be first configured in the Analyzed Phenotypes configuration.'),
      '#empty_option' => '- Select -',
      '#options' => $active_genus,
      '#default_value' => $default_genus,
      '#weight' => -99,
      '#required' => TRUE
    );

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
  }

  /**
   * {@inheritdoc}
   */
  public function formValidate($form, &$form_state) {

    $form_values = $form_state->getValues();

    $file_id = $form_values['file_upload'];

    // A FLAG to keep track if any validator fails.
    // We will only continue to the next input-type if all validators of the
    // current input-type pass.
    $failed_validator = FALSE;

    // Keep track of failed items.
    // We expect the first key to be a unique name of the validator instance
    // (as declared by the configureValidators() method) as there can be multiple
    // instances of one validator. For file-row input-type validators, this will
    // be further keyed by line number.
    $failures = [];

    // Configure the validators.
    $validators = $this->configureValidators($form_values);

    // ************************************************************************
    // Metadata Validation
    // ************************************************************************
    foreach ($validators['metadata'] as $validator_name => $validator) {
      // Set failures for this validator name to an empty array to signal that
      // this validator has been run.
      $failures[$validator_name] = [];
      // @TODO: Update to use the validateMetadata() method
      $result = $validator->validate();
      // Check for old return style...
      if (array_key_exists('status', $result) && ($result['status'] == 'fail')) {
        $failed_validator = TRUE;
        $failures[$validator_name] = $result;
      }
      // Then new return style.
      elseif (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
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
        // this validator has been run
        $failures[$validator_name] = [];
        // @TODO: Update to use the validateFile() method
        //$result = $validator->validateFile($form_value['filename'], $form_values['fid']);
        $result = $validator->validate();
        // Check for old return style...
        if (array_key_exists('status', $result) && ($result['status'] == 'fail')) {
          $failed_validator = TRUE;
          $failures[$validator_name] = $result;
        }
        // Then new return style.
        elseif (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
          $failed_validator = TRUE;
          $failures[$validator_name] = $result;
        }
      }
    }

    // Check if any previous validators failed before moving on to the next
    // input-type validation.
    if ($failed_validator === FALSE) {

      // Open the file so we can iterate through the rows
      $file = File::load($file_id);

      // Open and read file in this uri.
      $file_uri = $file->getFileUri();
      $handle = fopen($file_uri, 'r');

      // Get the mime type which is used to split the row.
      $file_mime_type = $file->getMimeType();

      // Line counter.
      $line_no = 0;

      // Begin column and row validation.
      while(!feof($handle)) {
        // Current row.
        $line = fgets($handle);
        $line_no++;

        // ********************************************************************
        // Header Row Validation
        // ********************************************************************
        if ($line_no == 1) {
          // Split line into an array of values.
          $header_row = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($line, $file_mime_type);
          foreach ($validators['header-row'] as $validator_name => $validator) {
            // Set failures for this validator name to an empty array to signal
            // that this validator has been run
            $failures[$validator_name] = [];
            // @TODO: Update to use the validateRow() method and use the split
            // $header_row above.
            $result = $validator->validate();

            // Check for old style...
            if (array_key_exists('status', $result) && ($result['status'] == 'fail')) {
              $failed_validator = TRUE;
              $failures[$validator_name] = $result;
            }
            // Then new style.
            elseif (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $failed_validator = TRUE;
              $failures[$validator_name] = $result;
            }
          }
          // If any header-row validators failed, skip validation of the data
          // rows.
          if ($failed_validator === TRUE) {
            break;
          }
        }

        // ********************************************************************
        // Data Row Validation
        // ********************************************************************
        else if ($line_no > 1) {
          // Split line into an array using the delimiter defined by this
          // importer in the configure values method above.
          $data_row = TripalCultivatePhenotypesValidatorBase::splitRowIntoColumns($line, $file_mime_type);

          // Call each validator on this row of the file.
          foreach($validators['data-row'] as $validator_name => $validator) {
            // Set failures for this validator name to an empty array to signal
            // that this validator has been run, ONLY if it doesn't already exist
            // (ie. this validator may have already failed on a previous row).
            if(!array_key_exists($validator_name, $failures)) {
              $failures[$validator_name] = [];
            }
            $result = $validator->validateRow($data_row);
            // Check if validation failed.
            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $failed_validator = TRUE;
              $failures[$validator_name][$line_no] = $result;
            }
          }
        }
      }
    }
    $validation_feedback = $this->processValidationMessages($failures);

    // Save all validation results in Drupal storage to create a summary report.
    $storage = $form_state->getStorage();
    $storage[ $this->validation_result ] = $validation_feedback;
    $form_state->setStorage($storage);

    if ($failed_validator === TRUE) {
      // Prevent this form from submitting and reload form with all the
      // validation failures in the storage system.
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Configures and processes the validation messages that will be shown to the
   * user of the importer
   *
   * @param array $failures
   *   An array containing the return values from any failed validators, keyed
   *   by the unique name assigned to each validator-input type combination
   *
   * @return array
   *   An array of feedback to provide to the user which summarizes the validation results 
   *   reported by the validators in the formValidate (i.e. $failures). This array is keyed 
   *   by a string that is associated with a line in the validate UI. Specifically,
   *   - 'validation_line': A string associated with a line that will be
   *     displayed to the user in the validate UI
   *     - 'title': A user-focussed message describing the validation that took
   *       place.
   *     - 'details': A user-focussed message describing the failure that
   *       occurred and any relevant details to help the user fix it.
   *     - 'status': One of: 'todo', 'pass', 'fail'.
   *     - 'raw_results': A nested array keyed by validator name, which contains
   *       the raw return values when validation failed. Essentially, the
   *       contents of $failures['validator_name'].
   */
  public function processValidationMessages($failures) {
    // Array to hold all the user feedback. Currently this includes an entry for each 
    // validator. However, in future designs we may combine more then one validator into a
    // single line in the validate UI and, thus, a single entry in this array. Everything is
    // set to status of 'todo' to start and will only change to one of 'pass' or
    // 'fail' if the $failures[] array is defined for that validator, indicating
    // that validation did take place.
    $messages = [
      // ----------------------------- METADATA --------------------------------
      'GENUS' => [
        'title' => 'Genus exists and/or matches the project/experiment',
        'status' => 'todo',
        'details' => ''
      ],
      // ------------------------------- FILE ----------------------------------
      'FILE' => [
        'title' => 'File is a valid tsv or txt',
        'status' => 'todo',
        'details' => ''
      ],
      // ---------------------------- HEADER ROW -------------------------------
      'HEADERS' => [
        'title' => 'File has all of the column headers expected',
        'status' => 'todo',
        'details' => ''
      ],
      // ----------------------------- DATA ROW --------------------------------
      'empty_cell' => [
        'title' => 'Required cells contain a value',
        'status' => 'todo',
        'details' => ''
      ],
      'valid_data_type' => [
        'title' => 'Values in required cells are valid',
        'status' => 'todo',
        'details' => ''
      ],
      'duplicate_traits' => [
        'title' => 'All trait-method-unit combinations are unique',
        'status' => 'todo',
        'details' => ''
      ]
    ];

    foreach($messages as $validator_name => $default_messages) {
      // Check if this validator exists in the failures array, which indicates
      // that it was run.
      if (array_key_exists($validator_name, $failures)) {

        // ----------------------------- PASS ----------------------------------
        // Check if $failures[$validator_name] is empty, which indicates there
        // are no errors to report for this validator.
        if (count($failures[$validator_name]) === 0 ) {
          $messages[$validator_name] = [
            'status' => 'pass',
          ];
        }

        // ----------------------------- FAIL ----------------------------------
        // Check if $failures[$validator_name] contains one of the results
        // keys, indicating that this is not a row-level validator and therefore
        // doesn't keep track of line numbers.
        else if (array_key_exists('case', $failures[$validator_name])) {
          // @todo: Update the message to not use the 'case' string by default
          // and to incorporate the 'failed_details'.
          $message = $failures[$validator_name]['case'];
          $messages[$validator_name] = [
            'status' => 'fail',
            'details' => $message,
            'raw_results' => $failures[$validator_name],
          ];
        }
        // @todo: Remove this if block when old validators GENUS, FILE, and
        // HEADERS are removed.
        else if (array_key_exists('details', $failures[$validator_name])){
          $message = $failures[$validator_name]['details'];
          $messages[$validator_name] = [
            'status' => 'fail',
            'details' => $message,
            'raw_results' => $failures[$validator_name],
          ];
        }
        // @todo: Check if this is a validator that keeps track of line numbers.
        // @assumption: Only data-row validators enter this else
        // block since BOTH:
        //   a) $failures[$validator_name] is not empty
        //   b) $failures[$validator_name]['case'] is not set
        // It would be better to validate that we have line numbers (integers)
        // then leave the else {} for anything outside of these options to throw
        // an exception for the developer. Reminder that:
        // $failures[$validator_name]['valid'] and
        // $failures[$validator_name]['failures']
        // also are valid but this scenario should have already been caught by
        // the previous if block.
        else {
          // @todo: Update this current approach to not report only the first
          // failure, but instead collect all the cases and failedItems and
          // formulate one concise, helpful feedback message.
          // foreach ($failures[$validator_name] as $line_no => $validator_results) {
          $first_failed_row = array_key_first($failures[$validator_name]);
          $message = $failures[$validator_name][$first_failed_row]['case'] . ' at row #: ' . $first_failed_row;
          $messages[$validator_name] = [
            'status' => 'fail',
            'details' => $message,
            'raw_results' => $failures[$validator_name],
          ];
        }
      }
    }

    return $messages;
  }

  /**
   * {@inheritDoc}
   */
  public function run() {
    // Traits service.
    $service_traits = \Drupal::service('trpcultivate_phenotypes.traits');

    // Values provided by user in the importer page.
    // Genus.
    $genus = $this->arguments['run_args']['genus'];
    // Instruct trait service that all trait assets will be contained in this genus.
    $service_traits->setTraitGenus($genus);
    // Traits data file id.
    $file_id = $this->arguments['files'][0]['fid'];
    // Load file object.
    $file = FILE::load($file_id);
    // Open and read file in this uri.
    $file_uri = $file->getFileUri();
    $handle = fopen($file_uri, 'r');

    // Line counter.
    $line_no = 0;
    // Headers.
    $headers = array_keys($this->headers);
    $headers_count = count($headers);

    while(!feof($handle)) {
      // Current row.
      $line = fgets($handle);

      if ($line_no > 0 && !empty(trim($line))) {
        // Line split into individual data point.
        $data_columns = str_getcsv($line, "\t");
        // Sanitize every data in rows and columns.
        $data = array_map(function($col) { return isset($col) ? trim(str_replace(['"','\''], '', $col)) : ''; }, $data_columns);

        // Construct trait array so that each data (value) in a line/row corresponds
        // to the column header (key).
        // ie. ['Trait Name' => data 1, 'Trait Description' => data 2 ...]
        $trait = [];

        // Fill trait metadata: name, description, method, unit and type.
        for ($i = 0; $i < $headers_count; $i++) {
          $trait[ $headers[ $i ] ] = $data[ $i ];
        }

        // Create the trait.

        // NOTE: Loading of this file is performed using a database transaction.
        // If it fails or is terminated prematurely then all insertions and updates
        // are rolled back and will not be found in the database.
        $service_traits->insertTrait($trait);

        unset($data);
      }

      // Next line;
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
    $column_headers = array_keys($this->headers);

    $file_link = \Drupal::service('trpcultivate_phenotypes.template_generator')
      ->generateFile($importer_id, $column_headers);

    // Additional notes to the headers.
    $notes = 'The order of the above columns is important and your file must include a header!
    If you have a single trait measured in more then one way (i.e. with multiple collection
    methods), then you should have one line per collection method with the trait repeated.';

    // Render the header notes/lists template and use the file link as
    // the value to href attribute of the link to download a template file.
    $build = [
      '#theme' => 'importer_header',
      '#data' => [
        'headers' => $this->headers,
        'notes' => $notes,
        'template_file' => $file_link
      ]
    ];

    return \Drupal::service('renderer')->renderPlain($build);
  }

  /**
   * Service setter method:
   * Set genus ontology configuration service.
   *
   * @param $service
   *   Service as created/injected through create method.
   *
   * @return void
   */
  public function setServiceGenusOntology($service) {
    if ($service) {
      $this->service_genusontology = $service;
    }
  }

  /**
   * Service setter method:
   * Set traits service.
   *
   * @param $service
   *   Service as created/injected through create method.
   *
   * @return void
   */
  public function setServiceTraits($service) {
    if ($service) {
      $this->service_traits = $service;
    }
  }
}
