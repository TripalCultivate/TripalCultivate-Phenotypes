<?php

namespace Drupal\trpcultivate_phenoshare\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Phenotypes Share module hooks.
 */
class TripalCultivatePhenoShareHooks {

  use StringTranslationTrait;

  /**
   * Implementes hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {

    switch ($route_name) {
      // Provides the module overview in the help tab.
      case 'help.page.trpcultivate_phenoshare':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<ul><li>' . $this->t('Provides trait pages, downloads and visualization tools to facillitate sharing published phenotypic data with the public.') . '</li></ul>';

        return $output;

      default:
    }
  }

}
