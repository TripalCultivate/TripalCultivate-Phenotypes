# Tripal Cultivate: Phenotypes

**Developed by the University of Saskatchewan, Pulse Crop Bioinformatics team.**

**NOTE: This package will replace the following Tripal v3 modules: [Raw Phenotypes](https://github.com/UofS-Pulse-Binfo/rawphenotypes), [AnalyzedPhenotypes](https://github.com/uofs-pulse-binfo/analyzedphenotypes/).**

<!-- Summarize the main features of this package in point form below. -->

- Creates genus-specific Tripal Content Types for Trait pages to provide a landing page for all information about a specific trait. These are specific to the genus to ensure that all data summarized is relevant and to respect that traits to vary between genus in their expression and specific definition.

- Supports using genus-specific ontologies to ensure you capture each trait fully and mapping of these genus-specific terms to domain and system specific ontologies to enable comparison and data sharing.

- Focuses on the Trait - Method - Unit formula for describing phenotypic data.

    - This supports collecting all data for a specific trait (e.g. Plant Height) into a single page while still fully describing methodology and units for accurate analysis.

    - For the Plant Height trait, you would have data available for multiple experiments, measurement methodology (e.g highest canopy point, average canopy height in a plot, drone captured height based on NDVI) and units on the same page but they would not be combined across experiment, method or units.

- A holding space for raw phenotypic data / measurements right after collection which is private by default and sharable with individual accounts. These data are kept outside the main schema for your biological data since they are raw, unpublished results. There is an easy means to backup data, validate and import by season.

## Citation

If you use this module in your Tripal site, please use this citation to reference our work any place where you described your resulting Tripal site. For example, if you publish your site in a journal then this citation should be in the reference section and anywhere functionality provided by this module is discussed in the above text should reference it.

> Lacey-Anne Sanderson and Reynold Tan (2023). TripalCultivate Phenotypes: Large-scale trait and phenotypic data integration for Tripal. Development Version. University of Saskatchewan, Pulse Crop Research Group, Saskatoon, SK, Canada.

## Install

Using composer, add this package to your Drupal site by using the following command in the root of your Drupal site:

```
composer require tripalcultivate/phenotypes
```

This will download the most recent release in the modules directory. You can see more information in [the Drupal Docs](https://www.drupal.org/docs/develop/using-composer/manage-dependencies).

Then you can install it using Drush or the Extensions page on your Drupal site.

```
drush en trpcultivate_phenotypes
```

## Technology Stack

*See specific version compatibility in the automated testing section below.*

- Drupal
- Tripal 4.x
- PostgreSQL
- PHP
- Apache2

### Automated Testing

This package is dedicated to a high standard of automated testing. We use
PHPUnit for testing and QLTY Cloud to ensure good test coverage and maintainability.
There are more details on [our QLTY Cloud project page] describing our specific
maintainability issues and test coverage.

[![Maintainability](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate-Phenotypes/maintainability.svg)](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate-Phenotypes)
[![Code Coverage](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate-Phenotypes/coverage.svg)](https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate-Phenotypes)

The following compatibility is proven via automated testing workflows.

| PHP\Drupal | 10.5.x          | 10.6.x          | 11.2.x          | 11.3.x          |
|------------|---------------------|---------------------|---------------------|---------------------|
| **PHP8.2** | ![Grid82-105-Badge] | ![Grid82-106-Badge] |                     |                     |
| **PHP8.3** | ![Grid83-105-Badge] | ![Grid83-106-Badge] | ![Grid83-112-Badge] | ![Grid83-113-Badge] |
| **PHP8.4** | ![Grid84-105-Badge] | ![Grid84-106-Badge] | ![Grid84-112-Badge] | ![Grid84-113-Badge] |
| **PHP8.5** |                     |                     |                     | ![Grid85-113-Badge] |

[our QLTY Cloud project page]: https://qlty.sh/gh/TripalCultivate/projects/TripalCultivate-Phenotypes

[Grid82-105-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.2_D10_5x.yml/badge.svg
[Grid82-106-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.2_D10_6x.yml/badge.svg
[Grid83-105-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.3_D10_5x.yml/badge.svg
[Grid83-106-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.3_D10_6x.yml/badge.svg
[Grid83-112-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.3_D11_2x.yml/badge.svg
[Grid83-113-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.3_D11_3x.yml/badge.svg
[Grid84-105-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.4_D10_5x.yml/badge.svg
[Grid84-106-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.4_D10_6x.yml/badge.svg
[Grid84-112-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.4_D11_2x.yml/badge.svg
[Grid84-113-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.4_D11_3x.yml/badge.svg
[Grid85-113-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-php8.5_D11_3x.yml/badge.svg
