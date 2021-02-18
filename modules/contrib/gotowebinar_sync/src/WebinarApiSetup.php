<?php

namespace Drupal\gotowebinar_sync;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\MockObject\Stub\Exception;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Class WebinarApiSetup.
 */
class WebinarApiSetup {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * Config Interface for accessing site configuration.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Gotowebinar configuration settings.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  private $logger;

  /**
   * Boolean tracking whether the debug config option is turned on.
   *
   * @var bool
   */
  private $debug;

  /**
   * String containing the base URI for API requests.
   *
   * @var string
   */
  private $baseURI = 'https://api.getgo.com/';

  /**
   * Constructs a new WebinarSync object.
   */
  public function __construct(ClientInterface $http_client,
      ConfigFactoryInterface $config,
      LoggerChannelFactory $logger) {
    $this->httpClient = $http_client;
    $this->configFactory = $config->getEditable('gotowebinar_sync.settings');
    $this->config = $config->get('gotowebinar_sync.settings');
    $this->logger = $logger->get('gotowebinar_sync');
    $this->debug = $this->config->get('debug_requests');
  }

  /**
   * Enables the GotoWebinar webhooks.
   *
   * @param string $response_key
   *   The response key to set for future api calls.
   */
  public function enableWebhooks(string $response_key) {
    if ($this->debug) {
      $this->logger->debug('Response Key (in enableWebhooks): @response', ['@response' => $response_key]);
    }

    $auth_header = $this->getAuthHeader($response_key);

    if (empty($auth_header)) {
      return;
    }

    $this->createSecretKey($auth_header);
    sleep(1);
    $created = $this->createAndActivateWebhook('webinar.created', $auth_header);
    $changed = $this->createAndActivateWebhook('webinar.changed', $auth_header);

    if ($created && $changed) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Generates an access token and uses it to crate an authorization header.
   *
   * @param string $response_key
   *   The response key to set for future api calls.
   *
   * @return string
   *   Returns the authorization header to use for subsequent API requests.
   */
  private function getAuthHeader($response_key) {
    $auth = base64_encode($this->config->get('consumer_key') . ':' . $this->config->get('consumer_secret'));

    if ($this->debug) {
      $this->logger->debug('Auth header (in getAuthHeader): @auth', ['@auth' => $auth]);
    }

    $token_response = $this->httpClient->post($this->baseURI . 'oauth/v2/token', [
      'headers' => [
        'Authorization' => "Basic " . $auth,
      ],
      'form_params' => [
        'grant_type' => 'authorization_code',
        'code' => $response_key,
        'redirect_uri' => $this->config->get('site_url') . '/___gotowebinar_oauth',
      ],
    ]);

    $access_token = $this->getData($token_response->getBody());

    if ($this->debug && !empty($access_token['access_token'])) {
      $this->logger->debug('Access Token (in getAuthHeader): @token', ['@token' => $access_token['access_token']]);
    }

    return !empty($access_token['access_token']) ? 'Bearer ' . $access_token['access_token'] : NULL;
  }

  /**
   * Generates and stores the secret key.
   *
   * @param string $auth_header
   *   The authorization string for API calls to G2W.
   */
  private function createSecretKey($auth_header) {
    $secret_response = $this->httpClient->post($this->baseURI . 'G2W/rest/v2/webhooks/secretkey', [
      'headers' => [
        'Authorization' => $auth_header,
      ],
    ]);
    $secret_key = $this->getData($secret_response->getBody());

    if (!empty($secret_key['value'])) {
      if ($this->debug) {
        $this->logger->debug('Secret Key (in createSecretKey): @key', ['@key' => $secret_key['value']]);
      }

      $this->configFactory->set('secret_key', $secret_key['value'])->save();
    }
  }

  /**
   * Creates and activates a webhook.
   *
   * @param string $webhook
   *   The webhook event name to activate.
   * @param string $auth_header
   *   The authorization string for API calls to G2W.
   */
  private function createAndActivateWebhook($webhook, $auth_header) {
    $webhook_key = $this->createWebhook($webhook, $auth_header);

    if ($webhook_key) {
      $retries = 0;
      while ($retries < 3) {
        if ($this->verifyWebhook($webhook_key, $auth_header)) {
          try {
            return $this->activateWebhook($webhook_key, $auth_header);
          }
          catch (Exception $e) {
            $this->logger->error('Error activating Webhook: @error', ['@error' => $e->getMessage()]);
          }
        }
        $retries++;
        sleep(2);
      }

      return FALSE;
    }
  }

  /**
   * Creates the webhook webhook.
   *
   * @param string $webhook
   *   The webhook event name to activate.
   * @param string $auth_header
   *   The authorization string for API calls to G2W.
   *
   * @return mixed
   *   Returns the webhook key or FALSE if not successful.
   */
  private function createWebhook($webhook, $auth_header) {
    $retries = 0;
    while ($retries < 3) {
      try {
        $response = $this->httpClient->post($this->baseURI . 'G2W/rest/v2/webhooks', [
          'headers' => [
            'Authorization' => $auth_header,
          ],
          'json' => [
            [
              'callbackUrl' => $this->config->get('site_url') . '/___gotowebinar_sync',
              'eventName' => $webhook,
              'eventVersion' => '1.0.0',
              'product' => 'g2w',
            ],
          ],
        ]);

        // The webhook doesn't always seem to be immediately available.
        sleep(2);

        $response_data = $this->getData($response->getBody());

        if ($this->debug) {
          $this->logger->debug('Create Webhook response data from G2W: @response', ['@response' => Json::encode($response_data)]);
        }

        return $response_data['_embedded']['webhooks'][0]['webhookKey'];
      }
      catch (ClientException $e) {
        $this->logger->error('Client Exception Error creating Webhook: @error', ['@error' => $e->getMessage()]);
      }
      catch (Exception $e) {
        $this->logger->error('Error creating Webhook: @error', ['@error' => $e->getMessage()]);
      }
      $retries++;
      sleep(2);
    }

    return FALSE;
  }

  /**
   * Verifies a webhook has been created and is available.
   */
  private function verifyWebhook($webhook_key, $auth_header) {
    try {
      $response = $this->httpClient->get($this->baseURI . 'G2W/rest/v2/webhooks/' . $webhook_key, [
        'headers' => [
          'Authorization' => $auth_header,
        ],
      ]);
      $response_data = $this->getData($response->getBody());

      if (!empty($response_data['webhookKey'])) {
        if ($this->debug) {
          $this->logger->debug('Verify Webhook Response Data (in verifyWebhook): @response', ['@response' => Json::encode($response_data)]);
        }
        return TRUE;
      }
    }
    catch (ClientException $e) {
      $this->logger->error('Client Exception Error verifying Webhook: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
    catch (Exception $e) {
      $this->logger->error('Error verifying Webhook: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Activates a webhook.
   */
  private function activateWebhook($webhook_key, $auth_header) {
    try {
      $this->httpClient->put($this->baseURI . 'G2W/rest/v2/webhooks', [
        'headers' => [
          'Authorization' => $auth_header,
        ],
        'json' => [
          [
            'state' => 'ACTIVE',
            'webhookKey' => $webhook_key,
          ],
        ],
      ]);
    }
    catch (ClientException $e) {
      $this->logger->error('Client Exception Error activating Webhook: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
    catch (Exception $e) {
      $this->logger->error('Error activating Webhook: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }

    sleep(2);

    try {
      $this->httpClient->post($this->baseURI . 'G2W/rest/v2/userSubscriptions', [
        'headers' => [
          'Authorization' => $auth_header,
        ],
        'json' => [
          [
            'userSubscriptionState' => 'ACTIVE',
            'webhookKey' => $webhook_key,
          ],
        ],
      ]);
    }
    catch (ClientException $e) {
      $this->logger->error('Client Exception Error subscribing user to Webhook: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
    catch (Exception $e) {
      $this->logger->error('Error subscribing user to Webhook: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Extracts JSON data from the webhook.
   *
   * @return array
   *   Returns an array of data or FALSE if no data is available.
   */
  private function getData($data) {
    if (empty($data)) {
      return FALSE;
    }
    return Json::decode($data);
  }

}
