<?php
/**
 * Data class to mark a notice as a checkin
 *
 * PHP version 5
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category Checkin
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * A checkin
 *
 * @category Checkin
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class Checkin extends Managed_DataObject
{
    const OBJECT_TYPE = 'http://activityschema.org/object/checkin';

    public $__table = 'checkin'; // table name
    public $id;          // char(36) primary key not null -> UUID
    public $uri;
    public $profile_id;  // int -> profile.id
    public $spot_id;
    public $created;     // datetime

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup
     * @param mixed  $v Value to lookup
     *
     * @return QnA_Question object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('Checkin', $k, $v);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'Checkin data',
            'fields' => array(
                'id' => array(
                    'type'        => 'char',
                    'length'        => 36,
                    'not null'    => true,
                ),
                'uri' => array(
                    'type'     => 'varchar',
                    'length'   => 255,
                    'not null' => true
                ),
                'profile_id'  => array('type' => 'int'),
                'spot_id'  => array('type' => 'int'),
                'created'     => array(
                    'type'     => 'datetime',
                    'not null' => true
                ),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'checkin_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
	            'checkin_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
	            'checkin_spot_id_fkey' => array('spot', array('spot_id' => 'id')),
            ),
        );
    }

    /**
     * Get a checkin associated with a notice.
     *
     * @param Notice $notice Notice to check for
     *
     * @return Question found question or null
     */
    function getByNotice($notice)
    {
        return self::staticGet('uri', $notice->uri);
    }

    function getNotice()
    {
        return Notice::staticGet('uri', $this->uri);
    }

    function bestUrl()
    {
        return $this->getNotice()->bestUrl();
    }

    function getSpot()
    {
        $profile = Spot::staticGet('id', $this->profile_id);
        if (empty($profile)) {
            throw new Exception("No spot with ID {$this->profile_id}");
        }
        return $profile;
    }

    function getProfile()
    {
        $profile = Profile::staticGet('id', $this->spot_id);
        if (empty($profile)) {
            throw new Exception("No profile with ID {$this->spot_id}");
        }
        return $profile;
    }

    static function fromNotice($notice)
    {
        return Checkin::staticGet('uri', $notice->uri);
    }

    function asHTML()
    {
        return self::toHTML($this);
    }

    function asString()
    {
        return self::toString($this);
    }

    static function toHTML($checkin, $third_person=false)
    {
        $notice = $checkin->getNotice();

        $out = new XMLStringer();
        $out->elementStart('span', 'checkin');

		$out->raw(sprintf(_('%s checked in to %s'), $this->vcardLink(), $spot->spotLink()));
        $out->elementEnd('span');

        return $out->getString();
    }

    static function toString($checkin)
    {
        return sprintf(htmlspecialchars("$checkin->profile_id spot_id $checkin->spot_id"));
    }

    function vcardLink()
    {
		$profile = $this->getProfile();
        $xs = new XMLStringer(false);

        $attrs = array('href' => $profile->profileurl,
                       'class' => 'url');

        $attrs['title'] = $profile->getBestName();

        $xs->elementStart('span', 'vcard');
        $xs->elementStart('a', $attrs);
        $xs->element('span', 'fn nickname', $profile->nickname);
        $xs->elementEnd('a');
        $xs->elementEnd('span');

        return $xs->getString();
    }

	function spotLink()
	{
		$spot = $this->getSpot();
		$xs = new XMLStringer(false);

		$xs->elementStart('span', 'spot');
		$attr = array('href' => $spot->mainpageLink(), 'title' => $spot->coordinates());
		$xs->element('a', $attr, $spot->fullname);
		$xs->elementEnd('span');

		return $xs->getString();
	}

    /**
     * Save a new checkin
     *
     * @param Profile $profile_id
     * @param Profile  $spot_id
     *
     * @return Notice saved notice
     */
    static function saveNew($profile_id, $spot_id, $options = array())
    {
        $checkin = new Checkin();

        $checkin->id = UUID::gen();
        $checkin->profile_id = $profile_id->id;
        $checkin->spot_id = $spot_id->id;
        $checkin->uri = common_local_url(
            'showcheckin',
            array('id' => $checkin->id)
        );


        if (array_key_exists('created', $options)) {
            $checkin->created = $options['created'];
        } else {
            $checkin->created = common_sql_now();
        }

        if (array_key_exists('uri', $options)) {
            $checkin->uri = $options['uri'];
        } else {
            $options['uri'] = $checkin->uri;
        }

        common_log(LOG_DEBUG, "Saving checkin: $checkin->id $checkin->uri");
        $checkin->insert();

        if (empty($checkin)) {
            throw new Exception('Could not save checkin');
        }

        $content = $this->asString();

        $rendered = $this->asHTML();

        $replies = array($spot->getUri());
        $options = array_merge(
            array(
                'rendered'    => $rendered,
                'replies'     => $replies,
                'object_type' => self::OBJECT_TYPE
            ),
            $options
        );

        $saved = Notice::saveNew(
            $profile_id->id,
            $content,
            array_key_exists('source', $options) ?
            $options['source'] : 'web',
            $options
        );

        return $saved;
    }
}
