<?php
/**
 * Table Definition for profile_list
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_list extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_list';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $tagger;                          // int(4)
    public $tag;                             // varchar(64)
    public $description;                     // text
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP
    public $uri;                             // varchar(255)  unique_key
    public $mainpage;                        // varchar(255)

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Profile_list',$k,$v); }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Profile_list', $kv);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function getUri()
    {
        $uri = null;
        if (Event::handle('StartProfiletagGetUri', array($this, &$uri))) {
            if (!empty($this->uri)) {
                $uri = $this->uri;
            } else {
                $uri = common_local_url('profiletagbyid',
                                        array('id' => $this->id, 'tagger_id' => $this->tagger));
            }
        }
        Event::handle('EndProfiletagGetUri', array($this, &$uri));
        return $uri;
    }

    function permalink()
    {
        $url = null;
        if (Event::handle('StartProfiletagPermalink', array($this, &$url))) {
            $url = common_local_url('profiletagbyid',
                                    array('id' => $this->id));
        }
        Event::handle('EndProfiletagPermalink', array($this, &$url));
        return $url;
    }

    function getNotices($offset, $limit, $since_id=null, $max_id=null)
    {
        $ids = Notice::stream(array($this, '_streamDirect'),
                              array(),
                              'profile_tag:notice_ids:' . $this->id,
                              $offset, $limit, $since_id, $max_id);

        return Notice::getStreamByIds($ids);
    }

    function _streamDirect($offset, $limit, $since_id, $max_id)
    {
        $inbox = new Profile_tag_inbox();

        $inbox->profile_tag_id = $this->id;

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

    function getSubscribers($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN profile_tag_subscription '.
          'ON profile.id = profile_tag_subscription.profile_id ' .
          'WHERE profile_tag_subscription.profile_tag_id = %d ' .
          'ORDER BY profile_tag_subscription.created DESC ';

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $subs = new Profile();

        $subs->query(sprintf($qry, $this->id));
        return $subs;
    }

    function getUserSubscribers()
    {
        // XXX: cache this

        $user = new User();
        if(common_config('db','quote_identifiers'))
            $user_table = '"user"';
        else $user_table = 'user';

        $qry =
          'SELECT id ' .
          'FROM '. $user_table .' JOIN profile_tag_subscription '.
          'ON '. $user_table .'.id = profile_tag_subscription.profile_id ' .
          'WHERE profile_tag_subscription.profile_tag_id = %d ';

        $user->query(sprintf($qry, $this->id));

        $ids = array();

        while ($user->fetch()) {
            $ids[] = $user->id;
        }

        $user->free();

        return $ids;
    }

    function getTagged($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN profile_tag '.
          'ON profile.id = profile_tag.tagged ' .
          'WHERE profile_tag.tagger = %d ' .
          'AND profile_tag.tag = "%s" ' .
          'ORDER BY profile_tag.modified DESC ';

        if ($limit != null) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $members = new Profile();

        $members->query(sprintf($qry, $this->tagger, $this->tag));
        return $members;
    }

    function subscriberCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $cnt = $c->get(common_cache_key('profile_list:subscriber_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Profile_tag_subscription();
        $sub->profile_tag_id = $this->id;

        $cnt = (int) $sub->count('distinct profile_id');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(common_cache_key('profile_list:subscriber_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function blowSubscriberCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $c->delete(common_cache_key('profile_list:subscriber_count:'.$this->id));
        }
    }

    function taggedCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $cnt = $c->get(common_cache_key('profile_list:tagged_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $tag = new Profile_tag();
        $tag->tag = $this->tag;
        $tag->tagger = $this->tagger;

        $cnt = (int) $tag->count('distinct tagged');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(common_cache_key('profile:tagged_count:'.$this->id), $cnt);
        }

        return $cnt;
    }

    function blowTaggedCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $c->delete(common_cache_key('profile_list:tagged_count:'.$this->id));
        }
    }

    function delete()
    {
        Profile_tag::deleteTag($this->tagger, $this->tag);
        parent::delete();
    }

    function update($orig=null)
    {
        $result = true;

        if (!is_object($orig) && !$orig instanceof Profile_list) {
            parent::update($orig);
        }

        // if original tag was different
        // check to see if the new tag already exists
        // if not, rename the tag correctly
        if($orig->tag != $this->tag || $orig->tagger != $this->tagger) {
            $existing = Profile_list::getByTaggerAndTag($this->tagger, $this->tag);
            if(!empty($existing)) {
                throw new ServerException(_('The tag you are trying to rename ' .
                                            'to already exists.'));
            }
            // move the tag
            $result = Profile_tag::moveTag($orig->tag, $this->tag, $orig->tagger, $this->tagger);
        }
        parent::update($orig);
        return $result;
    }

    static function getByTaggerAndTag($tagger, $tag)
    {
        $ptag = Profile_list::pkeyGet(array('tagger' => $tagger, 'tag' => $tag));
        return $ptag;
    }

    /* create the tag if it does not exist, return it */
    static function ensureTag($tagger, $tag, $description=null)
    {
        $ptag = Profile_list::getByTaggerAndTag($tagger, $tag);

        if(empty($ptag->id)) {
            $args = array(
                'tag' => $tag,
                'tagger' => $tagger,
                'description' => $description
            );

            $new_tag = Profile_list::saveNew($args);

            return $new_tag;
        }
        return $ptag;
    }

    /* if there isn't use for a tag, delete it. Should be called after an untag
       return the object if not deleted, false if deleted.
    */
    static function cleanupTag($tagger, $tag)
    {
        $existing_tags = Profile_tag::getTagged($tagger, $tag);
        if(empty($existing_tags)) {
            $del_tag = new Profile_list();
            $del_tag->tagger = $tagger;
            $del_tag->tag = $tag;
            $result = $del_tag->delete();
            if (!$result) {
                common_log_db_error($del_tag, 'DELETE', __FILE__);
                return Profile_list::getByTaggerAndTag($tagger, $tag);
            }
            return false;
        }
        return Profile_list::getByTaggerAndTag($tagger, $tag);
    }

    static function maxDescription()
    {
        $desclimit = common_config('profiletag', 'desclimit');
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

    static function saveNew($fields) {

        extract($fields);

        $ptag = new Profile_list();

        $ptag->query('BEGIN');

        if (empty($uri)) {
            // fill in later...
            $uri = null;
        }

        if (empty($mainpage)) {
            $mainpage = null;
        }

        $ptag->tagger      = $tagger;
        $ptag->tag         = $tag;
        $ptag->description = $description;
        $ptag->uri         = $uri;
        $ptag->mainpage    = $mainpage;
        $ptag->created     = common_sql_now();
        $ptag->modified    = common_sql_now();

        $result = $ptag->insert();

        if (!$result) {
            common_log_db_error($ptag, 'INSERT', __FILE__);
            throw new ServerException(_('Could not create profile tag.'));
        }

        if (!isset($uri) || empty($uri)) {
            $orig = clone($ptag);
            $ptag->uri = common_local_url('profiletagbyid', array('id' => $ptag->id, 'tagger_id' => $ptag->tagger));
            $result = $ptag->update($orig);
            if (!$result) {
                common_log_db_error($ptag, 'UPDATE', __FILE__);
                throw new ServerException(_('Could not set profile tag URI.'));
            }
        }

        if (!isset($mainpage) || empty($mainpage)) {
            $orig = clone($ptag);
            $user = User::staticGet('id', $ptag->tagger);
            if(!empty($user)) {
                $ptag->mainpage = common_local_url('showprofiletag', array('tag' => $ptag->tag, 'tagger' => $user->nickname));
            }
            $result = $ptag->update($orig);
            if (!$result) {
                common_log_db_error($ptag, 'UPDATE', __FILE__);
                throw new ServerException(_('Could not set profile tag mainpage.'));
            }
        }
        return $ptag;
    }

    /**
     * get all lists at given cursor position for api
     *
     * $fn is a function that takes the following arguments in order:
     *      $offset, $limit, $since_id, $max_id
     * and returns a Profile_list object after making the DB query
     *
     * @returns array(array lists, int next_cursor, int previous_cursor)
     */

    static function getAtCursor($fn, $cursor, $count=20)
    {
        $lists = array();

        $since_id = 0;
        $max_id = 0;
        $next_cursor = 0;
        $prev_cursor = 0;

        // if cursor is zero show an empty list
        if ($cursor==0) {
            return array(array(), 0, 0);
        } else if($cursor > 0) {
            // if cursor is +ve fetch $count+1 lists before cursor,
            $max_id = $cursor;
            $list = call_user_func($fn, 0, $count+1, 0, $max_id);
            while($list->fetch()) {
                $lists[] = clone($list);
            }

            if(count($lists)==$count+1) {
                $next = array_pop($lists);
                $next_cursor = $next->cursor;
            }

            // and one list after cursor
            $prev = call_user_fucnc($fn, 0, 1, $cursor);
            while($prev->fetch()) {
                $prev_cursor = -1*$lists[0]->cursor;
            }

            return array($lists, $next_cursor, $prev_cursor);

        } else if($cursor < -1) {
            // if cursor is -ve fetch $count+2 lists created after -cursor-1,
            $since_id = abs($cursor)-1;

            $list = call_user_func($fn, 0, $count+2, $since_id);
            while($list->fetch()) {
                $lists[] = clone($list);
            }

            if($lists[count($lists)-1]->cursor == $cursor) {
                // this means there exists a next page
                $next = array_pop($lists);
                $next_cursor = $next->cursor;
            }

            if(count($lists) == $count+1) {
                $prev = array_shift($lists);
                $prev_cursor = -1*$prev->cursor;
            }
            return array($lists, $next_cursor, $prev_cursor);
        }
        else if($cursor == -1) {
            $list = call_user_func($fn, 0, $count+1);

            while($list->fetch()) {
                $lists[] = clone($list);
            }

            if(count($lists)==$count+1) {
                $next = array_pop($lists);
                $next_cursor = $next->cursor;
            }

            return array($lists, $next_cursor, $prev_cursor);
        }
    }
}
