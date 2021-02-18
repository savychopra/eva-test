<?php

namespace Drupal\gotowebinar_sync\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates the GotoWebinar webhook contains the secret key.
 */
class ValidateSync implements AccessInterface {

  /**
   * Gotowebinar configuration settings.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * Creates a DiffFormatter to render diffs in a table.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('gotowebinar_sync.settings');
  }

  /**
   * Validates the webhook secret key.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The http request object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request) {
    $json = $request->getContent();
    $signature = $request->headers->get('X-Webhook-Signature');
    $timestamp = $request->headers->get('X-Webhook-Signature-Timestamp');

    if ($this->verifySignature($json, $timestamp, $signature)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Verify the signature from the webhook payload.
   */
  private function verifySignature($json, $timestamp, $signature) {
    $data = "{$timestamp}:{$json}";

    if ($signature == base64_encode(hash_hmac('sha256', $data, $this->config->get('secret_key'), TRUE))) {
      return TRUE;
    }
    return FALSE;
  }

}
