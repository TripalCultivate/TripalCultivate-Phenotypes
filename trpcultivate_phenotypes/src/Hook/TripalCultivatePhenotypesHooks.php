<?php

namespace Drupal\trpcultivate_phenotypes\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Phenotypes module hooks.
 */
class TripalCultivatePhenotypesHooks {

  use StringTranslationTrait;

  /**
   * Implementes hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {

    switch ($route_name) {
      // Provides the module overview in the help tab.
      case 'help.page.trpcultivate_phenotypes':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';

        $output .= '<ul><li>' . $this->t('Creates genus-specific Tripal Content Types for Trait pages to provide a landing page for all information about a specific trait. These are specific to the genus to ensure that all data summarized is relevant and to respect that traits to vary between genus in their expression and specific definition.') . '</li>'
          . '<li>' . $this->t('Supports using genus-specific ontologies to ensure you capture each trait fully and mapping of these genus-specific terms to domain and system specific ontologies to enable comparison and data sharing.') . '</li>'
          . '<li>' . $this->t('Focuses on the Trait - Method - Unit formula for describing phenotypic data.') . '</li>'
            . '<ul>
                 <li>' . $this->t('This supports collecting all data for a specific trait (e.g. Plant Height) into a single page while still fully describing methodology and units for accurate analysis.') . '</li>'
              . '<li>' . $this->t('For the Plant Height trait, you would have data available for multiple experiments, measurement methodology (e.g highest canopy point, average canopy height in a plot, drone captured height based on NDVI) and units on the same page but they would not be combined across experiment, method or units.') . '</li>
            </ul>'
          . '<li>' . $this->t('A holding space for raw phenotypic data / measurements right after collection which is private by default and sharable with individual accounts. These data are kept outside the main schema for your biological data since they are raw, unpublished results. There is an easy means to backup data, validate and import by season.')
          . '</li>
          </ul>';

        return $output;

      default:
    }
  }

}
