<?php

namespace Drupal\sync_external_posts\Api;

/**
 * Class ApiGetPostsService.
 */
class ApiGetPaintCansService {

  /**
   * The API Connection.
   *
   * @var \Drupal\sync_external_posts\Api\ApiConnectionService
   */
  protected $apiConnection;

  /**
   * Storage for retrieved posts so we avoid getting the same twice.
   *
   * @var array Storage for retrieved posts to prevent duplicate calls.
   */
  protected $retrievedPosts = [];

  /**
   * Constructs a new ApiConnectionService object.
   */
  public function __construct(ApiConnectionService $api_connection) {
    $this->apiConnection = $api_connection;
  }

  /**
   * Get all posts on a page.
   *
   * @param int $page_number
   *
   * @return mixed
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPosts($page_number = 1) {
    if (isset($this->retrievedPosts[$page_number])) {
      return $this->retrievedPosts[$page_number];
    }
    $this->retrievedPosts[$page_number] = $this->apiConnection->getRequest('/posts', [
      'page' => $page_number,
    ]);
    return $this->retrievedPosts[$page_number];
  }

  /**
   * Get data from a page of results.
   *
   * @param int $page_number
   *
   * @return bool|mixed
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPostsData($page_number = 1) {
    $results = $this->getPosts($page_number);
    if (isset($results['data'])) {
      return $results['data'];
    }
    return FALSE;
  }

  /**
   * Get a single of result.
   *
   * @param int $id
   *
   * @return bool|mixed
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPostData($id) {
    $results = $this->apiConnection->getRequest('/posts/' . $id);
    if (isset($results['data'])) {
      return $results['data'];
    }
    return FALSE;
  }

  /**
   * Get the number of posts per page.
   *
   * @return int
   *   The total number of posts.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPostsPerPage() {
    $results = $this->getPosts();
    if (isset($results['per_page'])) {
      return (int) $results['per_page'];
    }
    return 0;
  }

  /**
   * Get the total number of posts.
   *
   * @return int
   *   The total number of posts.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getTotalPosts() {
    $results = $this->getPosts();
    if (isset($results['total'])) {
      return (int) $results['total'];
    }
    return 0;
  }

}
