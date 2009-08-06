<?php
/*
 * phtagr.
 * 
 * Multi-user image gallery.
 * 
 * Copyright (C) 2006-2009 Sebastian Felis, sebastian@phtagr.org
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

class ImageDataHelper extends AppHelper {
  var $helpers = array('time', 'ajax', 'html', 'form', 'query');

  function getimagesize($data, $size, $square=false) {
    if (!isset($data['Media']['width']) ||
      !isset($data['Media']['height']) ||
      !isset($data['Media']['orientation'])) {
      $result = array();
      $result[0] = 0;
      $result[1] = 0;
      $result[3] = "";
      return $result;
    }
    if ($square) {
      $width=$size;
      $height=$size;
    } else {
      $width=$data['Media']['width'];
      $height=$data['Media']['height'];
      if ($width > $size && $width>=$height) {
        $height=intval($size*($height/$width));
        $width=$size;
      } elseif ($height > $size && $height > $width) {
        $width=intval($size*($width/$height));
        $height=$size;
      }
    }
    $result = array();

    // Rotate the image according to the orientation
    $orientation = $data['Media']['orientation'];
    if ($orientation >= 5 && $orientation <= 8) {
      $result[0] = $height;
      $result[1] = $width;
    } else {
      $result[0] = $width;
      $result[1] = $height;
    }

    $result[3] = "height=\"{$result[1]}\" width=\"{$result[0]}\"";

    return $result;
  }
  
  function toUnix($data, $offset=0) {
    if (!isset($data['Media']['date']))
      return -1;

    $sec=$this->time->toUnix($data['Media']['date']);
    return $sec+$offset;
  }

  /** Returns an single icon of a acl */
  function _acl2icon($acl, $titlePrefix = '') {
    $t='';
    // Write access
    if (($acl & ACL_WRITE_MASK) == ACL_WRITE_META) {
      $t .= $this->html->image('icons/tag.png', array('alt' => 'm', 'title' => $titlePrefix.'edit the meta data'));
    } elseif (($acl & ACL_WRITE_MASK) == ACL_WRITE_TAG) {
      $t .= $this->html->image('icons/tag_blue.png', array('alt' => 't', 'title' => $titlePrefix.'edit the tags'));
    }

    // Read access
    if (($acl & ACL_READ_MASK) == ACL_READ_ORIGINAL) {
      $t .= $this->html->image('icons/disk.png', array('alt' => 'o', 'title' => $titlePrefix.'download this media'));
    } elseif (($acl & ACL_READ_MASK) == ACL_READ_PREVIEW) {
      $t .= $this->html->image('icons/picture.png', array('alt' => 'v', 'title' => $titlePrefix.'view this media'));
    }
    if ($t == '') {
      $t='-';
    }
    return $t;
  }

  /** Returns an text repesentation of the acl */
  function _acl2text($data) {
    //$output = $this->html->image('icons/user.png', array('alt' => 'groups', 'title' => "Access for group members")).': ';
    $output = '<span title="Access for group members">group</span>';
    if (isset($data['Group']['name'])) {
      $base = $this->query->getQuery();
      $name = $data['Group']['name'];
      $this->query->set('group', $data['Group']['id']);
      $output .= ' ('.$this->html->link($name, $this->query->getUri(), array('title' => "This media belongs to the group '$name'")).')';
      $this->query->setQuery($base);
    }
    $output .= ': ';
    $output .= $this->_acl2icon($data['Media']['gacl'], 'Group members can ').' ';

    //$output .= $this->html->image('icons/group.png', array('alt' => 'users', 'title' => "Access for users")).': ';
    $output .= '<span title="Access for users">users: </span> ';
    $output .= $this->_acl2icon($data['Media']['uacl'], 'Users can ').' ';

    //$output .= $this->html->image('icons/world.png', array('alt' => 'public', 'title' => "Public access")).': ';
    $output .= '<span title="Public access">public: </span> ';
    $output .= $this->_acl2icon($data['Media']['oacl'], 'The public can ');
    return $output;
  }

  function _metaDate($data) {
    $base = $this->query->getQuery();

    $id = "date-".$data['Media']['id'];
    $output = '<span onmouseover="toggleVisibility(\''.$id.'\', \'inline\');"';
    $output .= ' onmouseout="toggleVisibility(\''.$id.'\', \'inline\');">';

    $this->query->set('from', $this->toUnix(&$data, -3*60*60));
    $this->query->set('to', $this->toUnix(&$data, 3*60*60));
    $output .= $this->html->link($data['Media']['date'], $this->query->getUri());
    $output .= ' ';

    $output .= '<div style="display: none;" class="actionlist" id="'.$id.'">';
    $this->query->setQuery($base);
    $this->query->set('to', $this->toUnix(&$data));
    $this->query->set('sort', 'date');
    $icon = $this->html->image('icons/date_previous.png', array('alt' => '<', 'title' => "View media of previous dates"));
    $output .= $this->html->link($icon, $this->query->getUri(), array('escape' => false));

    $this->query->setQuery($base);
    $this->query->set('from', $this->toUnix(&$data, -12*60*60));
    $this->query->set('to', $this->toUnix(&$data, 12*60*60));
    $icon = $this->html->image('icons/calendar_view_day.png', array('alt' => 'd', 'title' => "View media of this day"));
    $output .= $this->html->link($icon, $this->query->getUri(), array('escape' => false));

    $this->query->set('from', $this->toUnix(&$data, -3.5*24*60*60));
    $this->query->set('to', $this->toUnix(&$data, 3.5*24*60*60));
    $icon = $this->html->image('icons/calendar_view_week.png', array('alt' => 'w', 'title' => "View media of this week"));
    $output .= $this->html->link($icon, $this->query->getUri(), array('escape' => false));

    $this->query->set('from', $this->toUnix(&$data, -15*24*60*60));
    $this->query->set('to', $this->toUnix(&$data, 15*24*60*60));
    $icon = $this->html->image('icons/calendar_view_month.png', array('alt' => 'm', 'title' => "View media of this month"));
    $output .= $this->html->link($icon, $this->query->getUri(), array('escape' => false));

    $this->query->setQuery($base);
    $this->query->set('from', $this->toUnix(&$data));
    $this->query->set('sort', '-date');
    $icon = $this->html->image('icons/date_next.png', array('alt' => '>', 'title' => "View media of next dates"));
    $output .= $this->html->link($icon, $this->query->getUri(), array('escape' => false));
    $output .= '</div></span>';

    $this->query->setQuery($base);
    return $output;
  }

  function _metaHabtm($data, $habtm) {
    if (!count($data[$habtm])) 
      return false;

    $base = $this->query->getQuery();
    $field = strtolower(Inflector::pluralize($habtm));
    $links = array();
    foreach ($data[$habtm] as $assoc) {
      $this->query->set($field, $assoc['name']);
      $links[] = $this->html->link($assoc['name'], $this->query->getUri());
    }
    $this->query->setQuery($base);
    return implode(', ', $links);
  }

  function _metaAccess($data) {
    $id = $data['Media']['id'];
    $output = '<div class="actionlist">';
    $output .= $this->_acl2text($data);

    $output .= '</div>';

    return $output;
  }

  function metaTable($data, $withMap = false) {
    $cells= array();
    if (!$data) 
      return $cells;

    $mediaId = $data['Media']['id'];

    $this->query->initialize();
    $tmpQuery = $this->query->getQuery();

    $userId = $this->query->get('user');
    $this->query->clear();
    if ($userId)
      $this->query->set('user', $userId);
    $base = $this->query->getQuery();

    $cells[] = array("Date:", $this->_metaDate(&$data));

    if (count($data['Tag'])) {
      $cells[] = array('Tags:', $this->_metaHabtm(&$data, 'Tag'));
    }
    if (count($data['Category'])) {
      $cells[] = array('Categories:', $this->_metaHabtm(&$data, 'Category'));
    }
    if (count($data['Location'])) {
      $cells[] = array('Locations:', $this->_metaHabtm(&$data, 'Location'));
    }

    if ($data['Media']['isOwner']) {
      $cells[] = array('Access:', $this->_metaAccess($data));
    }
    
    // Action list 
    $output = '';
    if ($data['Media']['canWriteTag']) {
      $output = $this->form->checkbox('selected][', array('value' => $mediaId, 'id' => 'select-'.$mediaId, 'onclick' => "selectMedia($mediaId);"));
    }

    if ($data['Media']['canWriteTag'])
      $output .= ' '.$this->ajax->link(
        $this->html->image('icons/tag_blue_edit.png', array('alt' => 'Edit tags', 'title' => 'Edit tags')), 
        '/explorer/editmeta/'.$mediaId, 
        array('update' => 'meta-'.$mediaId), null, false);
    if ($data['Media']['canReadOriginal']) {
      foreach ($data['File'] as $file) {
        $output .= ' '.$this->html->link(
          $this->html->image('icons/disk.png', 
            array('alt' => $file['file'], 'title' => 'Save file '.$file['file'])), 
          '/media/file/'.$file['id'].'/'.$file['file'], null, null, false);
      }
    }

    if ($withMap && isset($data['Media']['latitude']) && isset($data['Media']['longitude'])) {
      $output .= ' '.$this->html->link(
          $this->html->image('icons/map.png',
            array('alt' => 'Show location in a map', 'title' => 'Show location in a map')),
          '#',
          array('onclick' => sprintf('showMap(%d, %f,%f);return false;', $data['Media']['id'], $data['Media']['latitude'],$data['Media']['longitude'])),
          null, false);
    }
    
    if ($data['Media']['isOwner']) {
      $output .= ' '.$this->ajax->link(
        $this->html->image('icons/key.png', 
          array('alt' => 'Edit ACL', 'title' => 'Edit access rights')), 
        '/explorer/editacl/'.$mediaId, 
        array('update' => 'meta-'.$mediaId), null, false);
      if ($data['Media']['isDirty'])
        $output .= ' '.$this->ajax->link(
          $this->html->image('icons/database_refresh.png', 
            array('alt' => 'Synchronize db with image', 'title' => 'Synchronize meta data with the image')), 
          '/explorer/sync/'.$mediaId, 
          array('update' => 'meta-'.$mediaId), null, false);
    }

    if ($output) {
      $output = "<div class=\"actionlist\">$output</div>\n";
      $cells[] = array("Actions:", $output);
    }

    $this->query->setQuery($tmpQuery);
    return $cells;
  }

  function _getCurrentLevel($data, $flag, $mask) {
    $data = am(array('Media' => array('oacl' => 0, 'uacl' => 0, 'gacl' => 0)), $data);
    if (($data['Media']['oacl'] & $mask) >= $flag)
      return ACL_LEVEL_OTHER;
    if (($data['Media']['uacl'] & $mask) >= $flag)
      return ACL_LEVEL_USER;
    if (($data['Media']['gacl'] & $mask) >= $flag)
      return ACL_LEVEL_GROUP;
    return ACL_LEVEL_PRIVATE;
  }

  /**
    Creates a ACL select element
    @param fieldName fieldname of the select
    @param data If data is numeric, the value is handled as acl level. If data
    is an array, the data is assumed to be a image model data array. The level
    is extracted bz the flag and the mask.
    @param flag Bit flag of the acl (used for image data array)
    @param mask Bit mask of the acl (used for image data array)

  // 0=keep, 1=me only, 2=group, 3=user, 4=others
  */
  function acl2select($fieldName, $data, $flag=0, $mask=0, $options=null) {
    if (is_array($data))
      $level = $this->_getCurrentLevel(&$data, &$flag, &$mask);
    elseif (is_numeric($data))
      $level = $data;
    else
      $level = ACL_LEVEL_PRIVATE;

    // level check
    if ($level < ACL_LEVEL_KEEP|| $level > ACL_LEVEL_OTHER)
      $level = ACL_LEVEL_PRIVATE;

    //$this->log($data['Media']);
    //$this->log("level=$level, flag=$flag, mask=$mask");
    $acl = array(
      ACL_LEVEL_KEEP => 'Keep',
      ACL_LEVEL_PRIVATE => 'Me only',
      ACL_LEVEL_GROUP => 'Group members',
      ACL_LEVEL_USER => 'Users',
      ACL_LEVEL_OTHER => 'Everyone');
    $options = am($options, array('type' => 'select', 'options' => $acl, 'selected' => $level));
    //$this->log($options);
    return $this->form->input($fieldName, $options);
  }  

  /** Returns the visibility icon for the own media 
    @params media Media model data 
    @return Html output for the icon or false */
  function getVisibilityIcon(&$media) {
    $icon = false;
    if (isset($media['Media']['isOwner']) && $media['Media']['isOwner']) {
      switch ($media['Media']['visibility']) {
        case ACL_LEVEL_OTHER: 
          $icon = $this->html->image('icons/world.png', array('title' => 'This media is public visible'));;
          break;
        case ACL_LEVEL_USER: 
          $icon = $this->html->image('icons/group.png', array('title' => 'This media is visible for users'));;
          break;
        case ACL_LEVEL_GROUP: 
          $icon = $this->html->image('icons/user.png', array('title' => 'This media is visible for group members'));;
          break;
        default: 
          $icon = $this->html->image('icons/stop.png', array('title' => 'This media is private'));;
          break;
      }
    }
    return $icon;
  }

}
