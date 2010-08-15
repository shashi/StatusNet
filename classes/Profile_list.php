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
    public $private;                         // tinyint(1)
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP
    public $uri;                             // varchar(255)  unique_key
    public $mainpage;                        // varchar(255)
    public $tagged_count;                    // smallint
    public $subscriber_count;                // smallint

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Profile_list',$k,$v); }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Profile_list', $kv);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE


    function getTagger()
    {
        return Profile::staticGet('id', $this->tagger);
    }

    function getBestName()
    {
        return $this->tag;
    }

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

    function homeUrl()
    {
        $url = null;
        if (Event::handle('StartUserPeopletagHomeUrl', array($this, &$url))) {
            // normally stored in mainpage, but older ones may be null
            if (!empty($this->mainpage)) {
                $url = $this->mainpage;
            } else {
                $url = common_local_url('showprofiletag',
                                        array('tagger' => $this->getTagger()->nickname,
                                              'tag'    => $this->tag));
            }
        }
        Event::handle('EndUserPeopletagHomeUrl', array($this, &$url));
        return $url;
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

    function getTagger()
    {
        return new Profile('id', $this->tagger);
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

    function getSubscribers($offset=0, $limit=null, $since=0, $upto=0)
    {
        $subs = new Profile();
        $sub = new Profile_tag_subscription();
        $sub->profile_tag_id = $this->id;

        $subs->joinAdd($sub);
        $subs->selectAdd('unix_timestamp(profile_tag_subscription.' .
                         'created) as "cursor"');

        if ($since != 0) {
            $subs->whereAdd('cursor > ' . $since);
        }

        if ($upto != 0) {
            $subs->whereAdd('cursor <= ' . $upto);
        }

        if ($limit != null) {
            $subs->limit($offset, $limit);
        }

        $subs->orderBy('"cursor" DESC');
        $subs->find();

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

    function hasSubscriber($id)
    {
        if (!is_numeric($id)) {
            $id = $id->id;
        }

        $sub = Profile_tag_subscription::pkeyGet(array('profile_tag_id' => $this->id,
                                                       'profile_id'     => $id));
        return !empty($sub);
    }

    function getTagged($offset=0, $limit=null, $since=0, $upto=0)
    {
        $tagged = new Profile();
        $tagged->joinAdd(array('id', 'profile_tag:tagged'));

        #@fixme: postgres
        $tagged->selectAdd('unix_timestamp(profile_tag.modified) as "cursor"');
        $tagged->whereAdd('profile_tag.tagger = '.$this->tagger);
        $tagged->whereAdd("profile_tag.tag = '{$this->tag}'");

        if ($since != 0) {
            $tagged->whereAdd('cursor > ' . $since);
        }

        if ($upto != 0) {
            $tagged->whereAdd('cursor <= ' . $upto);
        }

        if ($limit != null) {
            $tagged->limit($offset, $limit);
        }

        $tagged->orderBy('"cursor" DESC');
        $tagged->find();

        return $tagged;
    }

    function delete()
    {
        // force delete one item at a time.
        if (empty($this->id)) {
            $this->find();
            while ($this->fetch()) {
                $this->delete();
            }
        }

        Profile_tag::cleanup($this);
        Profile_tag_subscription::cleanup($this);

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
            // XXX: allow OStatus plugin to send out profile tag
            $result = Profile_tag::moveTag($orig, $this);
        }
        parent::update($orig);
        return $result;
    }

    function asAtomAuthor()
    {
        $xs = new XMLStringer(true);

        $tagger = $this->getTagger();
        $xs->elementStart('author');
        $xs->element('name', null, '@' . $tagger->nickname . '/' . $this->tag);
        $xs->element('uri', null, $this->permalink());
        $xs->elementEnd('author');

        return $xs->getString();
    }

    function asActivitySubject()
    {
        return $this->asActivityNoun('subject');
    }

    function asActivityNoun($element)
    {
        $noun = ActivityObject::fromPeopletag($this);
        return $noun->asString('activity:' . $element);
    }

    function taggedCount($recount=false)
    {
        if (!$recount) {
            return $this->tagged_count;
        }

        $tags = new Profile_tag();
        $tags->tag = $this->tag;
        $tags->tagger = $this->tagger;
        $orig = clone($this);
        $this->tagged_count = (int) $tags->count('distinct tagged');
        $this->update($orig);

        return $this->tagged_count;
    }

    function subscriberCount($recount=false)
    {
        if ($recount) {
            return $this->subscriber_count;
        }

        $sub = new Profile_tag_subscription();
        $sub->profile_tag_id = $this->id;
        $orig = clone($this);
        $this->subscriber_count = (int) $sub->count('distinct profile_id');
        $this->update($orig);

        return $this->subscriber_count;
    }

    static function getByTaggerAndTag($tagger, $tag)
    {
        $ptag = Profile_list::pkeyGet(array('tagger' => $tagger, 'tag' => $tag));
        return $ptag;
    }

    /* create the tag if it does not exist, return it */
    static function ensureTag($tagger, $tag, $description=null, $private=false)
    {
        $ptag = Profile_list::getByTaggerAndTag($tagger, $tag);

        if(empty($ptag->id)) {
            $args = array(
                'tag' => $tag,
                'tagger' => $tagger,
                'description' => $description,
                'private' => $private
            );

            $new_tag = Profile_list::saveNew($args);

            return $new_tag;
        }
        return $ptag;
    }

    static function maxDescription()
    {
        $desclimit = common_config('peopletag', 'desclimit');
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

        if (empty($tagger)) {
            throw new Exception(_('No tagger specified.'));
        }

        if (empty($tag)) {
            throw new Exception(_('No tag specified.'));
        }

        if (empty($mainpage)) {
            $mainpage = null;
        }

        if (empty($uri)) {
            // fill in later...
            $uri = null;
        }

        if (empty($mainpage)) {
            $mainpage = null;
        }

        if (empty($description)) {
            $description = null;
        }

        if (empty($private)) {
            $private = false;
        }

        $ptag->tagger      = $tagger;
        $ptag->tag         = $tag;
        $ptag->description = $description;
        $ptag->private     = $private;
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
            } else {
                $ptag->mainpage = $uri; // assume this is a remote peopletag and the uri works
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

    static function getAtCursor($fn, $args, $cursor, $count=20)
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
            $fn_args = array_merge($args, array(0, $count+1, 0, $max_id));
            $list = call_user_func_array($fn, $fn_args);
            while($list->fetch()) {
                $lists[] = clone($list);
            }

            if(count($lists)==$count+1) {
                $next = array_pop($lists);
                if(isset($next->cursor)) {
                    $next_cursor = $next->cursor;
                } else {
                    $next_cursor = $next->id;
                }
            }

            // and one list after cursor
            $fn_args = array_merge($args, array(0, 1, $cursor));
            $prev = call_user_func_array($fn, $fn_args);
            while($prev->fetch()) {
                if(isset($lists[0]->cursor)) {
                    $prev_cursor = -1*$lists[0]->cursor;
                }
                else {
                    $prev_cursor = -1*$lists[0]->id;
                }
            }

            return array($lists, $next_cursor, $prev_cursor);

        } else if($cursor < -1) {
            // if cursor is -ve fetch $count+2 lists created after -cursor-1,
            $since_id = abs($cursor)-1;

            $fn_args = array_merge($args, array(0, $count+2, $since_id));
            $list = call_user_func_array($fn, $fn_args);
            while($list->fetch()) {
                $lists[] = clone($list);
            }

            $cur = isset($lists[count($lists)-1]->cursor) ? $lists[count($lists)-1]->cursor :
                                $lists[count($lists)-1]->id;
            if($cur == $cursor) {
                // this means there exists a next page
                $next = array_pop($lists);
                if(isset($next->cursor)) {
                    $next_cursor = $next->cursor;
                } else {
                    $next_cursor = $next->id;
                }
            }

            if(count($lists) == $count+1) {
                $prev = array_shift($lists);
                if(isset($prev->cursor)) {
                    $prev_cursor = -1*$prev->cursor;
                } else {
                    $prev_cursor = -1*$prev->id;
                }
            }
            return array($lists, $next_cursor, $prev_cursor);
        }
        else if($cursor == -1) {
            $fn_args = array_merge($args, array(0, $count+1));
            $list = call_user_func_array($fn, $fn_args);

            while($list->fetch()) {
                $lists[] = clone($list);
            }

            if(count($lists)==$count+1) {
                $next = array_pop($lists);
                if(isset($next->cursor)) {
                    $next_cursor = $next->cursor;
                } else {
                    $next_cursor = $next->id;
                }
            }

            return array($lists, $next_cursor, $prev_cursor);
        }
    }

    static function setCache($ckey, &$tag, $offset=0, $limit=null) {
        $cache = common_memcache();
        if (empty($cache)) {
            return false;
        }
        $str = '';
        $tags = array();
        while ($tag->fetch()) {
            $str .= $tag->tagger . ':' . $tag->tag . ';';
            $tags[] = clone($tag);
        }
        $str = substr($str, 0, -1);
        if ($offset>=0 && !is_null($limit)) {
            $tags = array_slice($tags, $offset, $limit);
        }

        $tag = new ArrayWrapper($tags);

        return self::cacheSet($ckey, $str);
    }

    static function getCached($ckey, $offset=0, $limit=null) {

        $keys_str = self::cacheGet($ckey);
        if ($keys_str === false) {
            return false;
        }

        $pairs = explode(';', $key_str);
        $keys = array();
        foreach ($pairs as $pair) {
            $keys[] = explode(':', $pair);
        }

        if ($$offset>=0 && !is_null($limit)) {
            $keys = array_slice($keys, $offset, $limit);
        }
        return self::getByKeys($keys, $tagger);
    }

    static function getByKeys($keys) {
        $cache = common_memcache();

        if (!empty($cache)) {
            $tags = array();

            foreach ($keys as $key) {
                $t = Profile_list::getByTaggerAndTag($key[0], $key[1]);
                if (!empty($t)) {
                    $tags[] = $t;
                }
            }
            return new ArrayWrapper($tags);
        } else {
            $tag = new Profile_list();
            if (empty($keys)) {
                //if no IDs requested, just return the tag object
                return $tag;
            }

            $pairs = array();
            foreach ($keys as $key) {
                $pairs[] = '(' . $key[0] . ', "' . $key[1] . '")';
            }

            $tag->whereAdd('(tagger, tag) in (' . implode(', ', $pairs) . ')');

            $tag->find();

            $temp = array();

            while ($tag->fetch()) {
                $temp[$tag->tagger.'-'.$tag->tag] = clone($tag);
            }

            $wrapped = array();

            foreach ($keys as $key) {
                $id = $key[0].'-'.$key[1];
                if (array_key_exists($id, $temp)) {
                    $wrapped[] = $temp[$id];
                }
            }

            return new ArrayWrapper($wrapped);
        }
    }
}
