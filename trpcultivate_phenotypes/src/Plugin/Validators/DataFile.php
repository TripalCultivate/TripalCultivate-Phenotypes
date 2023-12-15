<?php

/**
 * @file
 * Contains validator plugin definition.
 */

namespace Drupal\trpcultivate_phenotypes\Plugin\Validators;

use Drupal\trpcultivate_phenotypes\TripalCultivatePhenotypesValidatorBase;
use Drupal\file\Entity\File;

/**
 * Validate Data File.
 * 
 * @TripalCultivatePhenotypesValidator(
 *   id = "trpcultivate_phenotypes_validator_datafile",
 *   validator_name = @Translation("Data File Validator"),
 *   validator_scope = "FILE",
 * )
 */
class DataFile extends TripalCultivatePhenotypesValidatorBase {
  /**
   * Validate items in the phenotypic data upload assets.
   *
   * @return array
   *   An associative array with the following keys.
   *   - title: string, section or title of the validation as it appears in the result window.
   *   - status: string, pass if it passed the validation check/test, fail string otherwise and todo string if validation was not applied.
   *   - details: details about the offending field/value.
   */
  public function validate() {
    // Validate ...
    $validator_status = [
      'title' => 'File is a Valid Tab-separated Values (TSV) or Plain Text-based (TXT) file.',
      'status' => 'pass',
      'details' => ''
    ];
    
    // Instructed to skip this validation. This will set this validator as upcoming or todo.
    // This happens when other prior validation failed and this validation could only proceed
    // when input values in the failed validator have been rectified.  
    if ($this->skip) {
      $validator_status['status'] = 'todo';
      return $validator_status;
    }

    // Data File:
    //   - Has Drupal File Id assigned/created.
    //   - A valid TSV or TXT file extension.
    //   - File Id created/assigned can be loaded.
    //   - Is not empty file.

    if ($this->file_id <= 0) {
      // No file has been uploaded into the data file field.
      $validator_status['status']  = 'fail';
      $validator_status['details'] = 'No file has been uploaded. Please upload a file and try again.';
    }
    else {
      // Has file, check to make sure the correct file type.
      $file = FILE::load($this->file_id);

      if (!$file) {
        // Could not load file.
        $validator_status['status']  = 'fail';
        $validator_status['details'] = 'File could not be loaded. Please upload a file and try again.';   
      }
      else {
        // Ensure correct file type is tsv or txt and no pretentious image/pdf file as tsv/txt.
        $file_type = $file->getMimeType();
        
        if (!in_array($file_type, ['text/tab-separated-values', 'text/plain'])) {
          $validator_status['status']  = 'fail';
          $validator_status['details'] = 'The file uploaded does not have the expected file format of TSV or TXT file. Please upload a file and try again.';       
        }
        else {
          // Is there something in the file? - not empty file check.
          $file_size = filesize($file->getFileUri());

          if ($file_size <= 0) {
            $validator_status['status']  = 'fail';
            $validator_status['details'] = 'The file uploaded is an empty file. Please upload a file and try again.';         
          }
          else {
            // Check if it can be opened and read the contents.
            $file_uri = $file->getFileUri();
            $handle = fopen($file_uri, 'rb');
            
            if (!$handle) {
              $validator_status['status']  = 'fail';
              $validator_status['details'] = 'The file uploaded could not be opened. Please upload a file and try again.';           
            }
            else {
              // Check if file content for PDF signature.
              $pdf = fread($handle, 4);

              if ($pdf == '%PDF') {
                $validator_status['status']  = 'fail';
                $validator_status['details'] = 'The file uploaded does not have the expected file format of TSV or TXT file. Please upload a file and try again.';           
              }
            }

            fclose($handle);
          }
        }
      }
    }

    return $validator_status;
  }
}