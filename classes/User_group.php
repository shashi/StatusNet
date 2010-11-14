<?php
/**
 * Table Definition for user_group
 */

class User_group extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_group';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)
    public $fullname;                        // varchar(255)
    public $homepage;                        // varchar(255)
    public $description;                     // text
    public $location;                        // varchar(255)
    public $original_logo;                   // varchar(255)
    public $homepage_logo;                   // varchar(255)
    public $stream_logo;                     // varchar(255)
    public $mini_logo;                       // varchar(255)
    public $design_id;                       // int(4)
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP
    public $uri;                             // varchar(255)  unique_key
    public $mainpage;                        // varchar(255)

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('User_group',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function defaultLogo($size)
    {
        static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
                                  AVATAR_STREAM_SIZE => 'stream',
                                  AVATAR_MINI_SIZE => 'mini');
        return Theme::path('default-avatar-'.$sizenames[$size].'.png');
    }

    function homeUrl()
    {
        $url = null;
        if (Event::handle('StartUserGroupHomeUrl', array($this, &$url))) {
            // normally stored in mainpage, but older ones may be null
            if (!empty($this->mainpage)) {
                $url = $this->mainpage;
            } else {
                $url = common_local_url('showgroup',
                                        array('nickname' => $this->nickname));
            }
        }
        Event::handle('EndUserGroupHomeUrl', array($this, &$url));
        return $url;
    }

    function getUri()
    {
        $uri = null;
        if (Event::handle('StartUserGroupGetUri', array($this, &$uri))) {
            if (!empty($this->uri)) {
                $uri = $this->uri;
            } else {
                $uri = common_local_url('groupbyid',
                                        array('id' => $this->id));
            }
        }
        Event::handle('EndUserGroupGetUri', array($this, &$uri));
        return $uri;
    }

    function permalink()
    {
        $url = null;
        if (Event::handle('StartUserGroupPermalink', array($this, &$url))) {
            $url = common_local_url('groupbyid',
                                    array('id' => $this->id));
        }
        Event::handle('EndUserGroupPermalink', array($this, &$url));
        return $url;
    }

    function getNotices($offset, $limit, $since_id=null, $max_id=null)
    {
        $ids = Notice::stream(array($this, '_streamDirect'),
                              array(),
                              'user_group:notice_ids:' . $this->id,
                              $offset, $limit, $since_id, $max_id);

        return Notice::getStreamByIds($ids);
    }

    function _streamDirect($offset, $limit, $since_id, $max_id)
    {
        $inbox = new Group_inbox();

        $inbox->group_id = $this->id;

        $inbox->selectAdd();
        $inbox->selectAdd('notice_id');

        if ($since_id != 0) {
            $inbox->whereAdd('notice_id > ' . $since_id);
        }

        if ($max_id != 0) {
            $inbox->whereAdd('notice_id <= ' . $max_id);
        }

        $inbox->orderBy('notice_id DESC');

        if (!is_null($offset)) {
            $inbox->limit($offset, $limit);
        }

        $ids = array();

        if ($inbox->find()) {
            while ($inbox->fetch()) {
                $ids[] = $inbox->notice_id;
            }
        }

        return $ids;
    }

    function allowedNickname($nickname)
    {
        static $blacklist = array('new');
        return !in_array($nickname, $blacklist);
    }

    function getMembers($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN group_member '.
          'ON profile.id = group_member.profile_id ' .
          'WHERE group_member.group_id = %d ' .
          'ORDER BY group_member.created DESC ';

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $members = new Profile();

        $members->query(sprintf($qry, $this->id));
        return $members;
    }

    function getMemberCount()
    {
        // XXX: WORM cache this

        $members = $this->getMembers();
        $member_count = 0;

        /** $member->count() doesn't work. */
        while ($members->fetch()) {
            $member_count++;
        }

        return $member_count;
    }

    function getAdmins($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN group_member '.
          'ON profile.id = group_member.profile_id ' .
          'WHERE group_member.group_id = %d ' .
          'AND group_member.is_admin = 1 ' .
          'ORDER BY group_member.modified ASC ';

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $admins = new Profile();

        $admins->query(sprintf($qry, $this->id));
        return $admins;
    }

    function getBlocked($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN group_block '.
          'ON profile.id = group_block.blocked ' .
          'WHERE group_block.group_id = %d ' .
          'ORDER BY group_block.modified DESC ';

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $blocked = new Profile();

        $blocked->query(sprintf($qry, $this->id));
        return $blocked;
    }

    function setOriginal($filename)
    {
        $imagefile = new ImageFile($this->id, Avatar::path($filename));

        $orig = clone($this);
        $this->original_logo = Avatar::url($filename);
        $this->homepage_logo = Avatar::url($imagefile->resize(AVATAR_PROFILE_SIZE));
        $this->stream_logo = Avatar::url($imagefile->resize(AVATAR_STREAM_SIZE));
        $this->mini_logo = Avatar::url($imagefile->resize(AVATAR_MINI_SIZE));
        common_debug(common_log_objstring($this));
        return $this->update($orig);
    }

    function getBestName()
    {
        return ($this->fullname) ? $this->fullname : $this->nickname;
    }

    function getAliases()
    {
        $aliases = array();

        // XXX: cache this

        $alias = new Group_alias();

        $alias->group_id = $this->id;

        if ($alias->find()) {
            while ($alias->fetch()) {
                $aliases[] = $alias->alias;
            }
        }

        $alias->free();

        return $aliases;
    }

    function setAliases($newaliases) {

        $newaliases = array_unique($newaliases);

        $oldaliases = $this->getAliases();

        # Delete stuff that's old that not in new

        $to_delete = array_diff($oldaliases, $newaliases);

        # Insert stuff that's in new and not in old

        $to_insert = array_diff($newaliases, $oldaliases);

        $alias = new Group_alias();

        $alias->group_id = $this->id;

        foreach ($to_delete as $delalias) {
            $alias->alias = $delalias;
            $result = $alias->delete();
            if (!$result) {
                common_log_db_error($alias, 'DELETE', __FILE__);
                return false;
            }
        }

        foreach ($to_insert as $insalias) {
            $alias->alias = $insalias;
            $result = $alias->insert();
            if (!$result) {
                common_log_db_error($alias, 'INSERT', __FILE__);
                return false;
            }
        }

        return true;
    }

    static function getForNickname($nickname, $profile=null)
    {
        $nickname = common_canonical_nickname($nickname);

        // Are there any matching remote groups this profile's in?
        if ($profile) {
            $group = $profile->getGroups();
            while ($group->fetch()) {
                if ($group->nickname == $nickname) {
                    // @fixme is this the best way?
                    return clone($group);
                }
            }
        }

        // If not, check local groups.

        $group = Local_group::staticGet('nickname', $nickname);
        if (!empty($group)) {
            return User_group::staticGet('id', $group->group_id);
        }
        $alias = Group_alias::staticGet('alias', $nickname);
        if (!empty($alias)) {
            return User_group::staticGet('id', $alias->group_id);
        }
        return null;
    }

    function getDesign()
    {
        return Design::staticGet('id', $this->design_id);
    }

    function getUserMembers()
    {
        // XXX: cache this

        $user = new User();
        if(common_config('db','quote_identifiers'))
            $user_table = '"user"';
        else $user_table = 'user';

        $qry =
          'SELECT id ' .
          'FROM '. $user_table .' JOIN group_member '.
          'ON '. $user_table .'.id = group_member.profile_id ' .
          'WHERE group_member.group_id = %d ';

        $user->query(sprintf($qry, $this->id));

        $ids = array();

        while ($user->fetch()) {
            $ids[] = $user->id;
        }

        $user->free();

        return $ids;
    }

    static function maxDescription()
    {
        $desclimit = common_config('group', 'desclimit');
        // null => use global limit (distinct from 0!)
        if (is_null($desclimit)) {
            $desclimit = common_config('site', 'textlimit');
        }
        return $desclimit;
    }

    static function descriptionTooLong($desc)
    {
        $desclimit = self::maxDescription();
        return ($desclimit > 0 && !empty($desc) && (mb_strlen($desc) > $desclimit));
    }

    function asAtomEntry($namespace=false, $source=false)
    {
        $xs = new XMLStringer(true);

        if ($namespace) {
            $attrs = array('xmlns' => 'http://www.w3.org/2005/Atom',
                           'xmlns:thr' => 'http://purl.org/syndication/thread/1.0');
        } else {
            $attrs = array();
        }

        $xs->elementStart('entry', $attrs);

        if ($source) {
            $xs->elementStart('source');
            $xs->element('id', null, $this->permalink());
            $xs->element('title', null, $profile->nickname . " - " . common_config('site', 'name'));
            $xs->element('link', array('href' => $this->permalink()));
            $xs->element('updated', null, $this->modified);
            $xs->elementEnd('source');
        }

        $xs->element('title', null, $this->nickname);
        $xs->element('summary', null, common_xml_safe_str($this->description));

        $xs->element('link', array('rel' => 'alternate',
                                   'href' => $this->permalink()));

        $xs->element('id', null, $this->permalink());

        $xs->element('published', null, common_date_w3dtf($this->created));
        $xs->element('updated', null, common_date_w3dtf($this->modified));

        $xs->element(
            'content',
            array('type' => 'html'),
            common_xml_safe_str($this->description)
        );

        $xs->elementEnd('entry');

        return $xs->getString();
    }

    function asAtomAuthor()
    {
        $xs = new XMLStringer(true);

        $xs->elementStart('author');
        $xs->element('name', null, $this->nickname);
        $xs->element('uri', null, $this->permalink());
        $xs->elementEnd('author');

        return $xs->getString();
    }

    /**
     * Returns an XML string fragment with group information as an
     * Activity Streams <activity:subject> element.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @return string
     */
    function asActivitySubject()
    {
        return $this->asActivityNoun('subject');
    }

    /**
     * Returns an XML string fragment with group information as an
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
        $noun = ActivityObject::fromGroup($this);
        return $noun->asString('activity:' . $element);
    }

    function getAvatar()
    {
        return empty($this->homepage_logo)
            ? User_group::defaultLogo(AVATAR_PROFILE_SIZE)
            : $this->homepage_logo;
    }

    static function register($fields) {
        // MAGICALLY put fields into current scope

        extract($fields);

        $group = new User_group();

        $group->query('BEGIN');

        if (empty($uri)) {
            // fill in later...
            $uri = null;
        }

        $group->nickname    = $nickname;
        $group->fullname    = $fullname;
        $group->homepage    = $homepage;
        $group->description = $description;
        $group->location    = $location;
        $group->uri         = $uri;
        $group->mainpage    = $mainpage;
        $group->created     = common_sql_now();

        $result = $group->insert();

        if (!$result) {
            common_log_db_error($group, 'INSERT', __FILE__);
            // TRANS: Server exception thrown when creating a group failed.
            throw new ServerException(_('Could not create group.'));
        }

        if (!isset($uri) || empty($uri)) {
            $orig = clone($group);
            $group->uri = common_local_url('groupbyid', array('id' => $group->id));
            $result = $group->update($orig);
            if (!$result) {
                common_log_db_error($group, 'UPDATE', __FILE__);
                // TRANS: Server exception thrown when updating a group URI failed.
                throw new ServerException(_('Could not set group URI.'));
            }
        }

        $result = $group->setAliases($aliases);

        if (!$result) {
            // TRANS: Server exception thrown when creating group aliases failed.
            throw new ServerException(_('Could not create aliases.'));
        }

        $member = new Group_member();

        $member->group_id   = $group->id;
        $member->profile_id = $userid;
        $member->is_admin   = 1;
        $member->created    = $group->created;

        $result = $member->insert();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Server exception thrown when setting group membership failed.
            throw new ServerException(_('Could not set group membership.'));
        }

        if ($local) {
            $local_group = new Local_group();

            $local_group->group_id = $group->id;
            $local_group->nickname = $nickname;
            $local_group->created  = common_sql_now();

            $result = $local_group->insert();

            if (!$result) {
                common_log_db_error($local_group, 'INSERT', __FILE__);
                // TRANS: Server exception thrown when saving local group information failed.
                throw new ServerException(_('Could not save local group info.'));
            }
        }

        $group->query('COMMIT');
        return $group;
    }

    /**
     * Handle cascading deletion, on the model of notice and profile.
     *
     * This should handle freeing up cached entries for the group's
     * id, nickname, URI, and aliases. There may be other areas that
     * are not de-cached in the UI, including the sidebar lists on
     * GroupsAction
     */
    function delete()
    {
        if ($this->id) {

            // Safe to delete in bulk for now

            $related = array('Group_inbox',
                             'Group_block',
                             'Group_member',
                             'Related_group');

            Event::handle('UserGroupDeleteRelated', array($this, &$related));

            foreach ($related as $cls) {

                $inst = new $cls();
                $inst->group_id = $this->id;

                if ($inst->find()) {
                    while ($inst->fetch()) {
                        $dup = clone($inst);
                        $dup->delete();
                    }
                }
            }

            // And related groups in the other direction...
            $inst = new Related_group();
            $inst->related_group_id = $this->id;
            $inst->delete();

            // Aliases and the local_group entry need to be cleared explicitly
            // or we'll miss clearing some cache keys; that can make it hard
            // to create a new group with one of those names or aliases.
            $this->setAliases(array());
            $local = Local_group::staticGet('group_id', $this->id);
            if ($local) {
                $local->delete();
            }

            // blow the cached ids
            self::blow('user_group:notice_ids:%d', $this->id);

        } else {
            common_log(LOG_WARN, "Ambiguous user_group->delete(); skipping related tables.");
        }
        parent::delete();
    }
}
