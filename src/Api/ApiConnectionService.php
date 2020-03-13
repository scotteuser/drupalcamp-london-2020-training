<?php

namespace Drupal\sync_external_posts\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use GuzzleHttp\ClientInterface;

/**
 * Class ApiConnectionService.
 */
class ApiConnectionService {

  /**
   * The Guzzle http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The API base URL.
   *
   * @var string
   */
  protected $apiBaseUrl;

  /**
   * Constructs a new ApiConnectionService object.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
    $this->apiBaseUrl = 'https://reqres.in/api';
  }

  /**
   * Make an HTTP GET request.
   *
   * @param $endpoint
   *   The API endpoint, eg '/posts'.
   * @param $args
   *   Possible query string arguments to pass.
   *
   * @return bool|mixed
   *   The decoded response body or false.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getRequest($endpoint, array $args = []) {
    $url = $this->apiBaseUrl . $endpoint;
    if ($query = UrlHelper::buildQuery($args)) {
      $url .= '?' . $query;
    }
    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() === 200) {
      $json_body = $response->getBody();
      return Json::decode($json_body);
    }
    return FALSE;
  }

}
