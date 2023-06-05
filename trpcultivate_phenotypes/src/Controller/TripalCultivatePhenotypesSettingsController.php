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
    // Quick enabled or disabled status of sub-phenotypes modules
    // and report to dashboard.
    $moduleHandler = \Drupal::service('module_handler');
    $status_message = 'Tripal Cultivate Phenotypes Modules Status: ';

    foreach(['trpcultivate_phenoshare', 'trpcultivate_phenocollect'] as $m) {
      $is_enabled = ($moduleHandler->moduleExists($m)) ? 'is enabled' : 'is disabled';
      $status_message .= '*' . $m . ' ' . $is_enabled . ' ';
    }

    $url = Url::fromUri('internal:/admin/modules');
    $link = Link::fromTextAndUrl('Manage Modules', $url)
      ->toString();

    $this->messenger()->addStatus($this->t($status_message . '- @manage', ['@manage' => $link]));


    // Describe R Rules configuration:
    $url = Url::fromRoute('trpcultivate_phenotypes.settings_r');
    $link = Link::fromTextAndUrl($this->t('Click here'), $url)
      ->toString();

    $describe = 'Tripal Cultivate Phenotypes supports R language for statistical computing by providing
      syntactically valid version of trait or values. Use R Transformation Rules configuration page to define
      standard transformation rules to apply to trait or string when converting to R version.';

    $element['rrules'] = [
      '#type' => 'details',
      '#title' => $this->t('R TRANSFORMATION RULES'),
      '#description' => $this->t('@describe<br>@link', ['@describe' => $describe, '@link' => $link]),
      '#open' => TRUE,
    ];

    // Describe Watermarking configuration:
    $url = Url::fromRoute('trpcultivate_phenotypes.settings_watermark');
    $link = Link::fromTextAndUrl($this->t('Click here'), $url)
      ->toString();

    $describe = 'Every diagram generated by Tripal Cultivate Phenotypes can be superimposed with personalized
      logo or text logo to ensure proper credit and indicate authenticity. Use Watermark Chart configuration
      to set watermarking scheme of modules.';

    $element['watermarking'] = [
      '#type' => 'details',
      '#title' => $this->t('WATERMARK CHART'),
      '#description' => $this->t('@describe<br>@link', ['@describe' => $describe, '@link' => $link]),
      '#open' => TRUE,
    ];

    // Describe Ontologies configuration:
    $url = Url::fromRoute('trpcultivate_phenotypes.settings_ontology');
    $link = Link::fromTextAndUrl($this->t('Click here'), $url)
      ->toString();
      
    $describe = 'Tripal Cultivate Phenotypes require that phenotypic traits be housed in a Controlled Vocabulary (CV)
      and pre-define terms used throughout the various processes. Use Ontology Terms configuration to setup terms that
      best support your data.';

    $element['ontologies'] = [
      '#type' => 'details',
      '#title' => $this->t('ONTOLOGY TERMS'),
      '#description' => $this->t('@describe<br>@link', ['@describe' => $describe, '@link' => $link]),
      '#open' => TRUE,
    ];

    return $element;
  }
}
