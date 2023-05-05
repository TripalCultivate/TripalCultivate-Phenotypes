<?php

/**
 * @file
 * Unit test of Watermark configuration page.
 */

namespace Drupal\Tests\trpcultivate_phenotypes\Unit;

use Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesWatermarkSettingsForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
  *  Class definition ConfigWatermarkFormTest.
  *
  * @coversDefaultClass Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesWatermarkSettingsForm
  * @group trpcultivate_phenotypes
  */
class ConfigWatermarkFormTest extends UnitTestCase {
  /**
   * Initialization of container, configurations, service 
   * and service class required by the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Create container.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    // Mock config since the Watermark form extends configFormBase and expects
    // a configuration settings. Called in validateForm().
    $watermark_config_mock = $this->prophesize(Config::class);
    $watermark_config_mock->get('trpcultivate.phenotypes.watermark')->willReturn([
      'charts' => false,
      'image' => null,
      'file_ext' => ['png', 'gif']
    ]);
   
    // When watermark form rebuilds calling the module settings, return
    // only the watertmark configuration settings above exclude other config.
    $all_config_mock = $this->prophesize(ConfigFactoryInterface::class);
    $all_config_mock->getEditable('trpcultivate_phenotypes.settings')
      ->willReturn($watermark_config_mock);

    // Isolated configuration for watermark configuration.
    $watermark_config = $all_config_mock->reveal();

    // Translation requirement of the container
    $translation_mock = $this->prophesize(TranslationInterface::class);
    $translation = $translation_mock->reveal();

    // Class WatermarkForm class instance.
    $watermark_form = new TripalCultivatePhenotypesWatermarkSettingsForm($watermark_config);
    $watermark_form->setStringTranslation($translation);

    $container->set('watermark.config', $watermark_form);
  }

  /**
   * Test validate functionality of WatermarkForm class.
   */  
  public function testValidateForm() {
    $watermark = \Drupal::service('watermark.config');

    // Test if it is the watermark config form using the form id.
    $this->assertEquals('trpcultivate_phenotypes_watermark_settings_form', $watermark->getFormId());
    
    // Method formValidate requires 2 parameters $form and $form_state.
    $form_state = new FormState();
    $form = [];

    // Validate that when choosing to watermark, an image file is required.
    $form_state->setValue('charts', 1);
    $form_state->setValue('file', []);
    $watermark->validateForm($form, $form_state);
    $this->assertTrue($form_state->hasAnyErrors());

    $form_state->clearErrors();

    // Validate that when choosing not to watermark, an image file is not required.
    $form_state->setValue('charts', 0);
    $form_state->setValue('file', []);
    $watermark->validateForm($form, $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
  } 
}