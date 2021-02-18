<?php

namespace Drupal\gotowebinar_sync\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\gotowebinar_sync\WebinarSync;

/**
 * Class WebinarSyncController.
 */
class WebinarSyncController extends ControllerBase {

  /**
   * Webinar Sync service.
   *
   * @var webinarSync
   */
  private $webinarSync;

  /**
   * Constructs a new RouteController object.
   *
   * @param Drupal\gotowebinar_sync\WebinarSync $webinarSync
   *   The service for working with Webinar webhook data.
   */
  public function __construct(WebinarSync $webinarSync) {
    $this->webinarSync = $webinarSync;
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
    $webinarSync = $container->get('gotowebinar_sync.webinar_sync');
    return new static($webinarSync);
  }

  /**
   * Syncs Webinar data.
   *
   * @return array
   *   Returns json response with the status of the sync.
   */
  public function sync(Request $request) {
    $status = $this->webinarSync->process($request->getContent());
    return new JsonResponse([
      'data' => ['status' => $status],
      'method' => 'GET',
    ]);
  }

  /**
   * Handles GET requests which is needed for GotoWebinar to validate the url.
   *
   * @return array
   *   Returns json with a status OK.
   */
  public function get(Request $request) {
    return new JsonResponse([
      'data' => ['status' => 'OK'],
      'method' => 'GET',
    ]);
  }

}
