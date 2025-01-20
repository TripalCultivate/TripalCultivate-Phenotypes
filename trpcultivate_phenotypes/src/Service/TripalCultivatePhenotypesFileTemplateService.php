<?php

namespace Drupal\trpcultivate_phenotypes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\FileTypes;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;

/**
 * Generate data collection template file used in the importer.
 */
class TripalCultivatePhenotypesFileTemplateService {

  /**
   * Validator Traits required by this validator.
   *
   * - FileTypes: Gets an array of all supported MIME types the importer is
   *   configured to process.
   *
   * @todo Update when/if $extension_to_mime_mapping is moved to a more generic
   * class (ie. not validator specific).
   */
  use FileTypes;

  /**
   * Module configuration.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Drupal user account.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Constructor.
   *
   * @param Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration interface.
   * @param Drupal\Core\Session\AccountInterface $user
   *   Account interface.
   */
  public function __construct(ConfigFactoryInterface $config, AccountInterface $user) {
    // Set the configuration.
    $this->config = $config->get('trpcultivate_phenotypes.settings');

    // Set the current user.
    $this->user = $user;
  }

  /**
   * Generate template file.
   *
   * @param string $importer_id
   *   String, The plugin ID annotation definition used to prefix the filename.
   * @param array $column_headers
   *   An array of column headers to be written into the template file as the
   *   column header row.
   * @param array $file_extensions
   *   The file extension of the template file. This is taken from the
   *   'file_type' plugin annotation definition of the Importer.
   *
   *   NOTE: Only the first item is used as the primary file extension, in case
   *   of multiple file type values were provided.
   *
   * @return string
   *   The relative path to the generated template file.
   *
   * @see src/TripalCultivateValidator/TripalCultivatePhenotypesValidatorBase.php
   * @see src/TripalCultivateValidator/ValidatorTraits/FileTypes.php
   */
  public function generateFile($importer_id, $column_headers, $file_extensions) {

    // Fetch the configuration relating to directory for housing data collection
    // template file. This directory had been setup during install and had / at
    // the end as defined. @see config install and schema.
    $dir_template_file = $this->config->get('trpcultivate.phenotypes.directory.template_file');

    // About the template file:
    // File extension.
    $file_extension = $file_extensions[0];
    // See referenced file in the doc block about the mapping variables used.
    // File MIME type.
    // @todo Should move the following 2 methods to a more generic class
    // instead of keeping them in FileTypes and ValidatorBase which are meant
    // for validators.
    $file_mime_type = self::$extension_to_mime_mapping[$file_extension];
    // File delimiter.
    $file_delimiter = TripalCultivatePhenotypesValidatorBase::$mime_to_delimiter_mapping[$file_mime_type[0]];

    // Personalize the filename by appending display name of the current user,
    // but first sanitize it by replacing all spaces into a dash character.
    $display_name = $this->user->getDisplayName() ?? 'anonymous-user';
    $user_display_name = str_replace(' ', '-', $display_name);

    // Filename: importer id - data collection template file - username . (TSV).
    $filename = $importer_id . '-data-collection-template-file-' . $user_display_name . '.' . $file_extension;

    // Create the file.
    $file = File::create([
      'filename' => $filename,
      'filemime' => $file_mime_type,
      'uri' => $dir_template_file . $filename,
    ]);

    // Mark file for deletion during a Drupal maintenance.
    $file->set('status', 0);

    // Write the contents: headers into the file created and serve the path back
    // to the calling Importer as value to the href attribute of link to
    // download a template file. File uri of the created file.
    $fileuri = $file->getFileUri();

    // Before we can write contents, we need to ensure the upper level
    // folders exist.
    if (!file_exists($dir_template_file)) {
      mkdir($dir_template_file, 0777, TRUE);
    }

    // Convert the headers array into a delimited string value and post into the
    // first line of the file.
    $fileheaders = implode($file_delimiter[0], $column_headers) . "\n# DELETE THIS LINE --- START DATA HERE AND USE APPROPRIATE DELIMITER/VALUE SEPARATOR #";
    file_put_contents($fileuri, $fileheaders);

    // Save.
    $file->save();

    return $file->createFileUrl();
  }

}
