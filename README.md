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
PHPUnit for testing and CodeClimate to ensure good test coverage and maintainability.
There are more details on [our CodeClimate project page] describing our specific
maintainability issues and test coverage.

![MaintainabilityBadge]
![TestCoverageBadge]

The following compatibility is proven via automated testing workflows.

| Drupal | 9.3.x | 9.4.x | 9.5.x | 10.0.x |
|--------|-------|-------|-------|--------|
| **PHP 8.0** | ![Grid1A-Badge] | ![Grid1B-Badge] | ![Grid1C-Badge] |  |
| **PHP 8.1** | ![Grid2A-Badge] | ![Grid2B-Badge] | ![Grid2C-Badge] |  |

[our CodeClimate project page]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes
[MaintainabilityBadge]: https://api.codeclimate.com/v1/badges/03fa542e0d95dedb97e8/maintainability
[TestCoverageBadge]: https://api.codeclimate.com/v1/badges/03fa542e0d95dedb97e8/test_coverage

[Grid1A-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid1A.yml/badge.svg
[Grid1B-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid1B.yml/badge.svg
[Grid1C-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid1C.yml/badge.svg

[Grid2A-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid2A.yml/badge.svg
[Grid2B-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid2B.yml/badge.svg
[Grid2C-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid2C.yml/badge.svg
[Grid2D-Badge]: https://github.com/TripalCultivate/TripalCultivate-Phenotypes/actions/workflows/MAIN-phpunit-Grid2D.yml/badge.svg
