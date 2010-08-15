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

    function getMeta()
    {
        return Profile_list::pkeyGet(array('tagger' => $this->tagger, 'tag' => $this->tag));
    }

    static function getTags($tagger, $tagged, $auth_user=null) {

        $profile_list = new Profile_list();
        $include_priv = 1;

        if (!($auth_user instanceof User ||
            $auth_user instanceof Profile) ||
            ($auth_user->id !== $tagger)) {

            $profile_list->private = false;
            $include_priv = 0;
        }

        $key = sprintf('profile_tag:tagger_tagged_privacy:%d-%d-%d', $tagger, $tagged, $include_priv);
        $tags = Profile_list::getCached($key);
        if ($tags !== false) {
            return $tags;
        }

        $profile_tag = new Profile_tag();
        $profile_list->tagger = $tagger;
        $profile_tag->tagged = $tagged;

        $profile_list->selectAdd();

        // only fetch id, tag, mainpage and
        // private hoping this will be faster
        $profile_list->selectAdd('profile_list.id, ' .
                                 'profile_list.tag, ' .
                                 'profile_list.mainpage, ' .
                                 'profile_list.private');
        $profile_list->joinAdd($profile_tag);
        $profile_list->find();

        Profile_list::setCache($key, $profile_list);

        return $profile_list;
    }

    static function getTagsArray($tagger, $tagged, $auth_user_id=null)
    {
        $ptag = new Profile_tag();
        $ptag->tagger = $tagger;
        $ptag->tagged = $tagged;

        if ($tagger != $auth_user_id) {
            $list = new Profile_list();
            $list->private = false;
            $ptag->joinAdd($list);
            $ptag->selectAdd();
            $ptag->selectAdd('profile_tag.tag');
        }

        $tags = array();
        $ptag->find();
        while ($ptag->fetch()) {
            $tags[] = $ptag->tag;
        }
        $ptag->free();

        return $tags;
    }

    static function setTags($tagger, $tagged, $newtags, $privacy=array()) {

        $newtags = array_unique($newtags);
        $oldtags = self::getTagsArray($tagger, $tagged, $tagger);

        $ptag = new Profile_tag();

        # Delete stuff that's in old and not in new

        $to_delete = array_diff($oldtags, $newtags);

        # Insert stuff that's in new and not in old

        $to_insert = array_diff($newtags, $oldtags);

        foreach ($to_delete as $deltag) {
            self::unTag($tagger, $tagged, $deltag);
        }

        foreach ($to_insert as $instag) {
            $private = isset($privacy[$instag]) ? $privacy[$instag] : false;
            self::setTag($tagger, $tagged, $instag, null, $private);
        }
        return true;
    }

    # set a single tag
    static function setTag($tagger, $tagged, $tag, $desc=null, $private=false) {

        $ptag = Profile_tag::pkeyGet(array('tagger' => $tagger,
                                           'tagged' => $tagged,
                                           'tag' => $tag));

        # if tag already exists, return it
        if(!empty($ptag)) {
            return $ptag;
        }

        if (Event::handle('StartTagProfile', array($tagger, $tag))) {
            $tagger_profile = Profile::staticGet('id', $tagger);
            $tagged_profile = Profile::staticGet('id', $tagged);

            if (!$tagger_profile->canTag($tagged_profile)) {
                throw new ClientException(_('You cannot tag this user.'));
                return false;
            }

            $tags = new Profile_list();
            $tags->tagger = $tagger;
            $count = (int) $tags->count('distinct tag');

            if ($count >= common_config('peopletag', 'maxtags')) {
                throw new ClientException(sprintf(_('You already have created %d or more tags ' .
                                                    'which is the maximum allowed number of tags. ' .
                                                    'Try using or deleting some existing tags.'),
                                                    common_config('peopletag', 'maxtags')));
                return false;
            }

            $plist = new Profile_list();
            $plist->query('BEGIN');

            $profile_list = Profile_list::ensureTag($tagger, $tag, $desc, $private);

            if ($profile_list->taggedCount() >= common_config('peopletag', 'maxpeople')) {
                throw new ClientException(sprintf(_('You already have %d or more people tagged %s ' .
                                                    'which is the maximum allowed number.' .
                                                    'Try untagging others with the same tag first.'),
                                                    common_config('peopletag', 'maxpeople'), $tag));
                return false;
            }

            $newtag = new Profile_tag();

            $newtag->tagger = $tagger;
            $newtag->tagged = $tagged;
            $newtag->tag = $tag;

            $result = $newtag->insert();


            if (!$result) {
                common_log_db_error($newtag, 'INSERT', __FILE__);
                return false;
            }

            try {
                $plist->query('COMMIT');
                Event::handle('EndTagProfile', array($newtag));
            } catch (Exception $e) {
                $newtag->delete();
                $profile_list->delete();
                throw $e;
                return false;
            }

            $profile_list->taggedCount(true);
            self::blowCaches($tagger, $tagged);
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
            if (!$result) {
                common_log_db_error($this, 'DELETE', __FILE__);
                return false;
            }
            Event::handle('EndUntagProfile', array($orig));
            if ($result) {
                $profile_list = Profile_list::pkeyGet(array('tag' => $tag, 'tagger' => $tagger));
                $profile_list->taggedCount(true);
                self::blowCaches($tagger, $tagged);
                return true;
            }
            return false;
        }
    }

    // @fixme: move this to Profile_list?
    static function cleanup($profile_list) {
        $ptag = new Profile_tag();
        $ptag->tagger = $profile_list->tagger;
        $ptag->tag = $profile_list->tag;
        $ptag->find();

        while($ptag->fetch()) {
            if (Event::handle('StartUntagProfile', array($ptag))) {
                $orig = clone($ptag);
                $result = $ptag->delete();
                if (!$result) {
                    common_log_db_error($this, 'DELETE', __FILE__);
                }
                Event::handle('EndUntagProfile', array($orig));
            }
        }
    }

    // move a tag!
    static function moveTag($orig, $new) {
        $tags = new Profile_tag();
        $qry = 'UPDATE profile_tag SET ' .
               'tag = "%s", tagger = "%s" ' .
               'WHERE tag = "%s" ' .
               'AND tagger = "%s"';
        $result = $tags->query(sprintf($qry, $new->tag, $new->tagger,
                                             $orig->tag, $orig->tagger));

        if (!$result) {
            common_log_db_error($tags, 'UPDATE', __FILE__);
            return false;
        }
        return true;
    }

    static function blowCaches($tagger, $tagged) {
        foreach (array(0, 1) as $perm) {
            self::blow(sprintf('profile_tag:tagger_tagged_privacy:%d-%d-%d', $tagger, $tagged, $perm));
        }
        return true;
    }
}
