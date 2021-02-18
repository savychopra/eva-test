<?php

namespace Drupal\adva;

use Drupal\adva\Plugin\adva\Manager\AccessConsumerManagerInterface;
use Drupal\adva\Plugin\adva\OverridingAccessConsumerInterface;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a storage handler for the advanced access grants system.
 *
 * This service stores access requirements in the database and is used to check
 * entity access by querying these records during access checks by performed by
 * AdvancedAccessEntityAccessControlHandler instances.
 *
 * @ingroup adva
 */
class AccessStorage implements AccessStorageInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Advanced Access Table name.
   *
   * @var string
   */
  const TABLE_NAME = "adva_access";

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The plugin manager for access consumer plugins.
   *
   * @var \Drupal\adva\Plugin\adva\Manager\AccessConsumerManagerInterface
   */
  protected $consumerManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a AccessStorage object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\adva\Plugin\adva\Manager\AccessConsumerManagerInterface $consumer_manager
   *   The plugin manager for access consumer plugins.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type manager to get entity storage.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(Connection $database, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager, AccessConsumerManagerInterface $consumer_manager, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->database = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->consumerManager = $consumer_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account) {

    // Grants only support these operations.
    if (!in_array($operation, ['view', 'update', 'delete'])) {
      return AccessResult::neutral();
    }
    $entityType = $entity->getEntityType();
    $entity_type_id = $entityType->id();

    if ($this->hasGlobalBypassPermission($account) && $this->hasEntityTypeBypassPermission($account, $entity_type_id)) {
      return AccessResult::allowed();
    }

    $consumer = $this->consumerManager->getConsumerForEntityType($entityType);
    // If providers are configured for the entity's consumer.
    if (empty($consumer->getAccessProviders())) {
      // Return the equivalent of the default grant, defined by
      // self::saveDefaultGrant().
      if ($operation === 'view') {
        return AccessResult::allowed()->addCacheableDependency($entity);
      }
      else {
        return AccessResult::neutral();
      }
    }

    // Check the database for potential access grants.
    $query = $this->database->select(static::TABLE_NAME, 'base');
    $query->addExpression('1');
    // Only interested for granting of the current operation.
    $query->where('grant_' . $operation . '>= 1');
    $grants = $this->getUserGrants($consumer, $operation, $account);

    // Return rows for the grants the user has.
    $cond = new Condition('OR');
    if (count($grants)) {
      // Check for grants for this node and the correct langcode.
      $cond->condition(
        $query->andConditionGroup()
          ->condition('base.entity_id', $entity->id())
          ->condition('base.entity_type', $entity_type_id)
          ->condition('langcode', $entity->language()->getId())
          ->condition(static::buildAccessCondition($grants))
      );
    }
    // If there are no grants, we want to return the default (`all`) row.
    $cond->condition(static::defaultCondition($entity));
    $query->condition($cond);
    $query->range(0, 1);

    if ($query->execute()->fetchField()) {
      $access_result = AccessResult::allowed();
    }
    else {
      $access_result = AccessResult::neutral();
    }

    return $access_result;
  }

  /**
   * {@inheritdoc}
   */
  public function checkAll($entity_type_id, AccountInterface $account) {
    $query = $this
      ->database
      ->select('adva_access');
    $query
      ->addExpression('COUNT(*)');
    $query
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', 0)
      ->condition('grant_view', 1, '>=');

    $grants = static::buildGrantsCondition($this
      ->consumerManager
      ->getConsumerForEntityTypeId($entity_type_id)
      ->getAccessGrants('view', $account)
    );

    if (count($grants) > 0) {
      $query->condition($grants);
    }
    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function hasGlobalBypassPermission(AccountInterface $account) {
    return $account->hasPermission('bypass adva access');
  }

  /**
   * {@inheritdoc}
   */
  public function hasEntityTypeBypassPermission(AccountInterface $account, $entity_type_id) {
    return $account->hasPermission('bypass adva ' . $entity_type_id . ' access');
  }

  /**
   * {@inheritdoc}
   */
  public function clearRecords($entity_type_id) {
    $this->deleteRecords($entity_type_id);
    $this->saveDefaultGrant($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRecords($entity_type_id) {
    $query = $this->database
      ->delete(static::TABLE_NAME)
      ->condition('entity_type', $entity_type_id);
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function saveDefaultGrant($entity_type_id) {
    $this->database
      ->insert(static::TABLE_NAME)
      ->fields([
        'entity_id',
        'entity_type',
        'langcode',
        'fallback',
        'realm',
        'gid',
        'grant_view',
        'grant_update',
        'grant_delete',
      ])
      ->values([
        "entity_id" => 0,
        "entity_type" => $entity_type_id,
        "langcode" => 'und',
        "fallback" => 1,
        "realm" => 'all',
        "gid" => 0,
        "grant_view" => 1,
        "grant_update" => 1,
        "grant_delete" => 1,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultCondition(EntityInterface $entity) {
    $cond = new Condition("AND");

    $query = $this->database->select(static::TABLE_NAME, 'base');
    $query->addExpression('1');
    $query->condition('base.entity_id', $entity->id());
    $query->condition('base.entity_type', $entity->getEntityType()->id());

    $cond->notExists($query);
    $cond->where('base.entity_id = 0');

    return $cond;
  }

  /**
   * {@inheritdoc}
   */
  public function buildAccessCondition(array $grants) {
    $cond = new Condition("AND");

    $query = $this->database->select(static::TABLE_NAME, 'adva_access');
    $query->addField('adva_access', 'entity_id');
    $query->where('adva_access.entity_id = base.entity_id');
    $query->where('adva_access.entity_type = base.entity_type');
    $query->condition(static::buildGrantsCondition($grants));

    $cond->exists($query);

    return $cond;
  }

  /**
   * Creates a query condition from an array of advanced access grants.
   *
   * @param array $grants
   *   An array of grants, as returned by
   *   \Drupal\adva\Plugin\adva\AccessConsumerInterface::getAccessGrants().
   *
   * @return \Drupal\Core\Database\Query\Condition
   *   A condition object to be passed to $query->condition().
   *
   * @see \Drupal\adva\Plugin\adva\AccessConsumerInterface::getAccessGrants()
   */
  protected static function buildGrantsCondition(array $grants) {
    $cond = new Condition("OR");
    foreach ($grants as $realm => $gids) {
      if (!empty($gids)) {
        $and = new Condition('AND');
        $cond->condition($and
          ->condition('gid', $gids, 'IN')
          ->condition('realm', $realm)
        );
      }
    }
    return $cond;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild(OverridingAccessConsumerInterface $consumer, $batch_mode = FALSE) {

    $entity_type_id = $consumer->getEntityTypeId();
    $entityType = $this->entityTypeManager->getDefinition($entity_type_id);

    $context = [
      "%entityType" => $entityType->getLabel(),
    ];
    if ($batch_mode) {
      $batch = [
        'title' => $this->t('Rebuilding %entityType Access Permissions'),
        'operations' => [
          ['_adva_rebuild_access_batch_operation', [$entity_type_id]],
        ],
        'finished' => '_adva_rebuild_access_batch_finished',
      ];
      batch_set($batch);
    }
    else {
      // Try to allocate enough time to rebuild node grants.
      Environment::setTimeLimit(240);

      $entityStorage = $this->entityTypeManager->getStorage($entity_type_id);
      $this->clearRecords($entity_type_id);

      $entity_query = $entityStorage->getQuery();
      $entity_query->sort('entity_id', 'DESC');

      // Disable access checking since all entries must be processed even if the
      // user does not have access.
      $entity_query->accessCheck(FALSE);
      $ids = $entity_query->execute();

      $entityStorage->resetCache($ids);
      $entites = $entityStorage->loadMultiple($ids);

      foreach ($entites as $entity) {
        $this->updateRecordsFor($consumer, $entity);
      }
    }

    if (!isset($batch)) {
      $this->messenger->addMessage($this->t('%entityType access permissions have been rebuilt.', $context));
      $consumer->rebuildRequired(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateRecordsFor(OverridingAccessConsumerInterface $consumer, EntityInterface $entity) {
    $this->saveRecords($consumer, $entity, $this->getRecordsFor($consumer, $entity));
  }

  /**
   * {@inheritdoc}
   */
  public function saveRecords(OverridingAccessConsumerInterface $consumer, EntityInterface $entity, array $grants, $delete = TRUE) {
    if ($delete) {
      $this->deleteRecordsFor($entity);
    }

    $providers = $consumer->getAccessProviders();
    // Only save grants, if there is a module providing them.
    if (!empty($grants) && count($providers)) {
      $query = $this->database
        ->insert(static::TABLE_NAME)
        ->fields([
          'entity_id',
          'entity_type',
          'langcode',
          'fallback',
          'realm',
          'gid',
          'grant_view',
          'grant_update',
          'grant_delete',
        ]);

      // If we have defined a granted langcode, use it.
      foreach ($grants as $grant) {
        // Only write grants; denies are implicit.
        if ($grant['grant_view'] || $grant['grant_update'] || $grant['grant_delete']) {
          $grant['entity_id'] = $entity->id();
          $grant['entity_type'] = $entity->getEntityType()->id();

          // If a langcode is set, use it.
          if (isset($grant['langcode'])) {
            $grant_languages = [$grant['langcode']];
          }
          // If not, add a grant for every translation of this entity.
          else {
            $grant_languages = array_keys($entity->getTranslationLanguages(TRUE));
          }

          // For each language, add a record.
          foreach ($grant_languages as $grant_langcode) {
            $grant['langcode'] = $grant_langcode;
            // The record with the original langcode is used as the fallback.
            if ($grant['langcode'] == $entity->language()->getId()) {
              $grant['fallback'] = 1;
            }
            else {
              $grant['fallback'] = 0;
            }
            $query->values($grant);
          }
        }
      }
      $query->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRecordsFor(OverridingAccessConsumerInterface $consumer, EntityInterface $entity) {
    $providers = $consumer->getAccessProviders();
    if (!empty($providers)) {
      return $consumer->getAccessRecords($entity);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserGrants(OverridingAccessConsumerInterface $consumer, $operation, AccountInterface $account) {
    $providers = $consumer->getAccessProviders();
    if (!empty($providers)) {
      return $consumer->getAccessGrants($operation, $account);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function count($entity_type_id) {
    $result = $this->database
      ->select(static::TABLE_NAME, "base")
      ->condition('base.entity_type', $entity_type_id)
      ->countQuery()->execute()->fetchField();
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRecordsFor(EntityInterface $entity) {
    $query = $this->database
      ->delete(static::TABLE_NAME)
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityType()->id());
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function alterQuery($query, array $tables, $op, AccountInterface $account, $base_table, $entity_type_id) {
    // Only the view, update and delete operations are supported.
    if (!in_array($op, ['view', 'update', 'delete'])) {
      throw new \InvalidArgumentException($op . ' is not a valid operation.');
    }

    if (!$langcode = $query->getMetaData('langcode')) {
      $langcode = FALSE;
    }

    // Find all instances of the base table being joined -- could appear
    // more than once in the query, and could be aliased. Join each one to
    // the adva_access table.
    foreach ($tables as $alias => $tableinfo) {
      $table = $tableinfo['table'];
      if (!($table instanceof SelectInterface) && $table == $base_table) {
        // Set the subquery.
        $subquery = $this
          ->database
          ->select('adva_access', 'a')
          ->fields('a', ['entity_id'])
          ->condition('a.entity_type', $entity_type_id)
          ->condition("a.grant_$op", 1, '>=')
          ->groupBy('a.entity_id');

        $grants = $this
          ->consumerManager
          ->getConsumerForEntityTypeId($entity_type_id)
          ->getAccessGrants($op, $account);

        // If the user has grants, add conditions for them.
        if (!empty($grants)) {
          $subquery->condition(static::buildGrantsCondition($grants));
        }
        // If the user has no grants, force an empty result set to prevent
        // joining on any access records.
        else {
          $subquery->where('FALSE');
        }

        // Add langcode-based filtering if this is a multilingual site.
        if ($this->languageManager->isMultilingual()) {
          // If no specific langcode to check for is given, use the grant entry
          // which is set as a fallback.
          // If a specific langcode is given, use the grant entry for it.
          if ($langcode === FALSE) {
            $subquery->condition('a.fallback', 1);
          }
          else {
            $subquery->condition('a.langcode', $langcode);
          }
        }

        $entity_type = $this
          ->entityTypeManager
          ->getDefinition($entity_type_id);
        $id_key = $entity_type->getKey('id');

        // Join to the Advanced Access table.
        $query->join($subquery, NULL, "%alias.entity_id = $alias.$id_key");
      }
    }
  }

}
