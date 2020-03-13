<?php

namespace Drupal\sync_external_posts\Node;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;

/**
 * Class NodePaintCanUpdateService.
 */
class NodePaintCanUpdateService {

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ApiConnectionService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Insert or update paint can.
   *
   * @param $data
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function upsertNodePaintCan($data) {
    $node = $this->getExistingNodeById($data['id']);
    if (!$node) {
      $node = $this->createNewNode($data['id'], $data['name']);
    }

    if ($node) {
      /** @var $node \Drupal\node\Entity\Node */
      $hex_colour = str_replace('#', '', $data['color']);
      $node->setTitle(ucwords($data['name']));
      $node->set('field_colour', $hex_colour);
      $node->set('field_year', $data['year']);
      $node->set('field_pantone_value', $data['pantone_value']);
      $node->setPublished(TRUE);
      $node->save();
    }
  }

  /**
   * Get an existing paint can node by external id.
   *
   * @param $id
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getExistingNodeById($id) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery();
    $query->condition('type', 'paint_can');
    $query->condition('field_external_id', $id);
    $node_ids = $query->execute();
    if ($node_ids) {
      $node_id = reset($node_ids);
      return $node_storage->load($node_id);
    }
    return FALSE;
  }

  /**
   * Create a new pain can node.
   *
   * @param $id
   * @param $name
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createNewNode($id, $name) {
    return $this->entityTypeManager->getStorage('node')->create([
      'field_external_id' => $id,
      'title' => $name,
      'type' => 'paint_can',
    ]);
  }

}
