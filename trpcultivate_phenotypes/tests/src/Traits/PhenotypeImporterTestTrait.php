<?php
namespace Drupal\Tests\trpcultivate_phenotypes\Traits;

use Drupal\file\Entity\File;
use Drupal\tripal_chado\Database\ChadoConnection;

trait PhenotypeImporterTestTrait {

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Sets the ontology configuration for a specific genus.
   *
   * Note: It will create the cv + dbs if you do not provide them.
   *
   * @param string $genus
   *   The genus you would like to set the configuration for. It is suggested
   *   you create the organism first.
   * @param array $details
   *   An array of details regarding the cv and dbs to configure for this genus.
   *   The following keys are supported:
   *     - trait:
   *        - cv_id:
   *        - name:
   *     - unit:
   *        - cv_id:
   *        - name:
   *     - method:
   *        - cv_id:
   *        - name:
   *     - database
   *        - db_id:
   *        - name:
   *     - crop_ontology
   *        - cv_id:
   *        - name:
   *   If an id is supplied then no records will be added into the test chado.
   *   If an id is supplied but no name then the name will be looked up.
   *   If a name is provided but no id then the record will be created with that name.
   *   If nothing is supplied then a random name will be created.
   *
   * @return
   *   An array similar to $details but will all values filled out.
   */
  public function setOntologyConfig(string $genus, array $details = []) {
    $genus_ontology_config = [];

    $reference = [
      'trait' => 'cv_id',
      'unit' => 'cv_id',
      'method' => 'cv_id',
      'crop_ontology' => 'cv_id',
      'database' => 'db_id'
    ];
    foreach ($reference as $key => $id_column) {
      $table = ($id_column == 'cv_id') ? 'cv' : 'db';

      // Ensure all keys are set.
      $details[$key] = @$details[$key] ?: ['cv_id' => 0, 'name' => ''];
      $details[$key][$id_column] = @$details[$key][$id_column] ?: 0;
      $details[$key]['name'] = @$details[$key]['name'] ?: '';

      // Now we will want to set the name.
      // -- no name but we have the id.
      if (empty($details[$key]['name']) AND !empty($details[$key][$id_column])) {
        $table = ($id_column == 'cv_id') ? 'cv' : 'db';
        $id = $details[$key][$id_column];
        $name = $this->chado_connection->select("1:$table", 'tbl')
          ->fields('tbl', ['name'])
          ->condition($id_column, $id, '=')
          ->execute()
          ->fetchField();
        $this->assertNotEmpty($name,
          "We were not able to select the $table.name where $id_column=$id");
        $details[$key]['name'] = $name;
      }
      // -- no id but we have the name.
      elseif (!empty($details[$key]['name']) AND empty($details[$key][$id_column])) {
        $name = $details[$key]['name'];
        $id = $this->chado_connection->select("1:$table", 'tbl')
          ->fields('tbl', [$id_column])
          ->condition('name', $name, '=')
          ->execute()
          ->fetchField();
        $this->assertNotEmpty($name,
          "We were not able to select the $table.$id_column where name=$name");
        $details[$key][$id_column] = $id;
      }

      // -- we still don't have the id so create one.
      if (empty($details[$key][$id_column])) {
        // set the name if it's not already.
        $details[$key]['name'] = $details[$key]['name'] ?: $genus . ' ' . $key . uniqid();
        $name = $details[$key]['name'];
        $id = $this->chado_connection->insert("1:$table")
          ->fields([
            'name' => $name,
          ])
          ->execute();
        $this->assertIsNumeric($id, "We were not able to create a $table record where the name is $name for $key.");
        $details[$key][$id_column] = $id;
      }

      // Finally, add it to our array to be saved to config.
      $genus_ontology_config[$key] = $id;
    }

    $config_name = str_replace(' ', '_', strtolower($genus));
    $config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');
    $config->set('trpcultivate.phenotypes.ontology.cvdbon.' . $config_name, $genus_ontology_config);

    return $details;
  }

  /**
   * Configures the terms used by the phenotypes module.
   */
  public function setTermConfig(array $terms = []) {
    $config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    $reference = [
      'data_collector',
      'entry',
      'genus',
      'location',
      'name',
      'experiment_container',
      'unit_to_method_relationship_type',
      'method_to_trait_relationship_type',
      'trait_to_synonym_relationship_type',
      'unit_type',
      'experiment_replicate',
      'experiment_year',
    ];
    foreach ($reference as $key) {
      // Ensure the key exists in terms.
      if (!array_key_exists($key, $terms)) {
        $terms[$key] = 0;
      }
      // If the term value is not set, then choose a random integer between 10 - 300.
      // We know there are at least 300 terms in the cvterm table so this is pretty safe.
      if(empty($terms[$key])) {
        $terms[$key] = random_int(10,300);
      }

      // Now set the configuration
      $config->set("trpcultivate.phenotypes.ontology.terms.$key", $terms[$key]);
    }

    return $terms;
  }

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
   *     - filesize: the size of the file being created in bytes
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
    $details['filename'] = @$details['filename'] ?: 'testFile.' . uniqid() . '.' . $details['extension'];
    $details['mime'] = @$details['mime'] ?: 'text/tab-separated-values';
    $details['is_temporary'] = @$details['is_temporary'] ?: FALSE;
    $details['content'] = @$details['content'] ?: ['string' => uniqid()];

    // Set directory.
    $directory = ($details['is_temporary']) ? 'temporary://' : 'public://';
    $file_uri = $directory . $details['filename'];

    // Create file object.
    $file = File::create([
      'filename' => $details['filename'],
      'filemime' => $details['mime'],
      'uri' => $file_uri,
      'status' => 0,
    ]);

    // Reference file attributes:
    $file_id = $file->id();
    $file_uri = $file->getFileUri();

    // If a test file fixture was provided, create a copy and set this file copy as
    // the file uri value in the file object for this test file.
    if (array_key_exists('file', $details['content']) && !empty($details['content']['file'])) {
      $path_to_file_fixture = __DIR__ . '/../Fixtures/' . $details['content']['file'];

      $this->assertFileIsReadable(
        $path_to_file_fixture,
        'Unable to setup FILE ' . $file_id . ' because cannot access Fixture file at ' .  $path_to_file_fixture
      );

      copy($path_to_file_fixture, $file_uri);
    }

    // Write something on file with content key set to a string.
    if (!empty($details['content']['string'])) {
      file_put_contents($file_uri, $details['content']['string']);
    }

    // Set other file attributes:

    // Set the size of the file.
    // This is usually used if the file is empty in which case this is 0.
    if (isset($details['filesize'])) {
      // File size was provided.
      $file->setSize($details['filesize']);
    }
    else {
      // File size is to be determined.
      // Get the file size.
      $file_size = @filesize($file_uri);

      // Assert that a file size was established.
      $this->assertNotFalse($file_size, 'Unable to determine size of test file: ' . $file_uri);

      // Set the file size.
      $file->setSize($file_size);
    }

    // Save all set attributes.
    $file->save();

    // Set file permissions if needed.
    if (!empty($details['permissions'])) {
      if ($details['permissions'] == 'none') {
        chmod($file_uri, octdec(0000));
      }
      elseif (is_numeric($details['permissions'])) {
        $decoded = decoct(octdec($details['permissions']));
        if ($details['permissions'] == $decoded) {
          chmod($file_uri, $details['permissions']);
        }
      }
    }

    return $file;
  }
}
