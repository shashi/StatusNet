<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, 2010 StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) { exit(1); }

class Status_network_tag extends Safe_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'status_network_tag';                      // table name
    public $site_id;                  // int(4)  primary_key not_null
    public $tag;                      // varchar(64)  primary_key not_null 
    public $created;                 // datetime()   not_null


    function __construct()
    {
        global $config;
        global $_DB_DATAOBJECT;
        
        $sn = new Status_network();
        $sn->_connect();

        $config['db']['table_'. $this->__table] = $sn->_database;

        $this->_connect();
    }


    /* Static get */
    function staticGet($k,$v=null)
    {
        $i = DB_DataObject::staticGet('Status_network_tag',$k,$v);

        // Don't use local process cache; if we're fetching multiple
        // times it's because we're reloading it in a long-running
        // process; we need a fresh copy!
        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CACHE']['status_network_tag']);
        return $i;
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE



    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Status_network_tag', $kv);
    }
}
