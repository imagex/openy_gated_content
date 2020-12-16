<?php

namespace Drupal\openy_gated_content;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Class Edit User Account Access Check.
 *
 * @package Drupal\openy_gated_content
 */
class EditUserAccountAccessCheck implements AccessInterface {

  /**
   * Checks access for editing user account.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account that is to be edited.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account that is from the current session.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $user, AccountInterface $account) {
    $roles = $account->getRoles();
    foreach ($roles as $role) {
      if (strpos($role, 'virtual_y_') !== FALSE || $role == 'virtual_y') {
        return AccessResult::forbidden()->cachePerUser();
      }
    }
    return AccessResult::allowedIf($user->id() === $account->id()
      || $account->hasPermission('administer users'));
  }

}
