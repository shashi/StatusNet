<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Allows users to flag content and accounts as offensive/spam/whatever
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Allows users to flag content and accounts as offensive/spam/whatever
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class UserFlagPlugin extends Plugin
{
    const REVIEWFLAGS = 'UserFlagPlugin::reviewflags';
    const CLEARFLAGS  = 'UserFlagPlugin::clearflags';

    public $flagOnBlock = true;

    /**
     * Hook for ensuring our tables are created
     *
     * Ensures that the user_flag_profile table exists
     * and has the right columns.
     *
     * @return boolean hook return
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles

        $schema->ensureTable('user_flag_profile',
                             array(new ColumnDef('profile_id', 'integer', null,
                                                 false, 'PRI'),
                                   new ColumnDef('user_id', 'integer', null,
                                                 false, 'PRI'),
                                   new ColumnDef('created', 'datetime', null,
                                                 false, 'MUL'),
                                   new ColumnDef('cleared', 'datetime', null,
                                                 true, 'MUL')));

        return true;
    }

    /**
     * Add our actions to the URL router
     *
     * @param Net_URL_Mapper $m URL mapper for this hit
     *
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('main/flag/profile', array('action' => 'flagprofile'));
        $m->connect('main/flag/clear', array('action' => 'clearflag'));
        $m->connect('admin/profile/flag', array('action' => 'adminprofileflag'));
        return true;
    }

    /**
     * Auto-load our classes if called
     *
     * @param string $cls Class to load
     *
     * @return boolean hook return
     */
    function onAutoload($cls)
    {
        switch (strtolower($cls))
        {
        case 'flagprofileaction':
        case 'adminprofileflagaction':
        case 'clearflagaction':
            include_once INSTALLDIR.'/plugins/UserFlag/' .
              strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'flagprofileform':
        case 'clearflagform':
            include_once INSTALLDIR.'/plugins/UserFlag/' . strtolower($cls . '.php');
            return false;
        case 'user_flag_profile':
            include_once INSTALLDIR.'/plugins/UserFlag/'.ucfirst(strtolower($cls)).'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Add a 'flag' button to profile page
     *
     * @param Action  &$action The action being called
     * @param Profile $profile Profile being shown
     *
     * @return boolean hook result
     */
    function onEndProfilePageActionsElements(&$action, $profile)
    {
        $user = common_current_user();

        if (!empty($user) && ($user->id != $profile->id)) {

            $action->elementStart('li', 'entity_flag');

            if (User_flag_profile::exists($profile->id, $user->id)) {
                // @todo FIXME: Add a title explaining what 'flagged' means?
                // TRANS: Message added to a profile if it has been flagged for review.
                $action->element('p', 'flagged', _('Flagged'));
            } else {
                $form = new FlagProfileForm($action, $profile,
                                            array('action' => 'showstream',
                                                  'nickname' => $profile->nickname));
                $form->show();
            }

            $action->elementEnd('li');
        }

        return true;
    }

    /**
     * Add a 'flag' button to profiles in a list
     *
     * @param ProfileListItem $item item being shown
     *
     * @return boolean hook result
     */
    function onEndProfileListItemActionElements($item)
    {
        $user = common_current_user();

        if (!empty($user)) {

            list($action, $args) = $item->action->returnToArgs();

            $args['action'] = $action;

            $form = new FlagProfileForm($item->action, $item->profile, $args);

            $item->action->elementStart('li', 'entity_flag');
            $form->show();
            $item->action->elementEnd('li');
        }

        return true;
    }

    /**
     * Initialize any flagging buttons on the page
     *
     * @param Action $action action being shown
     *
     * @return boolean hook result
     */
    function onEndShowScripts($action)
    {
        $action->inlineScript('if ($(".form_entity_flag").length > 0) { '.
                              '$(".form_entity_flag").bind("click", function() {'.
                              'SN.U.FormXHR($(this)); return false; }); }');
        return true;
    }

    /**
     * Check whether a user has one of our defined rights
     *
     * We define extra rights; this function checks to see if a
     * user has one of them.
     *
     * @param User    $user    User being checked
     * @param string  $right   Right we're checking
     * @param boolean &$result out, result of the check
     *
     * @return boolean hook result
     */
    function onUserRightsCheck($user, $right, &$result)
    {
        switch ($right) {
        case self::REVIEWFLAGS:
        case self::CLEARFLAGS:
            $result = $user->hasRole('moderator');
            return false; // done processing!
        }

        return true; // unchanged!
    }

    /**
     * Optionally flag profile when a block happens
     *
     * We optionally add a flag when a profile has been blocked
     *
     * @param User    $user    User doing the block
     * @param Profile $profile Profile being blocked
     *
     * @return boolean hook result
     */
    function onEndBlockProfile($user, $profile)
    {
        if ($this->flagOnBlock && !User_flag_profile::exists($profile->id,
                                                             $user->id)) {

            User_flag_profile::create($user->id, $profile->id);
        }
        return true;
    }

    /**
     * Ensure that flag entries for a profile are deleted
     * along with the profile when deleting users.
     * This prevents breakage of the admin profile flag UI.
     *
     * @param Profile $profile
     * @param array &$related list of related tables; entries
     *              with matching profile_id will be deleted.
     *
     * @return boolean hook result
     */
    function onProfileDeleteRelated($profile, &$related)
    {
        $related[] = 'user_flag_profile';
        return true;
    }

    /**
     * Ensure that flag entries created by a user are deleted
     * when that user gets deleted.
     *
     * @param User $user
     * @param array &$related list of related tables; entries
     *              with matching user_id will be deleted.
     *
     * @return boolean hook result
     */
    function onUserDeleteRelated($user, &$related)
    {
        $related[] = 'user_flag_profile';
        return true;
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:UserFlag';

        $versions[] = array('name' => 'UserFlag',
            'version' => STATUSNET_VERSION,
            'author' => 'Evan Prodromou',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('This plugin allows flagging of profiles for review and reviewing flagged profiles.'));

        return true;
    }
}
