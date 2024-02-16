ARG drupalversion='10.2.x-dev'
ARG phpversion='8.3'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql13-noChado

ARG chadoschema='testchado'
COPY . /var/www/drupal/web/modules/contrib/TripalCultivate-Phenotypes

WORKDIR /var/www/drupal/web/themes

## Download the Tripal Cultivate base theme
RUN git clone https://github.com/TripalCultivate/TripalCultivate-Theme.git trpcultivatetheme

WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate-Phenotypes

## Complete Tripal install:
##  - enable the theme we downloaded above + set it as default.
##  - install and prepare chado
##  - add content types: general + germplasm
##  - enable our modules
##  - run tripal jobs and clear cache
RUN service postgresql restart \
  && drush theme:enable trpcultivatetheme --yes \
  && drush config-set system.theme default trpcultivatetheme \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=general_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=germplasm_chado \
  && drush en trpcultivate_phenotypes trpcultivate_phenocollect trpcultivate_phenoshare --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr
