<?php
/**
 * Table Definition for profile_tag
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_tag extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $user_id;                         // int(4)
    public $tag;                             // varchar(64)
    public $description;                     // text
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP
    public $uri;                             // varchar(255)  unique_key
    public $mainpage;                        // varchar(255)

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Profile_tag',$k,$v); }

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
                                        array('id' => $this->id));
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

    function getTagged($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN profile_tag_map '.
          'ON profile.id = profile_tag_map.tagged ' .
          'WHERE profile_tag_map.profile_tag_id = %d ' .
          'ORDER BY profile_tag_map.created DESC ';

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

    static function getByTaggerAndTag($tagger, $tag)
    {
        $ptag = new Profile_tag();
        $ptag->tagger = $tagger;
        $ptag->tag = $tag;
        $ptag->find();
        $ptag->fetch();
        return $ptag;
    }

    /* create the tag if it does not exist, return it */
    static function ensureTag($tagger, $tag)
    {
        $ptag = Profile_tag::getByTaggerAndTag($tagger, $tag);

        if(empty($ptag->id)) {
            $new_tag = new Profile_tag();
            $new_tag->tagger = $tagger;
            $new_tag->tag = $tag;
            $result = $new_tag->insert();
            if (!$result) {
                common_log_db_error($new_tag, 'INSERT', __FILE__);
                return false;
            }
            return $new_tag;
        }
        return $ptag;
    }

    /* if there isn't use for a tag, delete it. Should be called after an untag
       return true if deleted.
    */
    static function cleanupTag($tagger, $tag)
    {
        $existing_tags = Profile_tag_map::getTagged($tagger, $tag);
        if(empty($existing_tags)) {
            $del_tag = new Profile_tag();
            $del_tag->tagger = $tagger;
            $del_tag->tag = $tag;
            $result = $del_tag->delete();
            if (!$result) {
                common_log_db_error($del_tag, 'DELETE', __FILE__);
                return false;
            }
            return true;
        }
        return false;
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

    static function register($fields) {

        // MAGICALLY put fields into current scope

        extract($fields);

        $ptag = new Profile_tag();

        $ptag->query('BEGIN');

        if (empty($uri)) {
            // fill in later...
            $uri = null;
        }

        $ptag->user_id     = $user_id;
        $ptag->tag         = $tag;
        $ptag->description = $description;
        $ptag->uri         = $uri;
        $ptag->mainpage    = $mainpage;
        $ptag->created     = common_sql_now();

        $result = $ptag->insert();

        if (!$result) {
            common_log_db_error($ptag, 'INSERT', __FILE__);
            throw new ServerException(_('Could not create profile tag.'));
        }

        if (!isset($uri) || empty($uri)) {
            $orig = clone($ptag);
            $ptag->uri = common_local_url('profiletagbyid', array('id' => $ptag->id));
            $result = $ptag->update($orig);
            if (!$result) {
                common_log_db_error($ptag, 'UPDATE', __FILE__);
                throw new ServerException(_('Could not set people tag URI.'));
            }
        }
    }
}
