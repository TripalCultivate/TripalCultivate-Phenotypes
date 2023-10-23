<?php

/**
 * @file
 * Tripal Cultivate Phenotypes File Template service definition.
 */

namespace Drupal\trpcultivate_phenotypes\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;

/**
 * Class TripalCultivatePhenotypesOntologyService.
 */
class TripalCultivatePhenotypesFileTemplateService {
  // Module configuration.
  protected $config;
  
  // Name of the current user logged in.
  private $user;

  /**
   * Constructor.  
   */
  public function __construct(ConfigFactoryInterface $config, AccountInterface $current_user) {
    $this->config = $config->get('trpcultivate_phenotypes.settings');
    
    // Sanitize the user display name by replacing all spaces into a dash character.
    // This will be used as part of the filename of template file.
    $username = str_replace(' ', '-', $current_user->getDisplayName());
    $this->user = $username;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Generate template file.
   */
  public function generateFile($importer_id, $column_headers) {
    // Fetch the configuration relating to directory for housing data collection template file.
    // This directory had been setup during install and had / at the end as defined.
    // @see configuration.
    $dir_template_file = $this->config->get('trpcultivate.phenotypes.directory.template_file');
    
    // About the template file:
    // Filename: importer id - data collection template file - username (TSV).
    $filename = $importer_id . '-data_collection-template-file-' . $this->user . '.tsv';
    // MIME: TSV type file.
    $filemime = 'text/tab-separated-values';
    
    // Create the file.
    $file = File::create([
      'filename' => $filename,
      'filemime' => $filemime,
      'uri' => $dir_template_file . $filename
    ]);
    // Mark file for deletion for Drupal maintenance.
    $file->set('status', 0);
    // Save.
    $file->save();

    // Write the contents, header to the file created and serve it to user for download.
    // File uri of the created file.
    $fileuri = $file->getFileUri();

    // Write the headers on the first line (header row) of the tsv file.
    // Headers: File column header to write coming from the importer form.
    $fileheaders = implode("\t", $column_headers);
    file_put_contents($fileuri, $fileheaders);
    
    return $file->createFileUrl();
  }
}