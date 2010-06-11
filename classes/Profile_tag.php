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

        $profile_tag->query('BEGIN');

        foreach ($to_delete as $deltag) {
            $profile_tag->tag = $deltag;
            $result = $profile_tag->delete();

            if (!$result) {
                common_log_db_error($profile_tag, 'DELETE', __FILE__);
                return false;
            }

            # Delete tag metadata if no one is tagged
            $profile_list = Profile_list::cleanupTag($tagger, $deltag);

            if($profile_list) {
                $profile_list->blowTaggedCount();
            }
        }

        foreach ($to_insert as $instag) {
            $profile_tag->tag = $instag;
            $result = $profile_tag->insert();

            # Add if tag no one is tagged
            $profile_list = Profile_list::ensureTag($tagger, $instag);

            if (!$result) {
                common_log_db_error($profile_tag, 'INSERT', __FILE__);
                return false;
            }

            $profile_list->blowTaggedCount();
        }

        $profile_tag->query('COMMIT');

        return true;
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
}
