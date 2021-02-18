<?php

namespace Drupal\adva;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines AdvancedAccessEntityAccessControlHandlerInterface instance.
 */
interface AdvancedAccessEntityAccessControlHandlerInterface extends EntityHandlerInterface {

  /**
   * Determines if the user has a global viewing grant for all entities.
   *
   * Checks to see whether any module grants global 'view' access to a user
   * account; global 'view' access is encoded in the {adva_access} table as a
   * grant with entity_id=0.
   *
   * This function is called when a entity listing query is tagged with
   * 'ENTITY_TYPE_access'; when this function returns TRUE, no entity type
   * access joins are added to the query.
   *
   * @param string $entity_type_id
   *   The id of the entity type to check grants for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user object for the user whose access is being checked. If
   *   omitted, the current user is used. Defaults to NULL.
   *
   * @return bool
   *   TRUE if 'view' access to all entities is granted, FALSE otherwise.
   *
   * @see adva_query_alter()
   */
  public function checkAllGrants($entity_type_id, AccountInterface $account = NULL);

}
