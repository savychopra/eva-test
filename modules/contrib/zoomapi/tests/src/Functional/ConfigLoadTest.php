<?php

namespace Drupal\Tests\zoomapi\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Simple test to ensure that the config page loads.
 *
 * @group zoomapi
 */
class ConfigLoadTest extends BrowserTestBase {

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
    $this->user = $this->drupalCreateUser(['administer zoom api']);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the config page loads with a 200 response.
   */
  public function testConfigLoad() {
    $this->drupalGet(Url::fromRoute('zoomapi.settings'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
