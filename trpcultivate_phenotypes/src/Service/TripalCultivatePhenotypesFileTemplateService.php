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
 * Class TripalCultivatePhenotypesFileTemplateService.
 */
class TripalCultivatePhenotypesFileTemplateService {
  // Module configuration.
  protected $config;
  
  // Drupal current user.
  protected $user;

  /**
   * Constructor.  
   */
  public function __construct(ConfigFactoryInterface $config, AccountInterface $current_user) {
    $this->config = $config->get('trpcultivate_phenotypes.settings');
    $this->user   = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Generate template file.
   * 
   * @param string $importer_id
   *   String, The plugin ID annotation definition.
   * @param array $column_headers
   *   Array keys (column headers) as defined by the header property in the importer.
   * 
   * @return path
   *   Path to the template file. 
   */
  public function generateFile($importer_id, $column_headers) {
    // Fetch the configuration relating to directory for housing data collection template file.
    // This directory had been setup during install and had / at the end as defined.
    // @see config install and schema.
    $dir_template_file = $this->config->get('trpcultivate.phenotypes.directory.template_file');
    
    // About the template file:

    // File extension.
    $fileextension = 'tsv';
    // MIME: TSV type file.
    $filemime = 'text/tab-separated-values';

    // Personalize the filename by appending display name of the current user, but first
    // sanitize it by replacing all spaces into a dash character.
    $display_name = $this->user->getDisplayName() ?? 'anonymous-user';
    $user_display_name = str_replace(' ', '-', $display_name);

    // Filename: importer id - data collection template file - username . (TSV).        
    $filename = $importer_id . '-data-collection-template-file-' . $user_display_name . '.' . $fileextension;

    // Create the file.
    $file = File::create([
      'filename' => $filename,
      'filemime' => $filemime,
      'uri' => $dir_template_file . $filename
    ]);

    // Mark file for deletion during a Drupal maintenance.
    $file->set('status', 0);
    // Save.
    $file->save();

    // Write the contents: headers into the file created and serve the path back
    // to the calling Importer as value to the href attribute of link to download a template file.
    
    // File uri of the created file.
    $fileuri = $file->getFileUri();

    // Convert the headers array into a tsv string value and post into the first line of the file.
    $fileheaders = implode("\t", $column_headers) . "\n# DELETE THIS LINE --- START DATA HERE AND USE TAB KEY #";
    file_put_contents($fileuri, $fileheaders);
    
    return $file->createFileUrl();
  }
}