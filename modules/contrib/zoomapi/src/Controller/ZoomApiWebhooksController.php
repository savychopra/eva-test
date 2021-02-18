<?php

namespace Drupal\zoomapi\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\zoomapi\Event\ZoomApiEvents;
use Drupal\zoomapi\Event\ZoomApiWebhookEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ZoomApiWebhooksController.
 */
class ZoomApiWebhooksController extends ControllerBase {

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
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Symfony\Component\EventDispatcher\EventDispatcherInterface definition.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Enable or disable debugging.
   *
   * @var bool
   */
  protected $debug = FALSE;

  /**
   * Zoom webhook verification token.
   *
   * @var string
   */
  protected $webhookVerificationToken;

  /**
   * Constructs a new WebhookController object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   Key repository interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher interface.
   */
  public function __construct(
      LoggerInterface $logger,
      KeyRepositoryInterface $key_repository,
      ConfigFactoryInterface $config_factory,
      RequestStack $request_stack,
      EventDispatcherInterface $event_dispatcher
    ) {
    $this->logger = $logger;
    $this->requestStack = $request_stack;
    $this->eventDispatcher = $event_dispatcher;
    $this->keyRepository = $key_repository;
    $this->config = $config_factory->get('zoomapi.settings');
    $this->webhookVerificationToken = $this->getKeyValue('webhook_verification_token');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.channel.zoomapi'),
      $container->get('key.repository'),
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Capture the incoming payload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A simple JSON response.
   */
  public function capture(Request $request) {
    // Capture the payload.
    $payload = $request->getContent();

    // Check if the payload is empty.
    if (empty($payload)) {
      $message = 'The Zoom webhook payload was missing.';
      $this->logger->notice($message);
      $response = [
        'success' => FALSE,
        'message' => $message,
        'data' => [],
      ];
      return new JsonResponse($response, 400);
    }

    // JSON decode the payload.
    // TODO: We could do additional error checking of the payload.
    $data = Json::decode($payload);

    // Ability to debug the incoming payload.
    if ($this->debug) {
      $this->logger->debug('<pre><code>' . print_r($data, TRUE) . '</code></pre>');
    }

    // Dispatch Event.
    // Allows other modules to respond.
    // Var $data['event'] = Name of webhook event from Zoom.
    // Var $data['payload'] = Payload data from Zoom.
    // Var $request = The complete request from Zoom.
    $dispatch = new ZoomApiWebhookEvent($data['event'], $data['payload'], $request);
    $this->eventDispatcher->dispatch(ZoomApiEvents::WEBHOOK_POST, $dispatch);

    $response = [
      'success' => TRUE,
      'message' => 'Webhook payload captured!',
      'data' => [],
    ];
    return new JsonResponse($response);
  }

  /**
   * Compares local webhook token to incoming.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   AccessResult allowed or forbidden.
   */
  public function authorize() {
    $request = $this->requestStack->getCurrentRequest();
    // Token was not retreieved.
    if (!$zoomToken = $this->getZoomVerificationToken($request)) {
      $this->logger->debug('The Zoom API webhook post could not be verified.');
      return AccessResult::forbidden();
    }

    // Saved token matches incoming from zoom.
    if ($zoomToken === $this->webhookVerificationToken) {
      return AccessResult::allowed();
    }
    $this->logger->debug('The Zoom API webhook post could not be verified.');
    return AccessResult::forbidden();
  }

  /**
   * Gets the Zoom authorize header from the incoming request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   FALSE or the authorization header.
   */
  protected function getZoomVerificationToken(Request $request) {
    // Check for the authorization header provided by Zoom.
    if (!$request->headers->has('authorization')) {
      return FALSE;
    }
    return $request->headers->get('authorization');
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
