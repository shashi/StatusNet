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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Table Definition for profile
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  multiple_key not_null
    public $fullname;                        // varchar(255)  multiple_key
    public $profileurl;                      // varchar(255)
    public $homepage;                        // varchar(255)  multiple_key
    public $bio;                             // text()  multiple_key
    public $location;                        // varchar(255)  multiple_key
    public $lat;                             // decimal(10,7)
    public $lon;                             // decimal(10,7)
    public $location_id;                     // int(4)
    public $location_ns;                     // int(4)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) {
        return Memcached_DataObject::staticGet('Profile',$k,$v);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function getUser()
    {
        return User::staticGet('id', $this->id);
    }

    function getAvatar($width, $height=null)
    {
        if (is_null($height)) {
            $height = $width;
        }
        return Avatar::pkeyGet(array('profile_id' => $this->id,
                                     'width' => $width,
                                     'height' => $height));
    }

    function getOriginalAvatar()
    {
        $avatar = DB_DataObject::factory('avatar');
        $avatar->profile_id = $this->id;
        $avatar->original = true;
        if ($avatar->find(true)) {
            return $avatar;
        } else {
            return null;
        }
    }

    function setOriginal($filename)
    {
        $imagefile = new ImageFile($this->id, Avatar::path($filename));

        $avatar = new Avatar();
        $avatar->profile_id = $this->id;
        $avatar->width = $imagefile->width;
        $avatar->height = $imagefile->height;
        $avatar->mediatype = image_type_to_mime_type($imagefile->type);
        $avatar->filename = $filename;
        $avatar->original = true;
        $avatar->url = Avatar::url($filename);
        $avatar->created = DB_DataObject_Cast::dateTime(); # current time

        # XXX: start a transaction here

        if (!$this->delete_avatars() || !$avatar->insert()) {
            @unlink(Avatar::path($filename));
            return null;
        }

        foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
            # We don't do a scaled one if original is our scaled size
            if (!($avatar->width == $size && $avatar->height == $size)) {
                $scaled_filename = $imagefile->resize($size);

                //$scaled = DB_DataObject::factory('avatar');
                $scaled = new Avatar();
                $scaled->profile_id = $this->id;
                $scaled->width = $size;
                $scaled->height = $size;
                $scaled->original = false;
                $scaled->mediatype = image_type_to_mime_type($imagefile->type);
                $scaled->filename = $scaled_filename;
                $scaled->url = Avatar::url($scaled_filename);
                $scaled->created = DB_DataObject_Cast::dateTime(); # current time

                if (!$scaled->insert()) {
                    return null;
                }
            }
        }

        return $avatar;
    }

    /**
     * Delete attached avatars for this user from the database and filesystem.
     * This should be used instead of a batch delete() to ensure that files
     * get removed correctly.
     *
     * @param boolean $original true to delete only the original-size file
     * @return <type>
     */
    function delete_avatars($original=true)
    {
        $avatar = new Avatar();
        $avatar->profile_id = $this->id;
        $avatar->find();
        while ($avatar->fetch()) {
            if ($avatar->original) {
                if ($original == false) {
                    continue;
                }
            }
            $avatar->delete();
        }
        return true;
    }

    /**
     * Gets either the full name (if filled) or the nickname.
     *
     * @return string
     */
    function getBestName()
    {
        return ($this->fullname) ? $this->fullname : $this->nickname;
    }

    /**
     * Gets the full name (if filled) with nickname as a parenthetical, or the nickname alone
     * if no fullname is provided.
     *
     * @return string
     */
    function getFancyName()
    {
        if ($this->fullname) {
            // TRANS: Full name of a profile or group followed by nickname in parens
            return sprintf(_m('FANCYNAME','%1$s (%2$s)'), $this->fullname, $this->nickname);
        } else {
            return $this->nickname;
        }
    }

    /**
     * Get the most recent notice posted by this user, if any.
     *
     * @return mixed Notice or null
     */

    function getCurrentNotice()
    {
        $notice = $this->getNotices(0, 1);

        if ($notice->fetch()) {
            return $notice;
        } else {
            return null;
        }
    }

    function getTaggedNotices($tag, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        $ids = Notice::stream(array($this, '_streamTaggedDirect'),
                              array($tag),
                              'profile:notice_ids_tagged:' . $this->id . ':' . $tag,
                              $offset, $limit, $since_id, $max_id);
        return Notice::getStreamByIds($ids);
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        // XXX: I'm not sure this is going to be any faster. It probably isn't.
        $ids = Notice::stream(array($this, '_streamDirect'),
                              array(),
                              'profile:notice_ids:' . $this->id,
                              $offset, $limit, $since_id, $max_id);

        return Notice::getStreamByIds($ids);
    }

    function _streamTaggedDirect($tag, $offset, $limit, $since_id, $max_id)
    {
        // XXX It would be nice to do this without a join
        // (necessary to do it efficiently on accounts with long history)

        $notice = new Notice();

        $query =
          "select id from notice join notice_tag on id=notice_id where tag='".
          $notice->escape($tag) .
          "' and profile_id=" . intval($this->id);

        $since = Notice::whereSinceId($since_id, 'id', 'notice.created');
        if ($since) {
            $query .= " and ($since)";
        }

        $max = Notice::whereMaxId($max_id, 'id', 'notice.created');
        if ($max) {
            $query .= " and ($max)";
        }

        $query .= ' order by notice.created DESC, id DESC';

        if (!is_null($offset)) {
            $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }

        $notice->query($query);

        $ids = array();

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        return $ids;
    }

    function _streamDirect($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->profile_id = $this->id;

        $notice->selectAdd();
        $notice->selectAdd('id');

        Notice::addWhereSinceId($notice, $since_id);
        Notice::addWhereMaxId($notice, $max_id);

        $notice->orderBy('created DESC, id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        $notice->find();

        $ids = array();

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        return $ids;
    }

    function isMember($group)
    {
        $mem = new Group_member();

        $mem->group_id = $group->id;
        $mem->profile_id = $this->id;

        if ($mem->find()) {
            return true;
        } else {
            return false;
        }
    }

    function isAdmin($group)
    {
        $mem = new Group_member();

        $mem->group_id = $group->id;
        $mem->profile_id = $this->id;
        $mem->is_admin = 1;

        if ($mem->find()) {
            return true;
        } else {
            return false;
        }
    }

    function getGroups($offset=0, $limit=null)
    {
        $qry =
          'SELECT user_group.* ' .
          'FROM user_group JOIN group_member '.
          'ON user_group.id = group_member.group_id ' .
          'WHERE group_member.profile_id = %d ' .
          'ORDER BY group_member.created DESC ';

        if ($offset>0 && !is_null($limit)) {
            if ($offset) {
                if (common_config('db','type') == 'pgsql') {
                    $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
                } else {
                    $qry .= ' LIMIT ' . $offset . ', ' . $limit;
                }
            }
        }

        $groups = new User_group();

        $cnt = $groups->query(sprintf($qry, $this->id));

        return $groups;
    }

    function isTagged($peopletag)
    {
        $tag = Profile_tag::pkeyGet(array('tagger' => $peopletag->tagger,
                                          'tagged' => $this->id,
                                          'tag'    => $peopletag->tag));
        return !empty($tag);
    }

    function canTag($tagged)
    {
        if (empty($tagged)) {
            return false;
        }

        if ($tagged->id == $this->id) {
            return true;
        }

        $all = common_config('peopletag', 'allow_tagging', 'all');
        $local = common_config('peopletag', 'allow_tagging', 'local');
        $remote = common_config('peopletag', 'allow_tagging', 'remote');
        $subs = common_config('peopletag', 'allow_tagging', 'subs');

        if ($all) {
            return true;
        }

        $tagged_user = $tagged->getUser();
        if (!empty($tagged_user)) {
            if ($local) {
                return true;
            }
        } else if ($subs) {
            return (Subscription::exists($this, $tagged) ||
                    Subscription::exists($tagged, $this));
        } else if ($remote) {
            return true;
        }
        return false;
    }

    function getOwnedTags($auth_user, $offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $tags = new Profile_list();
        $tags->tagger = $this->id;

        if (($auth_user instanceof User || $auth_user instanceof Profile) &&
                $auth_user->id === $this->id) {
            // no condition, get both private and public tags
        } else {
            $tags->private = false;
        }

        $tags->selectAdd('id as "cursor"');

        if ($since_id>0) {
           $tags->whereAdd('id > '.$since_id);
        }

        if ($max_id>0) {
            $tags->whereAdd('id <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $tags->limit($offset, $limit);
        }

        $tags->orderBy('id DESC');
        $tags->find();

        return $tags;
    }

    function getOtherTags($auth_user=null, $offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $lists = new Profile_list();

        $tags = new Profile_tag();
        $tags->tagged = $this->id;

        $lists->joinAdd($tags);
        #@fixme: postgres (round(date_part('epoch', my_date)))
        $lists->selectAdd('unix_timestamp(profile_tag.modified) as "cursor"');

        if ($auth_user instanceof User || $auth_user instanceof Profile) {
            $lists->whereAdd('( ( profile_list.private = false ) ' .
                             'OR ( profile_list.tagger = ' . $auth_user->id . ' AND ' .
                             'profile_list.private = true ) )');
        } else {
            $lists->private = false;
        }

        if ($since_id>0) {
           $lists->whereAdd('cursor > '.$since_id);
        }

        if ($max_id>0) {
            $lists->whereAdd('cursor <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $lists->limit($offset, $limit);
        }

        $lists->orderBy('profile_tag.modified DESC');
        $lists->find();

        return $lists;
    }

    function getPrivateTags($offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $tags = new Profile_list();
        $tags->private = true;
        $tags->tagger = $this->id;

        if ($since_id>0) {
           $tags->whereAdd('id > '.$since_id);
        }

        if ($max_id>0) {
            $tags->whereAdd('id <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $tags->limit($offset, $limit);
        }

        $tags->orderBy('id DESC');
        $tags->find();

        return $tags;
    }

    function hasLocalTags()
    {
        $tags = new Profile_tag();

        $tags->joinAdd(array('tagger', 'user:id'));
        $tags->whereAdd('tagged  = '.$this->id);
        $tags->whereAdd('tagger != '.$this->id);

        $tags->limit(0, 1);
        $tags->fetch();

        return ($tags->N == 0) ? false : true;
    }

    function getTagSubscriptions($offset=0, $limit=null, $since_id=0, $max_id=0)
    {
        $lists = new Profile_list();
        $subs = new Profile_tag_subscription();

        $lists->joinAdd($subs);
        #@fixme: postgres (round(date_part('epoch', my_date)))
        $lists->selectAdd('unix_timestamp(profile_tag_subscription.created) as "cursor"');

        $lists->whereAdd('profile_tag_subscription.profile_id = '.$this->id);

        if ($since_id>0) {
           $lists->whereAdd('cursor > '.$since_id);
        }

        if ($max_id>0) {
            $lists->whereAdd('cursor <= '.$max_id);
        }

        if($offset>=0 && !is_null($limit)) {
            $lists->limit($offset, $limit);
        }

        $lists->orderBy('"cursor" DESC');
        $lists->find();

        return $lists;
    }

    function avatarUrl($size=AVATAR_PROFILE_SIZE)
    {
        $avatar = $this->getAvatar($size);
        if ($avatar) {
            return $avatar->displayUrl();
        } else {
            return Avatar::defaultImage($size);
        }
    }

    function getSubscriptions($offset=0, $limit=null)
    {
        $subs = Subscription::bySubscriber($this->id,
                                           $offset,
                                           $limit);

        $profiles = array();

        while ($subs->fetch()) {
            $profiles[] = Profile::staticGet($subs->subscribed);
        }

        return new ArrayWrapper($profiles);
    }

    function getSubscribers($offset=0, $limit=null)
    {
        $subs = Subscription::bySubscribed($this->id,
                                           $offset,
                                           $limit);

        $profiles = array();

        while ($subs->fetch()) {
            $profiles[] = Profile::staticGet($subs->subscriber);
        }

        return new ArrayWrapper($profiles);
    }


    function getTaggedSubscribers($tag)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN (subscription, profile_tag, profile_list) ' .
          'ON profile.id = subscription.subscriber ' .
          'AND profile.id = profile_tag.tagged ' .
          'AND profile_tag.tagger = profile_list.tagger AND profile_tag.tag = profile_list.tag ' .
          'WHERE subscription.subscribed = %d ' .
          'AND subscription.subscribed != subscription.subscriber ' .
          'AND profile_tag.tagger = %d AND profile_tag.tag = "%s" ' .
          'AND profile_list.private = false ' .
          'ORDER BY subscription.created DESC';

        $profile = new Profile();
        $tagged = array();

        $cnt = $profile->query(sprintf($qry, $this->id, $this->id, $tag));

        while ($profile->fetch()) {
            $tagged[] = clone($profile);
        }
        return $tagged;
    }

    function subscriptionCount()
    {
        $c = Cache::instance();

        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:subscription_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscriber = $this->id;

        $cnt = (int) $sub->count('distinct subscribed');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(Cache::key('profile:subscription_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function subscriberCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:subscriber_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscribed = $this->id;
        $sub->whereAdd('subscriber != subscribed');
        $cnt = (int) $sub->count('distinct subscriber');

        if (!empty($c)) {
            $c->set(Cache::key('profile:subscriber_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    /**
     * Is this profile subscribed to another profile?
     *
     * @param Profile $other
     * @return boolean
     */
    function isSubscribed($other)
    {
        return Subscription::exists($this, $other);
    }

    /**
     * Are these two profiles subscribed to each other?
     *
     * @param Profile $other
     * @return boolean
     */
    function mutuallySubscribed($other)
    {
        return $this->isSubscribed($other) &&
          $other->isSubscribed($this);
    }

    function hasFave($notice)
    {
        $cache = Cache::instance();

        // XXX: Kind of a hack.

        if (!empty($cache)) {
            // This is the stream of favorite notices, in rev chron
            // order. This forces it into cache.

            $ids = Fave::stream($this->id, 0, NOTICE_CACHE_WINDOW);

            // If it's in the list, then it's a fave

            if (in_array($notice->id, $ids)) {
                return true;
            }

            // If we're not past the end of the cache window,
            // then the cache has all available faves, so this one
            // is not a fave.

            if (count($ids) < NOTICE_CACHE_WINDOW) {
                return false;
            }

            // Otherwise, cache doesn't have all faves;
            // fall through to the default
        }

        $fave = Fave::pkeyGet(array('user_id' => $this->id,
                                    'notice_id' => $notice->id));
        return ((is_null($fave)) ? false : true);
    }

    function faveCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:fave_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $faves = new Fave();
        $faves->user_id = $this->id;
        $cnt = (int) $faves->count('distinct notice_id');

        if (!empty($c)) {
            $c->set(Cache::key('profile:fave_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function noticeCount()
    {
        $c = Cache::instance();

        if (!empty($c)) {
            $cnt = $c->get(Cache::key('profile:notice_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $notices = new Notice();
        $notices->profile_id = $this->id;
        $cnt = (int) $notices->count('distinct id');

        if (!empty($c)) {
            $c->set(Cache::key('profile:notice_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function blowFavesCache()
    {
        $cache = Cache::instance();
        if ($cache) {
            // Faves don't happen chronologically, so we need to blow
            // ;last cache, too
            $cache->delete(Cache::key('fave:ids_by_user:'.$this->id));
            $cache->delete(Cache::key('fave:ids_by_user:'.$this->id.';last'));
            $cache->delete(Cache::key('fave:ids_by_user_own:'.$this->id));
            $cache->delete(Cache::key('fave:ids_by_user_own:'.$this->id.';last'));
        }
        $this->blowFaveCount();
    }

    function blowSubscriberCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:subscriber_count:'.$this->id));
        }
    }

    function blowSubscriptionCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:subscription_count:'.$this->id));
        }
    }

    function blowFaveCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:fave_count:'.$this->id));
        }
    }

    function blowNoticeCount()
    {
        $c = Cache::instance();
        if (!empty($c)) {
            $c->delete(Cache::key('profile:notice_count:'.$this->id));
        }
    }

    static function maxBio()
    {
        $biolimit = common_config('profile', 'biolimit');
        // null => use global limit (distinct from 0!)
        if (is_null($biolimit)) {
            $biolimit = common_config('site', 'textlimit');
        }
        return $biolimit;
    }

    static function bioTooLong($bio)
    {
        $biolimit = self::maxBio();
        return ($biolimit > 0 && !empty($bio) && (mb_strlen($bio) > $biolimit));
    }

    function delete()
    {
        $this->_deleteNotices();
        $this->_deleteSubscriptions();
        $this->_deleteMessages();
        $this->_deleteTags();
        $this->_deleteBlocks();
        $this->delete_avatars();

        // Warning: delete() will run on the batch objects,
        // not on individual objects.
        $related = array('Reply',
                         'Group_member',
                         );
        Event::handle('ProfileDeleteRelated', array($this, &$related));

        foreach ($related as $cls) {
            $inst = new $cls();
            $inst->profile_id = $this->id;
            $inst->delete();
        }

        parent::delete();
    }

    function _deleteNotices()
    {
        $notice = new Notice();
        $notice->profile_id = $this->id;

        if ($notice->find()) {
            while ($notice->fetch()) {
                $other = clone($notice);
                $other->delete();
            }
        }
    }

    function _deleteSubscriptions()
    {
        $sub = new Subscription();
        $sub->subscriber = $this->id;

        $sub->find();

        while ($sub->fetch()) {
            $other = Profile::staticGet('id', $sub->subscribed);
            if (empty($other)) {
                continue;
            }
            if ($other->id == $this->id) {
                continue;
            }
            Subscription::cancel($this, $other);
        }

        $subd = new Subscription();
        $subd->subscribed = $this->id;
        $subd->find();

        while ($subd->fetch()) {
            $other = Profile::staticGet('id', $subd->subscriber);
            if (empty($other)) {
                continue;
            }
            if ($other->id == $this->id) {
                continue;
            }
            Subscription::cancel($other, $this);
        }

        $self = new Subscription();

        $self->subscriber = $this->id;
        $self->subscribed = $this->id;

        $self->delete();
    }

    function _deleteMessages()
    {
        $msg = new Message();
        $msg->from_profile = $this->id;
        $msg->delete();

        $msg = new Message();
        $msg->to_profile = $this->id;
        $msg->delete();
    }

    function _deleteTags()
    {
        $tag = new Profile_tag();
        $tag->tagged = $this->id;
        $tag->delete();
    }

    function _deleteBlocks()
    {
        $block = new Profile_block();
        $block->blocked = $this->id;
        $block->delete();

        $block = new Group_block();
        $block->blocked = $this->id;
        $block->delete();
    }

    // XXX: identical to Notice::getLocation.

    function getLocation()
    {
        $location = null;

        if (!empty($this->location_id) && !empty($this->location_ns)) {
            $location = Location::fromId($this->location_id, $this->location_ns);
        }

        if (is_null($location)) { // no ID, or Location::fromId() failed
            if (!empty($this->lat) && !empty($this->lon)) {
                $location = Location::fromLatLon($this->lat, $this->lon);
            }
        }

        if (is_null($location)) { // still haven't found it!
            if (!empty($this->location)) {
                $location = Location::fromName($this->location);
            }
        }

        return $location;
    }

    function hasRole($name)
    {
        $has_role = false;
        if (Event::handle('StartHasRole', array($this, $name, &$has_role))) {
            $role = Profile_role::pkeyGet(array('profile_id' => $this->id,
                                                'role' => $name));
            $has_role = !empty($role);
            Event::handle('EndHasRole', array($this, $name, $has_role));
        }
        return $has_role;
    }

    function grantRole($name)
    {
        if (Event::handle('StartGrantRole', array($this, $name))) {

            $role = new Profile_role();

            $role->profile_id = $this->id;
            $role->role       = $name;
            $role->created    = common_sql_now();

            $result = $role->insert();

            if (!$result) {
                throw new Exception("Can't save role '$name' for profile '{$this->id}'");
            }

            Event::handle('EndGrantRole', array($this, $name));
        }

        return $result;
    }

    function revokeRole($name)
    {
        if (Event::handle('StartRevokeRole', array($this, $name))) {

            $role = Profile_role::pkeyGet(array('profile_id' => $this->id,
                                                'role' => $name));

            if (empty($role)) {
                // TRANS: Exception thrown when trying to revoke an existing role for a user that does not exist.
                // TRANS: %1$s is the role name, %2$s is the user ID (number).
                throw new Exception(sprintf(_('Cannot revoke role "%1$s" for user #%2$d; does not exist.'),$name, $this->id));
            }

            $result = $role->delete();

            if (!$result) {
                common_log_db_error($role, 'DELETE', __FILE__);
                // TRANS: Exception thrown when trying to revoke a role for a user with a failing database query.
                // TRANS: %1$s is the role name, %2$s is the user ID (number).
                throw new Exception(sprintf(_('Cannot revoke role "%1$s" for user #%2$d; database error.'),$name, $this->id));
            }

            Event::handle('EndRevokeRole', array($this, $name));

            return true;
        }
    }

    function isSandboxed()
    {
        return $this->hasRole(Profile_role::SANDBOXED);
    }

    function isSilenced()
    {
        return $this->hasRole(Profile_role::SILENCED);
    }

    function sandbox()
    {
        $this->grantRole(Profile_role::SANDBOXED);
    }

    function unsandbox()
    {
        $this->revokeRole(Profile_role::SANDBOXED);
    }

    function silence()
    {
        $this->grantRole(Profile_role::SILENCED);
    }

    function unsilence()
    {
        $this->revokeRole(Profile_role::SILENCED);
    }

    /**
     * Does this user have the right to do X?
     *
     * With our role-based authorization, this is merely a lookup for whether the user
     * has a particular role. The implementation currently uses a switch statement
     * to determine if the user has the pre-defined role to exercise the right. Future
     * implementations may allow per-site roles, and different mappings of roles to rights.
     *
     * @param $right string Name of the right, usually a constant in class Right
     * @return boolean whether the user has the right in question
     */
    function hasRight($right)
    {
        $result = false;

        if ($this->hasRole(Profile_role::DELETED)) {
            return false;
        }

        if (Event::handle('UserRightsCheck', array($this, $right, &$result))) {
            switch ($right)
            {
            case Right::DELETEOTHERSNOTICE:
            case Right::MAKEGROUPADMIN:
            case Right::SANDBOXUSER:
            case Right::SILENCEUSER:
            case Right::DELETEUSER:
            case Right::DELETEGROUP:
                $result = $this->hasRole(Profile_role::MODERATOR);
                break;
            case Right::CONFIGURESITE:
                $result = $this->hasRole(Profile_role::ADMINISTRATOR);
                break;
            case Right::GRANTROLE:
            case Right::REVOKEROLE:
                $result = $this->hasRole(Profile_role::OWNER);
                break;
            case Right::NEWNOTICE:
            case Right::NEWMESSAGE:
            case Right::SUBSCRIBE:
                $result = !$this->isSilenced();
                break;
            case Right::PUBLICNOTICE:
            case Right::EMAILONREPLY:
            case Right::EMAILONSUBSCRIBE:
            case Right::EMAILONFAVE:
                $result = !$this->isSandboxed();
                break;
            case Right::BACKUPACCOUNT:
                $result = common_config('profile', 'backup');
                break;
            case Right::RESTOREACCOUNT:
                $result = common_config('profile', 'restore');
                break;
            case Right::DELETEACCOUNT:
                $result = common_config('profile', 'delete');
                break;
            case Right::MOVEACCOUNT:
                $result = common_config('profile', 'move');
                break;
            default:
                $result = false;
                break;
            }
        }
        return $result;
    }

    function hasRepeated($notice_id)
    {
        // XXX: not really a pkey, but should work

        $notice = Memcached_DataObject::pkeyGet('Notice',
                                                array('profile_id' => $this->id,
                                                      'repeat_of' => $notice_id));

        return !empty($notice);
    }

    /**
     * Returns an XML string fragment with limited profile information
     * as an Atom <author> element.
     *
     * Assumes that Atom has been previously set up as the base namespace.
     *
     * @param Profile $cur the current authenticated user
     *
     * @return string
     */
    function asAtomAuthor($cur = null)
    {
        $xs = new XMLStringer(true);

        $xs->elementStart('author');
        $xs->element('name', null, $this->nickname);
        $xs->element('uri', null, $this->getUri());
        if ($cur != null) {
            $attrs = Array();
            $attrs['following'] = $cur->isSubscribed($this) ? 'true' : 'false';
            $attrs['blocking']  = $cur->hasBlocked($this) ? 'true' : 'false';
            $xs->element('statusnet:profile_info', $attrs, null);
        }
        $xs->elementEnd('author');

        return $xs->getString();
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams <activity:actor> element.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @return string
     */
    function asActivityActor()
    {
        return $this->asActivityNoun('actor');
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity', 'georss', and 'poco' namespace has been
     * previously defined.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     *
     * @return string
     */
    function asActivityNoun($element)
    {
        $noun = ActivityObject::fromProfile($this);
        return $noun->asString('activity:' . $element);
    }

    /**
     * Returns the best URI for a profile. Plugins may override.
     *
     * @return string $uri
     */
    function getUri()
    {
        $uri = null;

        // give plugins a chance to set the URI
        if (Event::handle('StartGetProfileUri', array($this, &$uri))) {

            // check for a local user first
            $user = User::staticGet('id', $this->id);

            if (!empty($user)) {
                $uri = $user->uri;
            } else {
                // return OMB profile if any
                $remote = Remote_profile::staticGet('id', $this->id);
                if (!empty($remote)) {
                    $uri = $remote->uri;
                }
            }
            Event::handle('EndGetProfileUri', array($this, &$uri));
        }

        return $uri;
    }

    function hasBlocked($other)
    {
        $block = Profile_block::get($this->id, $other->id);

        if (empty($block)) {
            $result = false;
        } else {
            $result = true;
        }

        return $result;
    }

    function getAtomFeed()
    {
        $feed = null;

        if (Event::handle('StartProfileGetAtomFeed', array($this, &$feed))) {
            $user = User::staticGet('id', $this->id);
            if (!empty($user)) {
                $feed = common_local_url('ApiTimelineUser', array('id' => $user->id,
                                                                  'format' => 'atom'));
            }
            Event::handle('EndProfileGetAtomFeed', array($this, $feed));
        }

        return $feed;
    }

    static function fromURI($uri)
    {
        $profile = null;

        if (Event::handle('StartGetProfileFromURI', array($uri, &$profile))) {
            // Get a local user or remote (OMB 0.1) profile
            $user = User::staticGet('uri', $uri);
            if (!empty($user)) {
                $profile = $user->getProfile();
            } else {
                $remote_profile = Remote_profile::staticGet('uri', $uri);
                if (!empty($remote_profile)) {
                    $profile = Profile::staticGet('id', $remote_profile->profile_id);
                }
            }
            Event::handle('EndGetProfileFromURI', array($uri, $profile));
        }

        return $profile;
    }
}
