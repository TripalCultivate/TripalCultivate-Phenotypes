<?php

namespace Drupal\trpcultivate_phenoshare\PhenotypesImporter;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

interface PhenotypesImporterInterface extends PluginInspectionInterface {
    public function form($form, &$form_state);
    public function formSubmit($form, &$form_state);
    public function formValidate($form, &$form_state);
    public function run();
    
}