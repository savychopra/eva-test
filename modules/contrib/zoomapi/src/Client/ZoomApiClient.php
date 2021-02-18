<?php

namespace Drupal\zoomapi\Client;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Component\Datetime\Time;
use Drupal\zoomapi\ZoomApiClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;

/**
 * Uses http client to interact with the Zoom API.
 */
class ZoomApiClient implements ZoomApiClientInterface {

  /**
   * The Immutable Config Object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The KeyRepositoryInterface.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Drupal\Component\Datetime\Time definition.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Zoom API Key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * Zoom API Secret.
   *
   * @var string
   */
  protected $apiSecret;

  /**
   * Zoom base API.
   *
   * @var string
   */
  protected $baseUri;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Client interface.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   Key repository interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory interface.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\Component\Datetime\Time $time
   *   Time.
   */
  public function __construct(
    ClientInterface $http_client,
    KeyRepositoryInterface $key_repository,
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger,
    Time $time
    ) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->time = $time;
    $this->keyRepository = $key_repository;
    $this->config = $config_factory->get('zoomapi.settings');
    $this->apiKey = $this->getKeyValue('api_key');
    $this->apiSecret = $this->getKeyValue('api_secret');
    $this->baseUri = $this->config->get('base_uri');
  }

  /**
   * Utilizes Drupal's httpClient to connect to the Zoom API.
   *
   * Info: https://marketplace.zoom.us/docs/api-reference/introduction
   * Currently just supports JWT authentication.
   *
   * @param string $method
   *   get, post, patch, delete, etc. See Guzzle documentation.
   * @param string $endpoint
   *   The Zoom API endpoint (ex. users)
   * @param array $query
   *   Any Query Parameters defined in the API spec.
   * @param array $body
   *   Array that will get converted to JSON for some requests.
   *
   * @return mixed
   *   RequestException or \GuzzleHttp\Psr7\Response body
   */
  public function request(string $method, string $endpoint, array $query = [], array $body = []) {
    try {
      $response = $this->httpClient->{$method}(
        $this->baseUri . $endpoint,
        $this->buildOptions($query, $body)
      );
      // TODO: Add additional response options.
      $payload = Json::decode($response->getBody()->getContents());
      return $payload;
    }
    catch (RequestException $exception) {
      // Log Any exceptions.
      $this->logger->error('Failed to complete Zoom API Task "%error"', ['%error' => $exception->getMessage()]);
      throw $exception;
    }
  }

  /**
   * Build options for the client.
   *
   * @param array $query
   *   An array of querystring params for guzzle.
   * @param array $body
   *   An array of items that guzzle with json_encode.
   *
   * @return array
   *   An array of options for guzzle.
   */
  private function buildOptions(array $query = [], array $body = []) {
    $options = [];
    $options['headers'] = [
      'Authorization' => 'Bearer ' . $this->setAuthHeader(),
    ];
    if (!empty($body)) {
      // Json key converts array to json & adds appropriate content-type header.
      $options['json'] = $body;
    }
    if (!empty($query)) {
      $options['query'] = $query;
    }
    return $options;
  }

  /**
   * Generate JSON Web Token.
   *
   * TODO: allow for changing expiration.
   */
  protected function setAuthHeader() {
    $token = [
      'iss' => $this->apiKey,
      'exp' => ($this->time->getRequestTime() + 60) * 1000,
    ];
    return JWT::encode($token, $this->apiSecret);
  }

  /**
   * Used to validate Configuration.
   *
   * This could be more thorough.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function validateConfiguration() {
    $props = [
      'apiKey',
      'apiSecret',
      'baseUri',
    ];

    foreach ($props as $prop) {
      if (empty($this->{$prop})) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Return a KeyValue.
   *
   * @param string $whichConfig
   *   Name of the config in which the key name is stored.
   *
   * @return mixed
   *   Null or string.
   */
  protected function getKeyValue($whichConfig) {
    if (empty($this->config->get($whichConfig))) {
      return NULL;
    }
    $whichKey = $this->config->get($whichConfig);
    $keyValue = $this->keyRepository->getKey($whichKey)->getKeyValue();

    if (empty($keyValue)) {
      return NULL;
    }

    return $keyValue;
  }

}
