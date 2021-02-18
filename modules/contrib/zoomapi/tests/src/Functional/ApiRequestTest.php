<?php

namespace Drupal\Tests\zoomapi\Functional;

use Drupal\Core\Url;
use Drupal\Tests\key\Functional\KeyTestTrait;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Exception\RequestException;

/**
 * API request tests.
 *
 * @group zoomapi
 */
class ApiRequestTest extends BrowserTestBase {

  use KeyTestTrait;

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
   * Will fail, but that's ok.
   */
  public function testZoomApiConnection() {
    // Create test keys.
    // Api Key.
    $this->createTestKey(
      'test_zoomapi_key',
      'authentication',
      'config'
    );
    // API Secret.
    $this->createTestKey(
      'test_zoomapi_secret',
      'authentication',
      'config'
    );

    // Update Zoomapi config.
    $this->drupalGet(Url::fromRoute('zoomapi.settings'));
    $edit = [
      'base_uri' => 'https://api.zoom.us/v2',
      'api_key' => 'test_zoomapi_key',
      'api_secret' => 'test_zoomapi_secret',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    // Post to the webhook controller.
    $client = \Drupal::service('zoomapi.client');
    try {
      $client->request('GET', '/users');
    }
    catch (RequestException $exception) {
      // We are expecting failure.
      $message = $exception->getMessage();
      $this->assertStringContainsString('401 Unauthorized', $message, '401 Unauthorized was returned.');
      $this->assertStringContainsString('Client error', $message, 'Asserting client error exists.');
    }

  }

}
