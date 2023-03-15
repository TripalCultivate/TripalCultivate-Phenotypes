FROM tripalproject/tripaldocker:latest
MAINTAINER Lacey-Anne Sanderson <lacey.sanderson@usask.ca>

COPY . /var/www/drupal9/web/modules/TripalCultivate-Phenotypes

WORKDIR /var/www/drupal9/web/modules/TripalCultivate-Phenotypes

RUN service postgresql restart \
  && drush en trpcultivate_phenotypes --yes
