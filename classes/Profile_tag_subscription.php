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

    static function add($peopletag, $profile)
    {
        if ($peopletag->private) {
            return false;
        }

        if (Event::handle('StartSubscribePeopletag', array($peopletag, $profile))) {
            $args = array('profile_tag_id' => $peopletag->id,
                          'profile_id' => $profile->id);
            $existing = Profile_tag_subscription::pkeyGet($args);
            if(!empty($existing)) {
                return $existing;
            }

            $sub = new Profile_tag_subscription();
            $sub->profile_tag_id = $peopletag->id;
            $sub->profile_id = $profile->id;
            $sub->created = common_sql_now();

            $result = $sub->insert();

            if (!$result) {
                common_log_db_error($sub, 'INSERT', __FILE__);
                throw new Exception(_("Adding people tag subscription failed."));
            }

            $ptag = Profile_list::staticGet('id', $peopletag->id);
            $ptag->subscriberCount(true);

            Event::handle('EndSubscribePeopletag', array($peopletag, $profile));
            return $ptag;
        }
    }

    static function remove($peopletag, $profile)
    {
        $sub = Profile_tag_subscription::pkeyGet(array('profile_tag_id' => $peopletag->id,
                                              'profile_id' => $profile->id));

        if (empty($sub)) {
            // silence is golden?
            return true;
        }

        if (Event::handle('StartUnsubscribePeopletag', array($peopletag, $profile))) {
            $result = $sub->delete();

            if (!$result) {
                common_log_db_error($sub, 'DELETE', __FILE__);
                throw new Exception(_("Removing people tag subscription failed."));
            }

            $peopletag->subscriberCount(true);

            Event::handle('EndUnsubscribePeopletag', array($peopletag, $profile));
            return true;
        }
    }

    // called if a tag gets deleted / made private
    static function cleanup($profile_list) {
        $subs = new self();
        $subs->profile_tag_id = $profile_list->id;
        $subs->find();

        while($subs->fetch()) {
            $profile = Profile::staticGet('id', $subs->profile_id);
            Event::handle('StartUnsubscribePeopletag', array($profile_list, $profile));
            // Delete anyway
            $subs->delete();
            Event::handle('StartUnsubscribePeopletag', array($profile_list, $profile));
        }
    }
}
