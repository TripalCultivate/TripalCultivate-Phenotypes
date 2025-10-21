<?php

namespace Drupal\trpcultivate_phenotypes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class definition of Experiment Add Trait.
 */
class PhenoExperimentAddTraitForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'content_bio_data_research_experiment_add_trait_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {





    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
