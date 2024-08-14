<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\TripalCultivatePhenotypesValidatorBase;
use Drupal\trpcultivate_phenotypes\TripalCultivateValidator\ValidatorTraits\HeaderMetadata;

/**
 * Fake Validator that does not implement any of its own methods.
 * Used to test the base class.
 *
 * @TripalCultivatePhenotypesValidator(
 *   id = "validator_requiring_header_metadata",
 *   validator_name = @Translation("Validator Using Header Metadata Trait"),
 *   input_types = {"header-row", "data-row"}
 * )
 */
class ValidatorHeaderMetadata extends TripalCultivatePhenotypesValidatorBase {

  use HeaderMetadata;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }
}
