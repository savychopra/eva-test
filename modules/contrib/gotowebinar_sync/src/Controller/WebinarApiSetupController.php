<?php

namespace Drupal\gotowebinar_sync\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\gotowebinar_sync\WebinarApiSetup;

/**
 * Class WebinarApiSetupController.
 */
class WebinarApiSetupController extends ControllerBase {

  /**
   * Webinar API Setup service.
   *
   * @var webinarApiSetup
   */
  private $webinarApiSetup;

  /**
   * Constructs a new RouteController object.
   *
   * @param Drupal\gotowebinar_sync\WebinarApiSetup $webinarApiSetup
   *   The service for accessing setup and auth functions for G2W API.
   */
  public function __construct(WebinarApiSetup $webinarApiSetup) {
    $this->webinarApiSetup = $webinarApiSetup;
  }

  /**
   * Creates a new RouteController object with the api client.
   *
   * @param Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return RouteController
   *   Returns the new RouteController object.
   */
  public static function create(ContainerInterface $container) {
    $webinarApiSetup = $container->get('gotowebinar_sync.webinar_setup');
    return new static($webinarApiSetup);
  }

  /**
   * Handles the Oauth redirect path and stores the response key.
   *
   * @return array
   *   Returns a render array with a link back to the admin page.
   */
  public function oauth(Request $request) {
    if (!$request->get('code')) {
      throw new NotFoundHttpException();
    }

    if ($this->webinarApiSetup->enableWebhooks($request->get('code'))) {
      return [
        '#markup' => $this->t(
          'Successfully authenticated, <a href="@admin-url">Return to Admin page</a>',
          [
            '@admin-url' => '/admin/config/gotowebinar_sync/settings',
          ]
        ),
      ];
    }
    else {
      return [
        '#markup' => $this->t('There was an error creating and enabling the webhooks. Check the logs for more info.'),
      ];
    }
  }

}
