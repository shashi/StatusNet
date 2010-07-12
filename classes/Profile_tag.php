<?php
/**
 * Table Definition for profile_tag
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_tag extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag';                     // table name
    public $tagger;                          // int(4)  primary_key not_null
    public $tagged;                          // int(4)  primary_key not_null
    public $tag;                             // varchar(64)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Profile_tag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv) {
        return Memcached_DataObject::pkeyGet('Profile_tag', $kv);
    }

    function links()
    {
        return array('tagger,tag' => 'profile_list:tagger,tag');
    }

    static function getTags($tagger, $tagged) {

        $tags = array();

        # XXX: store this in memcached

        $profile_tag = new Profile_tag();
        $profile_tag->tagger = $tagger;
        $profile_tag->tagged = $tagged;

        $profile_tag->find();

        while ($profile_tag->fetch()) {
            $tags[] = $profile_tag->tag;
        }

        $profile_tag->free();

        return $tags;
    }

    static function setTags($tagger, $tagged, $newtags) {

        $newtags = array_unique($newtags);
        $oldtags = Profile_tag::getTags($tagger, $tagged);

        # Delete stuff that's in old and not in new

        $to_delete = array_diff($oldtags, $newtags);

        # Insert stuff that's in new and not in old

        $to_insert = array_diff($newtags, $oldtags);

        $profile_tag = new Profile_tag();

        $profile_tag->tagger = $tagger;
        $profile_tag->tagged = $tagged;

        foreach ($to_delete as $deltag) {
            self::unTag($tagger, $tagged, $deltag);
        }

        foreach ($to_insert as $instag) {
            self::setTag($tagger, $tagged, $instag);
        }

        return true;
    }

    # set a single tag
    static function setTag($tagger, $tagged, $tag) {

        $ptag = Profile_tag::pkeyGet(array('tagger' => $tagger,
                                           'tagged' => $tagged,
                                           'tag' => $tag));

        # if tag already exists, return it
        if(!empty($ptag)) {
            return $ptag;
        }

        if (Event::handle('StartTagProfile', array($tagger, $tag))) {
            $profile_list = Profile_list::ensureTag($tagger, $tag);

            $newtag = new Profile_tag();

            $newtag->tagger = $tagger;
            $newtag->tagged = $tagged;
            $newtag->tag = $tag;

            $result = $newtag->insert();
            if (!$result) {
                common_log_db_error($newtag, 'INSERT', __FILE__);
                return false;
            }

            if(!Event::handle('EndTagProfile', array($newtag))) {
                $newtag->delete();
                return false;
            }
            $profile_list->blowTaggedCount();
        }

        return $newtag;
    }

    static function unTag($tagger, $tagged, $tag) {
        $ptag = Profile_tag::pkeyGet(array('tagger' => $tagger,
                                           'tagged' => $tagged,
                                           'tag'    => $tag));
        if (!$ptag) {
            return true;
        }

        if (Event::handle('StartUntagProfile', array($ptag))) {
            $orig = clone($ptag);
            $result = $ptag->delete();
            Event::handle('EndUntagProfile', array($orig));
            return $result;
        }
    }

    # Return profiles with a given tag
    static function getTagged($tagger, $tag) {
        $profile = new Profile();
        $profile->query('SELECT profile.* ' .
                        'FROM profile JOIN profile_tag ' .
                        'ON profile.id = profile_tag.tagged ' .
                        'WHERE profile_tag.tagger = ' . $tagger . ' ' .
                        'AND profile_tag.tag = "' . $tag . '" ');
        $tagged = array();
        while ($profile->fetch()) {
            $tagged[] = clone($profile);
        }
        return $tagged;
    }

    // @fixme: move this to Profile_list?
    static function deleteTag($tagger, $tag) {
        $tags = new Profile_tag();
        $tags->tagger = $tagger;
        $tags->tag = $tag;
        $result = $tags->delete();

        if (!$result) {
            common_log_db_error($tags, 'DELETE', __FILE__);
            return false;
        }
        return true;
    }

    // move a tag!
    static function moveTag($orig_tag, $new_tag, $orig_tagger, $new_tagger) {
        $tags = new Profile_tag();
        $qry = 'UPDATE profile_tag SET ' .
               'tag = "%s", tagger = "%s" ' .
               'WHERE tag = "%s" ' .
               'AND tagger = "%s"';
        $result = $tags->query(sprintf($qry, $new_tag, $new_tagger,
                                             $orig_tag, $orig_tagger));

        if (!$result) {
            common_log_db_error($tags, 'UPDATE', __FILE__);
            return false;
        }
        return true;
    }

    function delete()
    {
        $tagger = $this->tagger;
        $tag    = $this->tag;

        $result = parent::delete();
        if (!$result) {
            common_log_db_error($this, 'DELETE', __FILE__);
            return false;
        }

        $record = Profile_list::cleanupTag($tagger, $tag);
        if ($record) {
            $record->blowTaggedCount();
        }
        return true;
    }
}
