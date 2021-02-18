<?php

namespace Drupal\Tests\zoomapi\Functional;

use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\Tests\BrowserTestBase;

/**
 * Webhook post tests.
 *
 * @group zoomapi
 */
class WebhookPostTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['zoomapi', 'key'];

  /**
   * A key entity to use for testing.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $testKey;

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['administer zoom api', 'administer keys']);
    $this->drupalLogin($this->user);
  }

  /**
   * GET method should return a 405.
   */
  public function testGetMethodBlocked() {
    $url = Url::fromRoute('zoomapi.webhooks')
      ->setAbsolute()
      ->toString();

    $res = $this->getHttpClient()
      ->get($url, ['http_errors' => FALSE]);
    $this->assertEquals($res->getStatusCode(), 405, 'GET method not allowed.');
  }

  /**
   * Post without authorization header. Access Denied.
   */
  public function testWebhookPostNoAuth() {
    $url = Url::fromRoute('zoomapi.webhooks')
      ->setAbsolute()
      ->toString();

    $res = $this->getHttpClient()
      ->post($url, ['http_errors' => FALSE]);
    $this->assertEquals($res->getStatusCode(), 403, 'Access Denied due to missing authorization header.');
  }

  /**
   * Test Webhook Post with header.
   */
  public function testWebhookPost() {
    // Create a test key.
    $this->createTestKey(
      'zoomapi_webhook_test_key',
      'authentication',
      'config'
    );

    // Update Zoomapi config.
    $this->drupalGet(Url::fromRoute('zoomapi.settings'));
    $edit = [
      'webhook_verification_token' => 'zoomapi_webhook_test_key',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $url = Url::fromRoute('zoomapi.webhooks')
      ->setAbsolute()
      ->toString();

    // Mock payload.
    $options = [
      'headers' => ['authorization' => 'taco'],
      'http_errors' => FALSE,
      'json' => [
        'event' => 'meeting.started',
        'payload' => [],
      ],
    ];
    // Post to the webhook controller.
    $res = $this->getHttpClient()->post($url, $options);
    $this->assertEquals($res->getStatusCode(), 200, 'Authorization header matched. Post was successful.');

    // Fail with an incorrect authorization header.
    $options['headers']['authorization'] = 'burrito';
    $res = $this->getHttpClient()->post($url, $options);
    $this->assertEquals($res->getStatusCode(), 403, 'Authorization header did not match. Post was denied.');

    // Empty out the payload.
    $options['headers']['authorization'] = 'taco';
    unset($options['json']);
    $res = $this->getHttpClient()->post($url, $options);
    $this->assertEquals($res->getStatusCode(), 400, 'Payload was empty.');
  }

  /**
   * Make a key for testing operations that require a key.
   */
  protected function createTestKey($id, $type = NULL, $provider = NULL) {
    $keyArgs = [
      'id' => $id,
      'label' => 'Zoom API Webhook Test Key',
    ];
    if ($type != NULL) {
      $keyArgs['key_type'] = $type;
    }
    if ($provider != NULL) {
      $keyArgs['key_provider'] = $provider;
    }
    $this->testKey = Key::create($keyArgs);
    $this->testKey->setKeyValue('taco');
    $this->testKey->save();
    return $this->testKey;
  }

}
