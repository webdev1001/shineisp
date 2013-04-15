<?php

/**
 * ServersGroupsIndexes
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
class ServersGroupsIndexes extends BaseServersGroupsIndexes
{
	/**
     * deleteServers
     * Delete the list of attribute from a group
     * @param integer $groupid
     * @return boolean
     */
    public static function deleteServers($groupid, array $servers) {
    	return Doctrine_Query::create ()->delete ()->from ( 'ServersGroupsIndexes' )
    					->whereIn ( 'attribute_id', $servers )
    					->where('group_id = ?', $groupid)
    					->execute ();
    }
    
	/**
     * deleteAllAttributes
     * Delete the list of attribute from a group
     * @param integer $groupid
     * @return boolean
     */
    public static function deleteAllServers($groupid) {
    	return Doctrine_Query::create ()->delete ()->from ( 'ServersGroupsIndexes' )
    					->where('group_id = ?', $groupid)
    					->execute ();
    }
    
	/**
     * AddAttributes
     * Add a list of attributes in a group
     * @param integer $groupid
     * @param array $servers 
     * @return boolean
     */
    public static function AddServers($groupid, array $servers) {
    	if(!empty($servers)){
			foreach ($servers as $serverID) {
				$srv = new ServersGroupsIndexes();
				$srv->server_id = $serverID;
				$srv->group_id = $groupid;
				$srv->save();
			}
			return true;
		}
		return false;
    }
}