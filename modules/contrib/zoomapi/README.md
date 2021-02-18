INTRODUCTION
------------

Zoom API module provides functionality for developers that would like to
interact the Zoom API. This modules tries to make little assumptions about how
you would like to interact with the API and focuses and ease of connection.

Note: Currently this module ONLY supports JWT to interact with the API. See the
JWT section at https://marketplace.zoom.us/docs/api-reference/using-zoom-apis

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/zoomapi

REQUIREMENTS
------------

This module requires the following modules:

 * Key (https://www.drupal.org/project/key)

INSTALLATION
------------
 * Since the module requires an external library, Composer must be used.
   `composer require "drupal/zoomapi ^1.0"`
 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.
 * Use ```composer update drupal/zoomapi --with-dependencies```
   to update to a new release.

CONFIGURATION
-------------

 * If you haven't already, create a Zoom App (JWT) Be sure to record your API
   key and secret for later use. https://marketplace.zoom.us/develop/create
Enable this Drupal module (Zoom API).
 * After enabling the module set the necessary API credentials in Drupal at
   /admin/config/zoomapi You will need to setup your API key and secret using
   the required Key module. Storing keys in files outside the webroot or as
   environment variables is recommended.
 * If using Zoom Event Subscriptions feature (webhooks), enable them in your
   Zoom Application (https://marketplace.zoom.us/user/build). Copy your
  Verification Token into /admin/config/zoomapi. On the Zoom side while
  managing your app, enter your "Event Notification endpoint URL"
  https://your-domain.com/zoomapi-webhooks

TROUBLESHOOTING
---------------

 * If API calls are failing, please check the logs.
 * This
 * Ensure that the module has been configured.
 * Report an issue https://www.drupal.org/project/issues/zoomapi

USAGE
---------------

A reminder, this module does nothing on its own. You must create your own
custom module to leverage the Zoom API module.

### Zoom Event Subscriptions (a.k.a. Webhooks)
You'll need to add an additonal Webhook Verification Token in the configuration
at /admin/config/zoomapi
Configure your Zoom Application to send webhooks to
https://example.com/zoomapi-webhooks
All events from Zoom dispatch a Drupal event that can be subscribed to.

1. In your custom module, add the following to your_module.services.yml

```
services:
  your_module.event_subscriber:
    class: Drupal\your_module\ZoomApiWebhookEventSubscriber
    tags:
      - {name: event_subscriber}
```

2. In your custom module (custom_module/src) add
ZoomApiWebhookEventSubscriber.php

```
<?php

namespace Drupal\your_module;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\zoomapi\Event\ZoomApiEvents;
use Drupal\zoomapi\Event\ZoomApiWebhookEvent;

/**
 * Class ZoomApiWebhookEventSubscriber.
 *
 * @package Drupal\your_module\ZoomApiWebhookEventSubscriber
 */
class ZoomApiWebhookEventSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ZoomApiEvents::WEBHOOK_POST][] = ['subscribe'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(ZoomApiWebhookEvent $event) {
    \Drupal::logger('your_module')->debug($event->getEvent());
  }

}
```

### API Client

1. Inject or statically call the Zoom API Client in your code.

`$client = Drupal::service('zoomapi.client');`
The client is based on Drupal HttpClient.

See zoomapi/src/ZoomApiClientInterface.php for var definitions.
`$response = $client->request($method, $endpoint, $query, $body);`

If successful, an array of decoded JSON will be returned.
