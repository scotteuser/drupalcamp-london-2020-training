<?php

namespace Drupal\sync_external_posts\Batch;

use Drupal\sync_external_posts\Api\ApiGetPaintCansService;

/**
 * Class BatchProcessor.
 */
class BatchProcessor {

  /**
   * Batch processing callback for the batch operation.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function operationCallback(&$context) {

    /** @var \Drupal\sync_external_posts\Api\ApiGetPaintCansService $api_get_paint_cans */
    $api_get_paint_cans = \Drupal::service('sync_external_posts.api_get_paint_cans');
    if (empty($context['sandbox'])) {
      $context = self::initialiseSandbox($context, $api_get_paint_cans);
    }

    // Nothing to process.
    if (!$context['sandbox']['max']) {
      $context['finished'] = 1;
    }

    // If we haven't yet processed all.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $limit_per_batch = 2;

      // Zero-indexed key.
      $count_this_batch = 0;
      while ($count_this_batch < $limit_per_batch && $context['sandbox']['progress'] < $context['sandbox']['max']) {

        // Determine the current page by seeing the current item number and
        // comparing with the number of results per page.
        $current_page = (int) ceil(($context['sandbox']['progress'] + 1) / $context['sandbox']['per_page']);

        // Get the page of results.
        $api_results_page = $api_get_paint_cans->getPostsData($current_page);

        // If we are at for instance progress '7' when pages are '6' long, on
        // page 2, the index we want is '1'. Getting the remainder of the
        // progress divided by the number per page gives us this.
        $current_index = $context['sandbox']['progress'] % $context['sandbox']['per_page'];
        if (isset($api_results_page[$current_index])) {

          // Let's pretend the individual API call gives us more data than the listing.
          $data = $api_get_paint_cans->getPostData($api_results_page[$current_index]['id']);

          if ($data) {

            // With our data, upsert the paint can.
            /** @var \Drupal\sync_external_posts\Node\NodePaintCanUpdateService $node_paint_can_update */
            $node_paint_can_update = \Drupal::service('sync_external_posts.node_paint_can_update');
            $node_paint_can_update->upsertNodePaintCan($data);

            // Store the ID for the finished callback.
            $context['results'][] = $data['id'];
          }
        }
        // Always increase the progress even if there is an error or we will
        // get stuck in an endless loop. Instead, set error messages, and if
        // you want to stop progressing on an error, set 'finished' to '1'.
        $context['sandbox']['progress']++;

        // Optional message displayed under the progressbar.
        $context['message'] = t('Processing item number "@progress".', [
          '@progress' => $context['sandbox']['progress'],
        ]);

        // Increase the number processed this particular batch.
        $count_this_batch++;
      }
    }

    // When progress equals max, finished is '1' which means completed. Any
    // decimal between '0' and '1' is used to determine the percentage of
    // the progress bar.
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Initialise the sandbox when the batch processing first starts.
   *
   * @param $context
   * @param \Drupal\sync_external_posts\Api\ApiGetPaintCansService $api_get_paint_cans
   *
   * @return mixed
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected static function initialiseSandbox($context, ApiGetPaintCansService $api_get_paint_cans) {
    $context['sandbox'] = [];
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = $api_get_paint_cans->getTotalPosts();
    $context['sandbox']['per_page'] = $api_get_paint_cans->getPostsPerPage();
    return $context;
  }

  /**
   * Batch finished callback.
   *
   * @param $success
   * @param $results
   * @param $operations
   */
  public static function finishedCallback($success, $results, $operations) {
    if ($success) {

      // The 'success' parameter means no fatal PHP errors were detected.
      $message = t('@count paint cans were synced successfully.', [
        '@count' => count($results),
      ]);
      \Drupal::messenger()->addStatus($message);
    }
    else {

      // A fatal error occurred.
      $message = t('Finished with an error.');
      \Drupal::messenger()->addWarning($message);
    }
  }

}
