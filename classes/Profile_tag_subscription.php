<?php
/**
 * Table Definition for profile_tag_subscription
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile_tag_subscription extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile_tag_subscription';                     // table name
    public $profile_tag_id;                         // int(4)  not_null
    public $profile_id;                             // int(4)  not_null
    public $created;                                // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                               // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Profile_tag_subscription',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Profile_tag_subscription', $kv);
    }

    static function add($people_tag_id, $profile_id)
    {
        $sub = new People_tag_subscription();

        $sub->profile_tag_id   = $people_tag_id;
        $sub->profile_id = $profile_id;
        $sub->created    = common_sql_now();

        $result = $sub->insert();

        if (!$result) {
            common_log_db_error($sub, 'INSERT', __FILE__);
            throw new Exception(_("Adding people tag subscription failed."));
        }

        return true;
    }

    static function remove($profile_tag_id, $profile_id)
    {
        $sub = Profile_tag_subcscription::pkeyGet(array('profile_tag_id' => $profile_tag_id,
                                              'profile_id' => $profile_id));

        if (empty($sub)) {
            throw new Exception(_("Not subcribed to people tag."));
        }

        $result = $sub->delete();

        if (!$result) {
            common_log_db_error($sub, 'DELETE', __FILE__);
            throw new Exception(_("Removing people tag subscription failed."));
        }

        return true;
    }
}
