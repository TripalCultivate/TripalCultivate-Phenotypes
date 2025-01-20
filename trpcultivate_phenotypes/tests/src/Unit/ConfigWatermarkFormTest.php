<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Tests\UnitTestCase;
use Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesWatermarkSettingsForm;

/**
 * Class definition ConfigWatermarkFormTest.
 *
 * @coversDefaultClass Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesWatermarkSettingsForm
 * @group trpcultivate_phenotypes
 */
class ConfigWatermarkFormTest extends UnitTestCase {
  /**
   * Class instance of watermark controller settings form.
   *
   * @var \Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesWatermarkSettingsForm
   */
  protected $watermark_form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock file url generator.
    $mock_file_url_generator = $this->createMock(FileUrlGenerator::class);
    // Mock file entity manager.
    $mock_file_entity_manager = $this->createMock(EntityTypeManagerInterface::class);

    $this->watermark_form = new TripalCultivatePhenotypesWatermarkSettingsForm($mock_file_url_generator, $mock_file_entity_manager);
  }

  /**
   * Test watermark form id matches expected value.
   */
  public function testFormId() {
    $form_id = $this->watermark_form->getFormId();

    // Form id set matches.
    $this->assertEquals(
      'trpcultivate_phenotypes_watermark_settings_form',
      $form_id,
      'The form id returned by getFormId() does not match expected id value.'
    );
  }

}
