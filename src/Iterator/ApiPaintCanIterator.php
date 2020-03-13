<?php

namespace Drupal\sync_external_posts\Iterator;

class ApiPaintCanIterator implements \Iterator, \Countable {

  /**
   * The paint can Api.
   *
   * @var \Drupal\sync_external_posts\Api\ApiGetPaintCansService
   */
  protected $apiGetPaintCans;

  /**
   * The current item.
   *
   * @var int
   */
  protected $currentPosition;

  /**
   * The total number of results.
   *
   * @var int
   */
  protected $count;

  /**
   * The number of results per page.
   *
   * @var int
   */
  protected $perPage;

  /**
   * The current page.
   *
   * @var int
   */
  protected $currentPage;

  /**
   * The current page results.
   *
   * @var array
   */
  protected $currentPageResults;

  /**
   * ApiMigrateSourceIterator constructor.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function __construct() {
    $this->apiGetPaintCans = \Drupal::service('sync_external_posts.api_get_paint_cans');
    $this->currentPosition = 0;
    $this->currentPage = 0;
    $this->count = $this->apiGetPaintCans->getTotalPosts();
    $this->perPage = $this->apiGetPaintCans->getPostsPerPage();
  }

  /**
   * Total number of results.
   *
   * @return int|void
   */
  public function count() {
    return $this->count;
  }

  /**
   * Required: Get the current item details.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function current() {
    if (!$this->currentPage) {
      $this->updateIterator();
    }

    // If we are at for instance progress '7' when pages are '6' long, on
    // page 2, the index we want is '1'. Getting the remainder of the
    // progress divided by the number per page gives us this.
    $current_index = $this->key() % $this->perPage;
    if (isset($this->currentPageResults[$current_index])) {
      return $this->currentPageResults[$current_index];
    }
    return FALSE;
  }

  /**
   * Required: Get the current position key.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function key() {
    return $this->currentPosition;
  }

  /**
   * Required: Allow moving to the next item.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function next() {
    $this->currentPosition += 1;
    $this->updateIterator();
  }

  /**
   * Required: Allow rewinding back to the start position.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function rewind() {
    $this->currentPosition = 0;
    $this->updateIterator();
  }

  /**
   * Required: Ensure that the current position is a valid item.
   *
   * @return bool
   */
  public function valid() {
    if ($this->currentPosition >= 0 && $this->currentPosition < $this->count()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determine whether we need to make an API call.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function updateIterator() {
    // Determine the current page by seeing the current item number and
    // comparing with the number of results per page.
    $current_page = (int) ceil(($this->currentPosition + 1) / $this->perPage);

    // Fetch the page from the API if we don't have it yet.
    if ($this->currentPage != $current_page) {

      // Get the page of results.
      $this->currentPageResults = $this->apiGetPaintCans->getPostsData($current_page);
      $this->currentPage = $current_page;
    }
  }

}
