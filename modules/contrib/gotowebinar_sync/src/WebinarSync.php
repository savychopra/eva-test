<?php

namespace Drupal\gotowebinar_sync;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Component\Serialization\Json;
use Drupal\node\Entity\Node;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Class WebinarSync.
 */
class WebinarSync {

  /**
   * Config Interface for accessing site configuration.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * Stores webinar data to be synced.
   *
   * @var array
   */
  private $data;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  private $logger;

  /**
   * Drupal\Core\Lock\LockBackendInterface definition.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  private $lock;

  /**
   * Boolean tracking whether the debug config option is turned on.
   *
   * @var bool
   */
  private $debug;

  /**
   * The Registration link url for gotowebinar.
   *
   * @var string
   */
  private $registerLink = "https://attendee.gotowebinar.com/register/";

  /**
   * Constructs a new WebinarSync object.
   */
  public function __construct(ConfigFactoryInterface $config,
      EntityTypeManagerInterface $entity_type_manager,
      LoggerChannelFactory $logger,
      LockBackendInterface $lock) {
    $this->config = $config->get('gotowebinar_sync.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('gotowebinar_sync');
    $this->lock = $lock;
    $this->debug = $this->config->get('debug_requests');
  }

  /**
   * Process the GotoWebinar webhook POST data.
   *
   * @param string $json
   *   String containing JSON data from GotoWebinar webhook.
   *
   * @return bool
   *   Returns true is successful or false if an error occurred.
   */
  public function process(string $json) {
    if ($this->debug) {
      $this->logger->debug('Received webhook data from G2W: @json', ['@json' => $json]);
    }

    $this->data = $this->getData($json);

    if (empty($this->data['status'])) {
      return FALSE;
    }

    if (empty($this->config->get('sync_content_type')) || empty($this->config->get('webinar_key'))) {
      return FALSE;
    }

    // Only process a single G2W webhook request at a time.
    $lock_name = 'gotowebinar_sync_lock';
    $retries = 0;

    while ($retries < 10) {
      if ($this->lock->acquire($lock_name, 3)) {
        break;
      }

      // Wait three seconds and try again.
      $this->lock->wait($lock_name, 3);
      $retries++;
    }

    if ($retries == 10) {
      $this->logger->error('Unable to acquire lock for webinar key @key', ['@key' => $this->data['webinarKey']]);
      return FALSE;
    }

    $return = $this->processWebinarData();
    $this->lock->release($lock_name);
    return $return;
  }

  /**
   * Process webinar data to create or update webinars.
   */
  private function processWebinarData() {
    if ($this->data['status'] == 'DELETED' && empty($this->config->get('group_webinars'))) {
      // This will not delete the webinar if grouping is enabled as there may
      // be multiple webinar dates.
      $node = $this->loadWebinar($this->data['webinarKey']);
      return $node->delete();
    }
    elseif ($this->data['status'] == 'NEW' && !empty($this->config->get('group_webinars'))) {
      if ($this->loadWebinar($this->data['webinarKey'])) {
        // Exit early if a webinar was already created for this key.
        return TRUE;
      }

      $node = $this->loadWebinarByTitle($this->data['webinarTitle']);
      if (!empty($node)) {
        $this->setWebinarFields($node, TRUE, TRUE);
        return $node->save();
      }
      else {
        return $this->createWebinar();
      }
    }
    elseif ($this->data['status'] != 'DELETED') {
      $node = $this->loadWebinar($this->data['webinarKey']);
      if (!empty($node)) {
        $this->setWebinarFields($node, FALSE);
        return $node->save();
      }
      else {
        return $this->createWebinar();
      }
    }
  }

  /**
   * Creates a webinar from webhook data.
   */
  private function createWebinar() {
    $node = Node::create(['type' => $this->config->get('sync_content_type')]);
    $this->setWebinarFields($node, TRUE);

    $node->status = 0;
    if ($this->config->get('published')) {
      $node->status = 1;
    }

    $node->enforceIsNew();
    return $node->save();
  }

  /**
   * Sets the webinar fields on the node entity.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node used to store the webinar data.
   * @param bool $new
   *   True if the webinar is being created.
   * @param bool $group
   *   True if the webinar should be updated with the fields grouped together.
   */
  private function setWebinarFields(Node $node, bool $new, bool $group = FALSE) {
    if ($new) {
      if (!empty($this->config->get('webinar_key'))) {
        if ($group) {
          $node->get($this->config->get('webinar_key'))->appendItem($this->data['webinarKey']);
        }
        else {
          $node->set($this->config->get('webinar_key'), $this->data['webinarKey']);
        }
      }
      if (!empty($this->config->get('webinar_date')) &&
          !empty($this->data['times'][0]['startTime']) &&
          !empty($this->data['times'][0]['endTime'])) {
        $timezone = 'UTC';
        $datetime = [
          'value' => $this->formatDateTime($this->data['times'][0]['startTime'], $timezone),
          'end_value' => $this->formatDateTime($this->data['times'][0]['endTime'], $timezone),
        ];
        if ($group) {
          $node->get($this->config->get('webinar_date'))->appendItem($datetime);
        }
        else {
          $node->set($this->config->get('webinar_date'), $datetime);
        }

      }
    }

    if (!empty($this->config->get('webinar_title'))) {
      $node->set($this->config->get('webinar_title'), $this->data['webinarTitle']);
    }
    if (!empty($this->config->get('webinar_key')) && !empty($this->config->get('webinar_url'))) {
      $webinar_url = $this->registerLink . $this->data['webinarKey'];
      $node->set($this->config->get('webinar_url'), $webinar_url);
    }
    if (!empty($this->config->get('webinar_description') && !empty($this->data['description']))) {
      $node->set($this->config->get('webinar_description'), $this->data['description']);
    }
    if (!empty($this->config->get('experience_type'))) {
      $node->set($this->config->get('experience_type'), $this->data['experienceType']);
    }
    if (!empty($this->config->get('recurrence_type'))) {
      $node->set($this->config->get('recurrence_type'), $this->data['recurrenceType']);
    }
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

  /**
   * Retrieves the existing webinar entity.
   *
   * @return \Drupal\node\Entity\Node
   *   Returns the node entity representing the webinar or FALSE.
   */
  private function loadWebinar($webinar_key) {
    $result = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $this->config->get('sync_content_type'))
      ->condition($this->config->get('webinar_key'), $webinar_key)
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($result);

    if (!empty($nodes)) {
      return reset($nodes);
    }
    return FALSE;
  }

  /**
   * Looks for an existing webinar entity by title.
   *
   * @return \Drupal\node\Entity\Node
   *   Returns the node entity representing the webinar or FALSE.
   */
  private function loadWebinarByTitle($title) {
    $result = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $this->config->get('sync_content_type'))
      ->condition('title', $title)
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($result);

    if (!empty($nodes)) {
      return reset($nodes);
    }
    return FALSE;
  }

  /**
   * Formats the date sent through the webhook into the correct format.
   *
   * @param string $webhook_datetime
   *   The datetime string sent from the webhook.
   * @param string $timezone
   *   The timezone of the datetime.
   *
   * @return string
   *   The correctly formatted date string.
   */
  private function formatDateTime($webhook_datetime, $timezone) {
    $date = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s\Z', $webhook_datetime, $timezone);
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

}
