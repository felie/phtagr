<?php
/*
 * phtagr.
 * 
 * social photo gallery for your community.
 * 
 * Copyright (C) 2006-2010 Sebastian Felis, sebastian@phtagr.org
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2 of the 
 * License.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class User extends AppModel
{
  var $name = 'User';

  var $actsAs = array('Cipher' => array());

  var $hasMany = array(
                  'Group' => array('dependent' => true),
                  'Option' => array('dependent' => true, 'dependent' => true),
                  'Guest' => array('foreignKey' => 'creator_id', 'dependent' => true)
                  );

  var $hasAndBelongsToMany = array(
                  'Member' => array(
                      'className' => 'Group', 
                      'dependent' => true
                    )
                  );

  var $validate = array(
    'username' => array(
      'rule' => array('between', 3, 32),
      'message' => 'Username must be between 3 and 32 characters long.'),
    'password' => array(
      'rule' => array('between', 6, 20),
      'message' => 'Password must be between 6 and 20 characters long.'),
    'role' => array(
      'rule' => array('between', ROLE_GUEST, ROLE_ADMIN),
      'message' => 'Invalid role'),
    'email' => array(
      'rule' => array('email'),
      'message' => 'Email address is not valid'),
    'notify_interval' => array(
      'rule' => array('inList', array(0, 1800, 3600, 86400, 604800, 2592000)),
      'message' => 'Invalid notification interval')
    );
 
  function afterFind($result, $primary = false) {
    if ($primary && isset($result[0]['Option'])) {
      $result[0]['Option'] = $this->Option->addDefaults($result[0]['Option']);
    }
    return $result;
  }

  function __fromReadableSize($readable) {
    if (is_float($readable) || is_numeric($readable)) {
      return $readable;
    } elseif (preg_match_all('/^\s*(0|[1-9][0-9]*)(\.[0-9]+)?\s*([KkMmGg][Bb]?)?\s*$/', $readable, $matches, PREG_SET_ORDER)) {
      $matches = $matches[0];
      $size = (float)$matches[1];
      if (is_numeric($matches[2])) {
        $size += $matches[2];
      }
      if (is_string($matches[3])) {
        switch ($matches[3][0]) {
          case 'k':
          case 'K':
            $size = $size * 1024;
            break;
          case 'm':
          case 'M':
            $size = $size * 1024 * 1024;
            break;
          case 'g':
          case 'G':
            $size = $size * 1024 * 1024 * 1024;
            break;
          default:
            Logger::err("Unknown unit {$matches[3]}");
        }
      }
      if ($size < 0) {
        Logger::err("Size is negtive: $size");
        return 0;
      }
      return $size;
    } else {
      return 0;
    }
  }

  function beforeValidate() {
    if (isset($this->data['User']['password']) && 
      isset($this->data['User']['confirm'])) {
      if (empty($this->data['User']['password']) && 
        empty($this->data['User']['confirm'])) {
        // both are empty - clear it
        unset($this->data['User']['confirm']);
        unset($this->data['User']['password']);
      } elseif (empty($this->data['User']['password'])) {
        $this->invalidate('password', __('Password not given', true));
      } elseif (empty($this->data['User']['confirm'])) {
        $this->invalidate('confirm', __('Password confirmation is missing', true));
      } elseif ($this->data['User']['password'] != $this->data['User']['confirm']) {
        $this->invalidate('password', __('Password confirmation mismatch', true));
        $this->invalidate('confirm', __('Password confirmation mismatch', true));
      }
    }
    $id = false;
    if ($this->id) {
      $id = $this->id;
    } elseif (isset($this->data['User']['id'])) {
      $id = $this->data['User']['id'];
    }
    if (isset($this->data['User']['username']) && $id) {
      $other = $this->find('first', array('conditions' => array('User.username' => $this->data['User']['username']), 'recursive' => -1));
      if ($other && $other['User']['id'] != $id) {
        $this->invalidate('username', __('Username already taken', true));
      }
    }
    return true;
  }

  function beforeSave() {
    if (isset($this->data['User']['quota'])) {
      $this->data['User']['quota'] = $this->__fromReadableSize($this->data['User']['quota']);
    }

    if (empty($this->data['User']['expires'])) {
      $this->data['User']['expires'] = null;
    }
    
    return true;
  }

  function beforeDelete($cascade) {
    $id = $this->id;
    $this->bindModel(array('hasMany' => array('Media')));
    Logger::info("Delete all image database entries of user $id");
    $this->Media->deleteFromUser($id);

    $this->bindModel(array('hasMany' => array('MyFile')));
    $this->MyFile->deleteAll("File.user_id = $id");

    $dir = USER_DIR.$id;
    Logger::info("Delete user directory of user $id: $dir");
    $folder = new Folder();
    $folder->delete($dir);

    return true;
  }

  function writeSession($user, $session) {
    if (!$session || !isset($user['User']['id']) || !isset($user['User']['role']) || !isset($user['User']['username'])) {
      return;
    }
    $session->write('User.id', $user['User']['id']);
    $session->write('User.role', $user['User']['role']);
    $session->write('User.username', $user['User']['username']);
  }

  function hasAnyWithRole($role = ROLE_ADMIN) {
    $role = min(max(intval($role), ROLE_NOBODY), ROLE_ADMIN);
    return $this->hasAny("role >= $role");
  }

  function getNobody() {
    $nobody = array(
        'User' => array(
            'id' => -1, 
            'username' => '', 
            'role' => ROLE_NOBODY), 
        'Member' => array(),
        'Option' => $this->Option->addDefaults(array()));
    return $nobody;
  }

  function isExpired($data) {
    if (!isset($data['User']['expires']))
      return false;
    $now = time();
    $expires = strtotime($data['User']['expires']);
    if ($expires < $now)
      return true;
    return false;
  }

  /** Generate a keystring
    @param data User model data
    @param length Key length. Default is 10. Must be beween 3 and 128.
    @param alphabet Key alphabet as string. Default is [a-zA-Z0-9]. Must be at least 10 characters long.
    @return User model data */
  function generateKey($data, $length = 10, $alphabet = false) {
    srand(microtime(true)*1000);

    if (!$alphabet || strlen(strval($alphabet)) < 10) {
      $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $alphabet .= 'abcdefghijklmnopqrstuvwxyz';
      $alphabet .= '0123456789';
    }
    $length = min(128, max(3, intval($length)));
    $count = strlen($alphabet);

    $key = '';
    for ($i = 0; $i < $length; $i++) {
      $key .= substr($alphabet, rand(0, $count), 1);
    }
    $data['User']['key'] = $key;
    return $data;
  }

  function getRootDir($data) {
    if (!isset($data['User']['id'])) {
      Logger::err("Data does not contain user's id");
      return false;
    }

    $rootDir = USER_DIR.$data['User']['id'].DS.'files'.DS;
    $folder = new Folder();
    if (!$folder->create($rootDir)) {
      Logger::err("Could not create users root directory '$fileDir'");
      return false;
    }
    return $rootDir;
  }
  
  function allowWebdav($user) {
    if (isset($user['User']['quota']) && $user['User']['quota'] > 0) {
      return true;
    }
    return false;
  }

  function canUpload($user, $size) {
    $this->bindModel(array('hasMany' => array('Media')));
    $userId = intval($user['User']['id']);
    if ($userId < 1) {
      return false;
    }

    $current = $this->Media->countBytes($userId, false);
    $quota = $user['User']['quota'];
    if ($current + $size <= $quota) {
      return true;
    }

    return false;
  }
  
  /** Selects visible users for users profile 
    @param user Current user
    @param username Username to select. 
    @return Array of users model data. If username is set only one user model data */
  function findVisibleUsers($user, $username = false) {
    $conditions = array();
    $findType = 'all';
    $resusive = -1;
    if ($username) {
      $conditions['User.username'] = $username;
      $findType = 'first';
      $recursive = 2;
    }
    $joins = array();
    if ($user['User']['role'] < ROLE_ADMIN) {
      if ($user['User']['role'] < ROLE_GUEST) {
        $conditions['User.visible_level'] = 4;
      } else {
        $groupIds = Set::extract('/Member/id', $user);
        $conditions['OR'] = array(
          'User.visible_level >=' => 3,
          'AND' => array(
            'User.visible_level' => 2,
            'MemberUser.group_id' => $groupIds
          )
        );
        $prefix = $this->tablePrefix;
        $joins[] = "LEFT JOIN `{$prefix}groups_users` AS `MemberUser` ON `MemberUser`.`user_id` = `User`.`id`";
      }
    }
    return $this->find($findType, array('conditions' => $conditions, 'joins' => $joins, 'recusive' => $resusive, 'group' => 'User.id'));
  }
}
?>