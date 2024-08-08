<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate_phenotypes\Traits\PhenotypeImporterTestTrait;
use Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators\ValidatorDelimiter;

 /**
  * Tests the delimiter validator trait.
  *
  * @group trpcultivate_phenotypes
  * @group validator_traits
  */
class ValidatorTraitDelimiterTest extends ChadoTestKernelBase {

  use PhenotypeImporterTestTrait;

  /**
   * Modules to enable.
   */
  protected static $modules = [
    'file',
    'user',
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes'
  ];

  /**
   * The validator instance to use for testing.
   *
   * @var ValidatorDelimiter
   */
  protected ValidatorDelimiter $instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate_phenotypes']);

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_delimiter';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Delimiter Trait',
      'input_types' => ['header-row', 'data-row', 'raw-row'],
    ];

    $instance = new ValidatorDelimiter(
      $configuration,
      $validator_id,
      $plugin_definition
    );

    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Delimiter trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the DataFileDelimiter::setDelimiter() setter and
   * DataFileDelimiter::getDelimiter() getter.
   *
   * @return void
   */
  public function testDelimiterSetterGetter() {
    // Test getter will trigger an error when attempting to get a delimiter
    // prior to a call to delimiter setter method.
    
    // Exception message when failed to set a delimiter.
    $expected_message = 'Cannot retrieve delimiter set by the importer';

    try {
      $this->instance->getDelimiter();
    } 
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Delimiter getter method should throw an exception for unset delimiter.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'Expected exception message does not match the message when trying to get unset delimiter.'
    );
    

    // Test invalid delimiter will trigger an exception.

    // Not valid delimiters:
    $invalid_delimiters = ['', false, '0', 0];
    // Exception message when invalid delimiter was provided to the setter.
    $expected_message = 'Invalid delimiter: Cannot use %s as data file delimiter.';

    foreach($invalid_delimiters as $invalid_delimiter) {
      $exception_caught = FALSE;
      $exception_message = '';
      
      try {
        $this->instance->setDelimiter($invalid_delimiter);
      } 
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }
      
      $this->assertTrue($exception_caught, 'Delimiter setter method should throw an exception for invalid delimiter value.');
      $this->assertStringContainsString(
        sprintf($expected_message, $invalid_delimiter),
        $exception_message,
        'Expected exception message does not match the message when invalid value was passed to the delimiter setter method'
      );
    }

    
    // Test that for every valid delimiter set the getter will return
    // back the same value using the getter method.

    // Valid delimiters:
    $valid_delimiters = ["\t", ',', '|', '#', '<my-delimiter>', ':', '-'];
    foreach($valid_delimiters as $valid_delimiter) {
      $this->instance->setDelimiter($valid_delimiter);

      $delimiter = $this->instance->getDelimiter();
      $this->assertEquals(
        $delimiter, 
        $valid_delimiter, 
        'The set delimiter does not match the delimiter returned by the delimiter getter method'
      );
    }
  }
}