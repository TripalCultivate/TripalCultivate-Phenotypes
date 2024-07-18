<?php

/**
 * @file
 * Kernel tests for validator the header row of the data file.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;

 /**
  * Tests Tripal Cultivate Phenotypes Header Row Validator Plugins.
  *
  * @group trpcultivate_phenotypes
  * @group validators
  */
class HeaderRowTest extends ChadoTestKernelBase {
  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test header row.
   */
  public function testHeaderRow() {
    // Create a plugin instance for this validator
    $validator_id = 'trpcultivate_phenotypes_validator_header_row';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Tests:
    // Each test will test that headerRow validator generated the correct case, valid status and failed item.
    // Failed item is the failed missing expected header value. Failed information is contained in the case
    // whether the header failed because it is empty or some missing headers found.

    // Header row is empty string, 0, FALSE and an empty array.
    $invalid_header = ['', 0, FALSE, []];

    foreach($invalid_header as $header_row) {
      $validation_status = $instance->validateRow($header_row, []);
    
      $this->assertEquals('Header row is an empty value', $validation_status['case'],
        'Header row validator case title does not match expected title for invalid header row.');
      $this->assertFalse($validation_status['valid'], 'A failed header row must return a FALSE valid status.');
      // NOTE: this is checking the custom text when checking for empty header row.
      $this->assertStringContainsString('header row is empty', $validation_status['failedItems'], 'header row is empty text is expected in failed items.');
    }
    
    // @TODO: reference the column headers defined by the importer.
    // Header 1 and 4 is missing here.
    $headers = ['Header 1', 'Header 2', 'Header 3', 'Header 4', 'Header 5'];
    // 1 less so as not to load an empty array.
    $count = count($headers) - 1;

    for ($i = 0; $i < $count; $i++) {
      $missing_header = array_pop($headers);
      $validation_status = $instance->validateRow($headers, []);

      $this->assertEquals('Missing expected column headers', $validation_status['case'],
        'Header row validator case title does not match expected title for missing header.');
      $this->assertFalse($validation_status['valid'], 'A failed header row must return a FALSE valid status.');
      $this->assertStringContainsString($missing_header, $validation_status['failedItems'], 'Missing header is expected in failed items.');
    }

    // A valid header row and no missing header.
    $header_row = ['Header 1', 'Header 2', 'Header 3', 'Header 4', 'Header 5'];
    $validation_status = $instance->validateRow($header_row, []);

    $this->assertEquals('Header row exists and match expected column headers', $validation_status['case'],
      'Header row validator case title does not match expected title for a valid header row.');
    $this->assertTrue($validation_status['valid'], 'A valid header row must return a TRUE valid status.');
    $this->assertEmpty($validation_status['failedItems'], 'A valid header row does not return a failed item value.');
  }
}