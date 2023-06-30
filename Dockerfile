ARG drupalversion='10.0.x-dev'
ARG chadoschema='testchado'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php8.1-pgsql13

COPY . /var/www/drupal9/web/modules/contrib/TripalCultivate-Phenotypes

WORKDIR /var/www/drupal9/web/modules/contrib/TripalCultivate-Phenotypes

RUN service postgresql restart \
  && drush trp-drop-chado --schema-name='chado' \
  && drush trp-install-chado --schema-name='testchado' --version=1.3 \
  && drush en trpcultivate_phenotypes trpcultivate_phenocollect trpcultivate_phenoshare --yes
