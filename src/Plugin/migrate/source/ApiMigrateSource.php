<?php

namespace Drupal\sync_external_posts\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\sync_external_posts\Iterator\ApiPaintCanIterator;

/**
 * Source plugin for API paint cans.
 *
 * @MigrateSource(
 *   id = "api_migrate_source_paint_cans"
 * )
 */
class ApiMigrateSource extends SourcePluginBase {

  /**
   * Return an interator for the API to iterate over.
   *
   * @return \Drupal\sync_external_posts\Iterator\ApiPaintCanIterator|\Iterator
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function initializeIterator() {
    return new ApiPaintCanIterator();
  }

  /**
   * Prints the available keys when called as a string.
   *
   * @return string
   */
  public function __toString() {
    $fields = $this->fields();
    return implode(', ', array_keys($fields));
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('Paint Can ID'),
      'name' => $this->t('Name of paint'),
      'year' => $this->t('The year'),
      'color' => $this->t('The colour'),
      'pantone_value' => $this->t('Pantone value'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
      ],
    ];
  }

}
