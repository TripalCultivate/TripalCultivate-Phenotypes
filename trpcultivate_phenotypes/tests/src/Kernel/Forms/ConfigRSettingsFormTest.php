<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Forms;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate_phenotypes\Form\TripalCultivatePhenotypesRSettingsForm;
use Drupal\Core\Form\FormState;

/**
 * Tests associated with TripalCultivatePhenotypesRSettingsForm class.
 */
class ConfigRSettingsFormTest extends ChadoTestKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'tripal',
    'tripal_chado',
    'trpcultivate_phenotypes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);
  }

  /**
   * Tests that the form submits with the expected values.
   */
  public function testSubmitForm() {
    $container = \Drupal::getContainer();
    // Create a form instance.
    $rsettingsform = TripalCultivatePhenotypesRSettingsForm::create($container);

    $form = [];
    $form_state = new FormState();

    // Set values for form state.
    $form_state->setValue('words', 'num,log');
    $form_state->setValue('chars', '#,*');
    $form_state->setValue('replace', 'num = number');

    $rsettingsform->validateForm($form, $form_state);
    $this->assertFalse(
      $form_state->hasAnyErrors(),
      'The form state should be valid but there are form errors for some reason.',
    );

    $rsettingsform->submitForm($form, $form_state);

    $config = \Drupal::configFactory()->getEditable('trpcultivate_phenotypes.settings');

    // Assert that the configuration was saved correctly.
    $this->assertNotNull(
      $config->get('trpcultivate.phenotypes.r_config.words'),
      'Words configuration was not saved correctly.'
    );
    $this->assertNotNull(
      $config->get('trpcultivate.phenotypes.r_config.chars'),
      'Char configuration was not saved correctly.'
    );
    $this->assertNotNull(
      $config->get('trpcultivate.phenotypes.r_config.replace'),
      'Replace configuration was not saved correctly.'
    );
  }

}
