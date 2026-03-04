<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Phenotypes module theme hooks.
 */
class TripalCultivatePhenotypesThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @see /templates
   */
  #[Hook('theme')]
  public function theme() {

    $theme = [];

    // Theme instructions found in the header section
    // of ontology configuration page.
    $theme['header_instructions'] = [
      'variables' => [
        'data' => [
          'section' => '',
          'link_01' => '',
          'link_02' => '',
        ],
      ],
      'template' => 'trpcultivate-phenotypes-template-header-instructions',
    ];

    return $theme;
  }

}
