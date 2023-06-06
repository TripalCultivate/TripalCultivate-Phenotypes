<?php

namespace Drupal\trpcultivate_phenoshare\Annotations;

use Drupal\Component\Annotation\Plugin;

class PhenotypesImporter extends Plugin {
  public $id;

  public $label;

  public $description;

  public $file_types;

  public $upload_description;
  
  public $upload_title;

  public $use_analysis;

  public $require_analysis;

  public $buytton_text;

  public $file_upload;

  public $file_local;

  public $file_remote;

  public $file_required;

  public $argument_list = [];

  public $cardinality;

  public $menu_path;

  public $callback;

  public $callback_module;

  public $callback_path;
}