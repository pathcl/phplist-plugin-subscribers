<?php
/**
 * SubscribersPlugin for phplist
 * 
 * This file is a part of SubscribersPlugin.
 *
 * SubscribersPlugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * SubscribersPlugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * @category  phplist
 * @package   SubscribersPlugin
 * @author    Duncan Cameron
 * @copyright 2011-2013 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

/**
 * DAO class that provides access to the user, user_attribute and related tables
 * 
 * @category  phplist
 * @package   SubscribersPlugin
 */
class SubscribersPlugin_DAO_User extends CommonPlugin_DAO {

    /**
     * Generates a WHERE expression for the user belonging to the specified list and 
     * optionally the list owned by the specified owner
     * @param int $listID 
     * @param string $loginid
     * @return string WHERE expression
     * @access private
     */
    private function list_exists($listID, $loginid)
    {
        if ($listID || $loginid) {
            $owner = $loginid ? "AND l.owner = $loginid" : '';
            $list = $listID ? "AND l.id = $listID" : '';
            $where = 
                "EXISTS (
                    SELECT 1 from {$this->tables['listuser']} lu, {$this->tables['list']} l
                    WHERE u.id = lu.userid AND lu.listid = l.id $list $owner
                )";
        } else {
            $where = '';
        }
        return $where;
    }

    /**
     * Generates a list of join expressions for the FROM table references and a list of attribute fields for the SELECT expression
     * @param array $attributes 
     * @param string $searchTerm optional attribute value to be matched
     * @param int $searchAttr optional attribute id to be matched
     * @return string WHERE expression
     * @access private
     */
    private function userAttributeJoin($attributes, $searchTerm, $searchAttr)
    {
        $searchTerm = sql_escape($searchTerm);
        $attr_fields = '';
        $attr_join = '';

        foreach ($attributes as $attr) {
            $id = $attr['id'];
            $tableName = $this->table_prefix . 'listattr_' . $attr['tablename'];

            $joinType = ($searchTerm && $searchAttr == $id) ? 'JOIN' : 'LEFT JOIN';
            $thisJoin = "
                $joinType {$this->tables['user_attribute']} ua{$id} 
                ON ua{$id}.userid = u.id AND ua{$id}.attributeid = {$id} ";
            
            switch ($attr['type']) {
            case 'radio':
            case 'select':
                $thisJoin .= "
                    $joinType {$tableName} la{$id} ON la{$id}.id = ua{$id}.value ";
                
                if ($searchTerm && $searchAttr == $id) {
                    $thisJoin .= "AND la{$id}.name LIKE '%$searchTerm%' ";
                }
                $attr_fields .= ", la{$id}.name as attr{$id}";
                break;
            default:
                if ($searchTerm && $searchAttr == $id) {
                    $thisJoin .= "AND ua{$id}.value LIKE '%$searchTerm%' ";
                }
                $attr_fields .= ", ua{$id}.value as attr{$id}";
                break;
            }
            $attr_join .= $thisJoin;
        }
        return array($attr_join, $attr_fields);
    }

    public function users($listID, $owner, $attributes, $searchTerm, $searchAttr,
        $unconfirmed = 0, $blacklisted = 0,    $start = null, $limit = null)
    {
        /*
         * 
         */
        list($attr_join, $attr_fields) = $this->userAttributeJoin($attributes, $searchTerm, $searchAttr);
        $limitClause = is_null($start) ? '' : "LIMIT $start, $limit";
        $w = array();

        if ($le = $this->list_exists($listID, $owner))
            $w[] = $le;

        if ($unconfirmed)
            $w[] = 'u.confirmed = 0';

        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $bl_join = $blacklisted ? '' : 'LEFT ';
        $bl_join .= "JOIN {$this->tables['user_blacklist']} ub ON u.email = ub.email";

        $sql = "SELECT u.id, u.email, u.confirmed, u.blacklisted, u.htmlemail, ub.email as ub_email $attr_fields,
            (SELECT count(lu.listid) FROM {$this->tables['listuser']} lu WHERE lu.userid = u.id) AS lists
            FROM {$this->tables['user']} u
            $attr_join
            $bl_join
            $where
            ORDER by u.id
            $limitClause";
        return $this->dbCommand->queryAll($sql);
    }

    public function totalUsers($listID, $owner, $attributes, $searchTerm, $searchAttr, $unconfirmed = 0, $blacklisted = 0)
    {
        if ($searchTerm) {
            list($attr_join) = $this->userAttributeJoin($attributes, $searchTerm, $searchAttr);
        } else {
            $attr_join = '';
        }
        $w = array();

        if ($le = $this->list_exists($listID, $owner))
            $w[] = $le;

        if ($unconfirmed)
            $w[] = 'u.confirmed = 0';

        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $bl_join = $blacklisted ? '' : 'LEFT ';
        $bl_join .= "JOIN {$this->tables['user_blacklist']} ub ON u.email = ub.email";

        $sql = "SELECT count(*) as t 
            FROM {$this->tables['user']} u
            $attr_join
            $bl_join
            $where";
        return $this->dbCommand->queryOne($sql, 't');
    }
}