ARG drupalversion=11.3.x-dev
ARG phpversion=8.5
ARG pgsqlversion=17
FROM knowpulse/tripalcultivate-base:drupal${drupalversion}-php${phpversion}-pgsql${pgsqlversion}

COPY . /var/www/drupal/web/modules/contrib/TripalCultivate-Phenotypes
WORKDIR /var/www/drupal/web/modules/contrib/TripalCultivate-Phenotypes

RUN rm ./phpunit.xml
RUN bash /var/www/drupal/web/modules/contrib/tripal/set_phpunit_config.sh

RUN service postgresql restart \
  && drush en trpcultivate_phenotypes trpcultivate_phenocollect trpcultivate_phenoshare --yes \
  && drush tripal:trp-run-jobs --username=drupaladmin \
  && drush cr
