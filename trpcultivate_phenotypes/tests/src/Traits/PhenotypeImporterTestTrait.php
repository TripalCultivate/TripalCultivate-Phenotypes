<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Traits;

use Drupal\file\Entity\File;

trait PhenotypeImporterTestTrait {

  /**
   * Creates a Drupal Managed file based on the details provided.
   *
   * @param array $details
   *   An array containing details about the file to create.
   *   Supported keys:
   *     - extension: the file extension to use (default txt)
   *     - mime: the file type (e.g. text/plain, text/tab-separated-values)
   *     - filename: the name including extension to create attached to the
   *         managed file.
   *     - is_temporary: either TRUE or FALSE to indicate whether to put the
   *         file in the temporary or public files directory.
   *     - content[string]: the content to copy into the file as a string
   *     - content[file]: an existing file in the fixtures directory to copy
   *         the contents from
   *     - permissions: permissions to apply to the file using chmod.
   *         Either 'none' for unreadable or the octet (see chmod)
   *         0600: read + write for owner, nothing for everyone else
   *         0644: read + write for owner, read only for everyone else
   *         0777: read + write + execute for everyone
   * @return File
   *   The Drupal managed file object created.
   */
  protected function createTestFile($details) {

    // Set Defaults.
    $details['extension'] = @$details['extension'] ?: 'txt';
    $details['mime'] = @$details['mime'] ?: 'text/tab-separated-values';
    $details['filename'] = @$details['filename'] ?: 'testFile.' . uniqid() . '.' . $details['extension'];
    $details['is_temporary'] = @$details['is_temporary'] ?: FALSE;
    $details['content'] = @$details['content'] ?: ['string' => uniqid()];

    $directory = ($details['is_temporary']) ? 'temporary://' : 'public://';

    $file = File::create([
      'filename' => $details['filename'],
      'filemime' => $details['mime'],
      'uri' => $directory . $details['filename'],
      'status' => 0,
    ]);

    // Set the size of the file.
    // This is usually used if the file is empty in which case this is 0
    if (isset($details['filesize'])) {
      $file->setSize($details['filesize']);
    }

    // Save the file to Drupal.
    $file->save();
    $id = $file->id();

    // Write something on file with content key set to a string.
    if (!empty($details['content']['string'])) {
      $fileuri = $file->getFileUri();
      file_put_contents($fileuri, $details['content']['string']);
    }

    // If an existing file was specified then we can add that in here.
    if (!empty($details['content']['file'])) {
      $fileuri = $file->getFileUri();

      $path_to_fixtures = __DIR__ . '/../Fixtures/';
      $full_path = $path_to_fixtures . $details['content']['file'];
      $this->assertFileIsReadable($full_path,
        "Unable to setup FILE ". $id . " because cannot access Fixture file at $full_path.");

      copy($full_path, $fileuri);
    }

    // Set file permissions if needed.
    if (!empty($details['permissions'])) {
      $fileuri = $file->getFileUri();
      if ($details['permissions'] == 'none') {
        chmod($fileuri, octdec(0000));
      }
      elseif (is_numeric($details['permissions'])) {
        $decoded = decoct(octdec($details['permissions']));
        if ($details['permissions'] == $decoded) {
          chmod($fileuri, $details['permissions']);
        }
      }
    }

    return $file;
  }
}
