FROM tripalproject/tripaldocker:drupal10.0.x-dev-php8.1-pgsql13

COPY . /var/www/drupal9/web/modules/contrib/TripalCultivate-Phenotypes

WORKDIR /var/www/drupal9/web/modules/contrib/TripalCultivate-Phenotypes

RUN service postgresql restart \
  && drush en trpcultivate_phenotypes trpcultivate_phenocollect trpcultivate_phenoshare --yes
