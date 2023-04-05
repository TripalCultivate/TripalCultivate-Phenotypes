<?php

/**
 * @file
 * Controller to display a dashboard page that houses links and descriptions
 * to configuration pages.
 */

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Class definition TripalCultivatePhenotypesSettingsController.
 */
class TripalCultivatePhenotypesSettingsController extends ControllerBase {
  
  /**
   * Returns a markup of details (fieldset) where the 
   * the title of the element is the configuration section and 
   * expanding each will reveal a short description.
   */
  public function loadPage() {
    $url = Url::fromRoute('trpcultivate_phenotypes.settings');
    $link = Link::fromTextAndUrl($this->t('Click here'), $url)
      ->toString();

    $element['rrules'] = [
      '#type' => 'details',
      '#title' => $this->t('R Transformation Rules'),
      '#description' => $this->t('Set transformation rules when converting 
        trait or value to R compatible version. @link', ['@link' => $link]),
      '#open' => TRUE
    ];

    $element['watermarking'] = [
      '#type' => 'details',
      '#title' => $this->t('Watermark Chart'),
      '#description' => $this->t('Setup watermark element to apply to 
        charts and illustrations. @link', ['@link' => $link]),
      '#open' => FALSE
    ];

    $element['ontologies'] = [
      '#type' => 'details',
      '#title' => $this->t('Ontology Terms'),
      '#description' => $this->t('Define ontology terms for use in 
        inserting data. @link', ['@link' => $link]),
      '#open' => FALSE
    ];


    $page = \Drupal::service('renderer')
      ->render($element);

    return [
      '#markup' => $page
    ];
  }
}