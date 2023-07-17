ARG drupalversion='10.0.x-dev'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php8.1-pgsql13-noChado

ARG chadoschema='testchado'
COPY . /var/www/drupal9/web/modules/contrib/TripalCultivate-Phenotypes

WORKDIR /var/www/drupal9/web/modules/contrib/TripalCultivate-Phenotypes

RUN service postgresql restart \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && drush en trpcultivate_phenotypes trpcultivate_phenocollect trpcultivate_phenoshare --yes
