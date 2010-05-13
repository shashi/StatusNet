<?php
/**
 * Table Definition for profile_tag_map
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_tag_map extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag';                     // table name
    public $tagger;                          // int(4)  not_null
    public $tagged;                          // int(4)  not_null
    public $tag;                             // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Profile_tag_map',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function getTags($tagger, $tagged) {

        $tags = array();

        # XXX: really store this in memcached

        $profile_tag_map = new Profile_tag_map();
        $profile_tag_map->tagger = $tagger;
        $profile_tag_map->tagged = $tagged;

        $profile_tag->find();

        while ($profile_tag_map->fetch()) {
            $profile_tag = Profile_tag::getStatic('id', $profile_tag_map->tag);
            $tags[] = $profile_tag->tag;
        }

        $profile_tag->free();
        $profile_tag_map->free();

        return $tags;
    }

    static function setTags($tagger, $tagged, $newtags) {

        $newtags = array_unique($newtags);
        $oldtags = Profile_tag_map::getTags($tagger, $tagged);

        # Delete stuff that's old that not in new

        $to_delete = array_diff($oldtags, $newtags);

        # Insert stuff that's in new and not in old

        $to_insert = array_diff($newtags, $oldtags);

        $profile_tag_map = new Profile_tag_map();

        $profile_tag_map->tagger = $tagger;
        $profile_tag_map->tagged = $tagged;

        $profile_tag_map->query('BEGIN');

        foreach ($to_delete as $deltag) {
            $ptag = Profile_tag::getByTaggerAndTag($tagger, $deltag);
            $profile_tag_map->tag = $ptag->id;
            $result = $profile_tag->delete();
            if (!$result) {
                common_log_db_error($profile_tag_map, 'DELETE', __FILE__);
                return false;
            }
            Profile_tag::cleanupTag($tagger, $deltag);
        }

        foreach ($to_insert as $instag) {
            $ptag = Profile_tag::ensureTag($tagger, $instag);
            $profile_tag_map->tag = $ptag->id;
            $result = $profile_tag_map->insert();
            if (!$result) {
                common_log_db_error($profile_tag_map, 'INSERT', __FILE__);
                return false;
            }
        }

        $profile_tag_map->query('COMMIT');

        return true;
    }

    # Return profiles with a given tag
    static function getTagged($tagger, $tag) {
        $profile = new Profile();
        $tag = Profile_tag::getByTaggerAndTag($tagger, $tag)->id;
        $profile->query('SELECT profile.* ' .
                        'FROM profile JOIN profile_tag_map ' .
                        'ON profile.id = profile_tag_map.tagged ' .
                        'WHERE profile_tag_map.tagger = ' . $tagger . ' ' .
                        'AND profile_tag_map.tag = "' . $tag . '" ');
        $tagged = array();
        while ($profile->fetch()) {
            $tagged[] = clone($profile);
        }
        return $tagged;
    }
}
