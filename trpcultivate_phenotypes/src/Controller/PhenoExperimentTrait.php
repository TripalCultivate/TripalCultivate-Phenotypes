<?php

namespace Drupal\trpcultivate_phenotypes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Phenotypes experiment configuration trait operations.
 */
class PhenoExperimentTrait extends ControllerBase {

  /**
   * Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * Traits service.
   *
   * @var \Drupal\trpcultivate_phenotypes\Service\TripalCultivatePhenotypesTraitsService
   */
  protected TripalCultivatePhenotypesTraitsService $service_PhenoTraits;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   Drupal database connection.
   * @param \Drupal\trpcultivate_phenotypes\Service\ripalCultivatePhenotypesTraitsService $service_PhenoTraits
   *   TripalCultivate Phenotypes Traits service.
   */
  public function __construct(
    Connection $db,
    TripalCultivatePhenotypesTraitsService $service_PhenoTraits,
  ) {

    $this->db = $db;
    $this->service_PhenoTraits = $service_PhenoTraits;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('trpcultivate_phenotypes.traits'),
    );
  }

  /**
   * Add trait to experiment.
   */
  public function addTrait(Request $request) {

    $values = $request->request->get('combo');

    $add_trait = json_decode($values, TRUE);

    [$attr_id, $observable_id, $unit_id] = explode(':', $add_trait['ids']);

    $label = $add_trait['label'];
    if (!$label) {
      $this->service_PhenoTraits->setTraitGenus($add_trait['genus']);
      $trait = $this->service_PhenoTraits->getTraitMethodUnitCombo($attr_id, $observable_id, $unit_id);
      $label = $trait['trait']->name . ' ' . $trait['method']->name;
    }

    $this->db->insert('trpcultivate_phenocombo')
      ->fields([
        'project_id' => $add_trait['project_id'],
        'attr_id' => $attr_id,
        'observable_id' => $observable_id,
        'unit_id' => $unit_id,
        'label' => $label,
        'is_archived' => 0,
        'is_required' => 0,
        'was_shared' => 0,
        'was_collected' => 0,
        'uid' => $add_trait['uid'],
        'timestamp' => time(),
      ])
      ->execute();

    return new JsonResponse(['message' => '']);
  }

}
