<?php

/**
 * @file
 * Construct form to manage and configure watermark schemes.
 */

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\file\Entity\File;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class definition TripalCultivatePhenotypesWatermarkSettingsForm.
 */
class TripalCultivatePhenotypesWatermarkSettingsForm extends ConfigFormBase {
  const SETTINGS = 'trpcultivate_phenotypes.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'trpcultivate_phenotypes_watermark_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS)->get('trpcultivate.phenotypes.watermark');

    // This is a warning about the watermark being able to bypass with
    // advanced knowledged of HTML/CSS.
    if (!$form_state->getUserInput()) {
      $warning = $this->t('Users with advanced HTML/CSS knowledge will be able to remove the watermark image.
        Please take additional steps to protect your data if you are concerned.');
      $this->messenger()->addWarning($warning, $repeat = FALSE);
    }

    $form['description'] = [
      '#markup' => $this->t('Every diagram generated by Tripal Cultivate Phenotypes can be superimposed
        with personalized logo or text logo to ensure proper credit/attribution and indicate authenticity.
        Use Watermark Chart configuration to set watermarking scheme of modules.')
    ];

    $watermark_options = [
      '1' => $this->t('Watermark all charts'),
      '0' => $this->t('Do not watermark any charts')
    ];

    $form['charts'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose which chart to watermark'),
      '#description' => $this->t('A watermark image file is required when selecting Watermark all charts option'),
      '#options' => $watermark_options,
      '#default_value' => (int) $config['charts'],
      '#required' => TRUE,
    ];

    // Watermark image. Has state so that when user picks not to watermark
    // image, there will be no need for the watermark image elements.
    $form['field_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="charts"]' => array('value' => '1'),
        ]
      ]
    ];

    $form['field_wrapper']['preview'] = [
      '#markup' => '<h4>Watermark Image</h4>'
    ];

    $form['field_wrapper']['container'] = [
      '#type' => 'container',
      '#attributes' => ['style' => [
        'background-color: #EAEAEA; text-align: center; padding: 20px'
      ]],
    ];

    $image = $config['image'];
    $file_url = \Drupal::service('file_url_generator');

    $preview = (empty($image))
      ? $this->t('No watermark image')
      : '<img src="' . $file_url->generateAbsoluteString($image) . '" alt="watermark" title="watermark" />';

    $form['field_wrapper']['container']['preview'] = [
      '#markup' => $preview
    ];

    // This field is required when choosing to watermark image
    // thus validate will make sure that image file is entered
    // when watermarking all charts.
    $source_path = $this->config(static::SETTINGS)->get('trpcultivate.phenotypes.directory.watermark');
    $valid_ext = $config['file_ext'];

    $form['field_wrapper']['file'] = [
      '#type' => 'managed_file',
      '#description' => $this->t('Image file format with transparent background enabled [@ext]',
        ['@ext' => strtoupper(implode(', ', $valid_ext))]),
      '#upload_validators' => [
        'file_validate_extensions' => $valid_ext
      ],
      '#upload_location' => $source_path
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate when opting for watermarking charts to also
    // provde a watermark image file.
    if ($form_state->getValue('charts') == 1) {
      $image = $form_state->getValue('file');

      if (!$image) {
        $form_state->setErrorByName('image', $this->t('Please provide an image file.'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $config_watermark = 'trpcultivate.phenotypes.watermark.';

    $charts = $form_state->getValue('charts');
    $charts = ($charts == 1) ? TRUE : FALSE;
    $watermark = null;

    if ($charts) {
      $file = $form_state->getValue('file');

      if ($file) {
        $file_obj = File::load(reset($file));

        // Set it permanent used by this module.
        $file_obj->setPermanent();
        $file_obj->save();

        $watermark = $file_obj->getFileUri();
      }
    }

    // Save configurations.
    $config
      ->set($config_watermark . 'charts', $charts)
      ->set($config_watermark . 'image', $watermark)
      ->save();


    return parent::submitForm($form, $form_state);
  }
}
