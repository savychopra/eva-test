<?php

namespace Drupal\zoomapi\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ZoomApiWebhookEvent.
 *
 * @package Drupal\zoomapi\Event
 */
class ZoomApiWebhookEvent extends Event {

  /**
   * Payload of the incoming post.
   *
   * @var array
   */
  protected $payload;

  /**
   * Type of the incoming post.
   *
   * @var string
   */
  protected $event;

  /**
   * Type of the incoming post.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * ZoomApiWebhookEvent constructor.
   *
   * @param string $event
   *   The type of webhook incoming.
   * @param array $payload
   *   The data posted.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The orignal request.
   */
  public function __construct($event, array $payload, Request $request) {
    $this->event = $event;
    $this->payload = $payload;
    $this->request = $request;
  }

  /**
   * Return payload.
   *
   * @return array
   *   Zooms JSON payload decoded.
   */
  public function getPayload() {
    return $this->payload;
  }

  /**
   * Return event.
   *
   * @return string
   *   The type of webhook event defined by Zoom.
   */
  public function getEvent() {
    return $this->event;
  }

  /**
   * Return request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The original request in case you need more.
   */
  public function getRequest() {
    return $this->request;
  }

}
