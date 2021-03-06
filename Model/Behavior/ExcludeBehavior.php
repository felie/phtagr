<?php
/**
 * PHP versions 5
 *
 * phTagr : Tag, Browse, and Share Your Photos.
 * Copyright 2006-2012, Sebastian Felis (sebastian@phtagr.org)
 *
 * Licensed under The GPL-2.0 License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2012, Sebastian Felis (sebastian@phtagr.org)
 * @link          http://www.phtagr.org phTagr
 * @package       Phtagr
 * @since         phTagr 2.2b3
 * @license       GPL-2.0 (http://www.opensource.org/licenses/GPL-2.0)
 */

class ExcludeBehavior extends ModelBehavior {

  function setup(&$Model, $settings = array()) {
    if (!isset($this->settings[$Model->alias])) {
      $this->settings[$Model->alias] = array();
    }
    if (!is_array($settings)) {
      $settings = array();
    }
    $this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $settings);
  }

  /**
   * Finds the model binding type between to models
   *
   * @param Model current model object
   * @param name Name of binding
   * @result Type of the binding to the current model. Return value is one of
   * 'hasOne', 'belongsTo', 'hasMany', 'hasAndBelongsToMany', or false on error.
   */
  function _findBindingType(&$Model, $name) {
    if (isset($Model->hasAndBelongsToMany[$name])) {
      return 'hasAndBelongsToMany';
    } elseif (isset($Model->hasMany[$name])) {
      return 'hasMany';
    } elseif (isset($Model->belongsTo[$name])) {
      return 'belongsTo';
    } elseif (isset($Model->hasOne[$name])) {
      return 'hasOne';
    }
    return false;
  }

  /**
   * Rename 'Group' alias to 'Groub' alias. This is required because
   * Cake does not escape the Group word.
   *
   * @param array $conditions
   * @return array
   */
  function renameGroupAlias($conditions) {
    $result = array();
    foreach ($conditions as $key => $condition) {
      if ($key === 'OR' || $key === 'AND') {
        $result[$key] = $this->escapeModelFields($condition);
      } else if (preg_match('/^Group\.(.*)$/', $condition, $m)) {
        $result[$key] = "Groub.{$m[1]}";
      } else {
        $result[$key] = $condition;
      }
    }
    return $result;
  }

  /**
   * Serialize condition to string for join statement
   *
   * @param $conditions
   * @param String $op Operand for condition.
   * @return String
   */
  function _serializeConditions($conditions, $op) {
    $result = array();
    foreach ($conditions as $key => $condition) {
      if ($key === 'AND' || $key === 'OR') {
        $result[] = $this->_serializeConditions($condition, $key);
      } else if (is_array($condition)) {
        $result[] = $this->_serializeConditions($condition, $op);
      } else if (!is_numeric($key)) {
        $result[] = $key . $condition;
      } else {
        $result[] = $condition;
      }
    }
    if (!count($result)) {
      return "";
    } else if (count($result) == 1) {
      return array_pop($result);
    } else {
      return "(" . join(" $op ", $result) . ")";
    }
  }

  /**
   * Build SQL joins for hasAndBelongsToMany relations
   *
   * @param Model current model object
   * @param query query array
   * @param joinConditions Join conditions for HABTM bindings
   * @param joinType Type of SQL join (INNER, LEFT, RIGHT)
   */
  function _buildHasAndBelongsToManyJoins(&$Model, &$query, $joinConditions, $options = array()) {
    $options = am(array('type' => false, 'count' => true), $options);

    $options['type'] = strtoupper($options['type']);
    if (!in_array($options['type'], array('', 'RIGHT', 'LEFT'))) {
      Logger::warn("Invalid join type: ".$options['type']);
      $options['type'] = '';
    }
    foreach ($joinConditions as $name => $queryConditions) {
      $config = $Model->hasAndBelongsToMany[$name];
      //Logger::trace($config);
      extract($config);

      $alias = $Model->{$name}->alias;
      if ($alias == 'Group') {
        $alias = 'Groub';
        $queryConditions = $this->renameGroupAlias($queryConditions);
      }
      $table = $Model->{$name}->tablePrefix.$Model->{$name}->table;

      $join = "{$options['type']} JOIN ( SELECT $with.$foreignKey";
      if ($options['count']) {
        $count = Inflector::camelize($name).'Count';
        $join .= ", COUNT($with.$foreignKey) AS $count";
        if (!isset($query['_counts'])) {
          $query['_counts'] = array();
        }
        if (!in_array($count, $query['_counts'])) {
          $query['_counts'][] = $count;
        }
      }
      $join .= " FROM {$Model->tablePrefix}$joinTable AS $with, $table AS $alias";
      $join .= " WHERE $with.$associationForeignKey = $alias.id";
      $join .=   " AND ".$this->_serializeConditions($queryConditions, 'OR');
      $join .= " GROUP BY $with.$foreignKey ";
      $join .= ") AS $with ON {$Model->alias}.id = $with.$foreignKey";
      $query['joins'][] = $join;
    }
    //Logger::debug($query);
  }

  /**
   * Build SQL joins for hasMany relations
   *
   * @param Model current model object
   * @param query current query array
   * @param joinConditions Conditions
   * @param options Options
   */
  function _buildHasManyJoins(&$Model, &$query, &$joinConditions, $options = array()) {
    $options = am(array('type' => false, 'count' => true), $options);
    $options['type'] = strtoupper($options['type']);
    if (!in_array($options['type'], array(false, 'RIGHT', 'LEFT'))) {
      Logger::warn("Invalid join type: ".$options['type']);
      $options['type'] = '';
    }
    foreach ($joinConditions as $name => $queryConditions) {
      $config = $Model->hasMany[$name];
      //Logger::trace($config);

      $alias = $Model->{$name}->alias;
      if ($alias == 'Group') {
        $alias = 'Groub';
        $queryConditions = $this->renameGroupAlias($queryConditions);
      }
      $table = $Model->{$name}->tablePrefix.$Model->{$name}->table;
      $foreignKey = $config['foreignKey'];

      $join = "{$options['type']} JOIN ( SELECT $alias.$foreignKey";
      if ($options['count']) {
        $count = Inflector::camelize($name).'Count';
        $join .= ", COUNT($alias.id) AS $count";
        if (!isset($query['_counts'])) {
          $query['_counts'] = array();
        }
        if (!in_array($count, $query['_counts'])) {
          $query['_counts'][] = $count;
        }
      }
      $join .= " FROM $table AS $alias";
      $join .= " WHERE ".implode(" OR ", $queryConditions);
      $join .= " GROUP BY $alias.$foreignKey ";
      $join .= ") AS $alias ON {$Model->alias}.id = $alias.$foreignKey";
      $query['joins'][] = $join;
    }
  }

  /**
   * Extract model name from condition
   *
   * @param $condition
   * @return String Name of model
   */
  function _findModelName($condition) {
    if (is_string($condition)) {
      return $condition;
    } else if (is_array($condition) && isset($condition['AND'])) {
      return array_pop($condition['AND']);
    } else {
      return false;
    }
  }

  /**
   * Extracts conditions for hasAndBelongsToMany and hasMany relations and build
   * joins for these relations.
   *
   * @param Model current model object
   * @param query query array
   * @param optionscondition Join options
   */
  function _buildJoins(&$Model, &$query, $options = array()) {
    $joinConditions = array();
    if (empty($query['conditions'])) {
      return;
    }
    if (!is_array($query['conditions'])) {
      $conditions = array($query['conditions']);
    } else {
      $conditions =& $query['conditions'];
    }
    //Logger::debug($conditions);
    foreach ($conditions as $key => $condition) {
      $name = $this->_findModelName($condition);
      if (!$name) {
        continue;
      }
      // Match 'Model.field'
      if (!preg_match('/^(\w+)\./', $name, $matches)) {
        continue;
      }
      $name = $matches[1];
      if ($name == $Model->alias) {
        continue;
      }
      $type = $this->_findBindingType($Model, $name);
      if (!$type) {
        Logger::warn("Model binding not found for {$Model->alias}->$name for condition {$condition}");
        continue;
      }
      if ($type == 'hasOne' || $type == 'belongsTo') {
        continue;
      }
      if (!is_array($query['conditions'])) {
        unset($query['conditions']);
      } else  {
        unset($conditions[$key]);
      }
      $joinConditions[$type][$name][] = $condition;
    }
    //Logger::debug($joinConditions);
    if (isset($joinConditions['hasAndBelongsToMany'])) {
      $this->_buildHasAndBelongsToManyJoins($Model, &$query, $joinConditions['hasAndBelongsToMany'], $options);
    }
    if (isset($joinConditions['hasMany'])) {
      $this->_buildHasManyJoins($Model, &$query, $joinConditions['hasMany'], $options);
    }
    return $query;
  }

  /**
   * Build the exclusion statement of a query array
   *
   * @param Model current model object
   * @param query query array
   * @return SQL exclusion condition
   */
  function _buildExclusion(&$Model, $query) {
    $query = am(array('joins' => array()), $query);
    //Logger::debug($query);
    $this->_buildJoins($Model, &$query, array('count' => true, 'type' => 'LEFT'));
    //Logger::debug($query);
    $exclusion = " {$Model->alias}.id NOT IN (";
    $exclusion .= " SELECT {$Model->alias}.id";
    $exclusion .= " FROM {$Model->tablePrefix}{$Model->table} AS {$Model->alias} ";
    $exclusion .= implode(' ', $query['joins']);

    // build condition for outer join
    if (count($query['_counts'])) {
      $counts = array();
      foreach ($query['_counts'] as $count) {
        $counts[] = "COALESCE($count, 0) > 0";
      }
      $condition = '( '.join(' OR ', $counts).' )';
      $query['conditions'][] = $condition;
    }
    if (count($query['conditions']) == 0) {
      $query['conditions'][] = '1 = 1';
    }
    $exclusion .= " WHERE ".implode(' AND ', $query['conditions']);

    $exclusion .= ")";
    return $exclusion;
  }

  function beforeFind(&$Model, $query) {
    $exclude = false;
    if (isset($query['conditions']['exclude']) &&
      is_array($query['conditions']['exclude'])) {
      $exclude = $query['conditions']['exclude'];
      unset($query['conditions']['exclude']);
    } elseif (isset($query['exclude'])) {
      $exclude = $query['exclude'];
    }
    $this->_buildJoins($Model, &$query, array('type' => 'LEFT'));
    if ($exclude) {
      $query['conditions'][] = $this->_buildExclusion($Model, $exclude);
    }
    return $query;
  }

}
?>
