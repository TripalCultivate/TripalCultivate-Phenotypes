ARG drupalversion='10.2.x-dev'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php8.3-pgsql13-noChado

ARG chadoschema='testchado'
COPY . /var/www/drupal/web/modules/contrib/TripalCultivate-Phenotypes

WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate-Phenotypes

RUN service postgresql restart \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=general_chado \
  && drush tripal:trp-import-types --username=drupaladmin --collection_id=germplasm_chado \
  && drush en trpcultivate_phenotypes trpcultivate_phenocollect trpcultivate_phenoshare --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr
