<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

/**
 * Table Definition for file_thumbnail
 */

class File_thumbnail extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_thumbnail';                  // table name
    public $file_id;                         // int(4)  primary_key not_null
    public $url;                             // varchar(255)  unique_key
    public $width;                           // int(4)
    public $height;                          // int(4)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('File_thumbnail',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Save oEmbed-provided thumbnail data
     *
     * @param object $data
     * @param int $file_id
     */
    public static function saveNew($data, $file_id) {
        if (!empty($data->thumbnail_url)) {
            // Non-photo types such as video will usually
            // show us a thumbnail, though it's not required.
            self::saveThumbnail($file_id,
                                $data->thumbnail_url,
                                $data->thumbnail_width,
                                $data->thumbnail_height);
        } else if ($data->type == 'photo') {
            // The inline photo URL given should also fit within
            // our requested thumbnail size, per oEmbed spec.
            self::saveThumbnail($file_id,
                                $data->url,
                                $data->width,
                                $data->height);
        }
    }

    /**
     * Save a thumbnail record for the referenced file record.
     *
     * @param int $file_id
     * @param string $url
     * @param int $width
     * @param int $height
     */
    static function saveThumbnail($file_id, $url, $width, $height)
    {
        $tn = new File_thumbnail;
        $tn->file_id = $file_id;
        $tn->url = $url;
        $tn->width = intval($width);
        $tn->height = intval($height);
        $tn->insert();
    }
}
