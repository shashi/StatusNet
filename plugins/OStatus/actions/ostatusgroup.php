<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Key UI methods:
 *
 *  showInputForm() - form asking for a remote profile account or URL
 *                    We end up back here on errors
 *
 *  showPreviewForm() - surrounding form for preview-and-confirm
 *    preview() - display profile for a remote group
 *
 *  success() - redirects to groups page on join
 */
class OStatusGroupAction extends OStatusSubAction
{
    protected $profile_uri; // provided acct: or URI of remote entity
    protected $oprofile; // Ostatus_profile of remote entity, if valid


    function validateRemoteProfile()
    {
        if (!$this->oprofile->isGroup()) {
            // Send us to the user subscription form for conf
            $target = common_local_url('ostatussub', array(), array('profile' => $this->profile_uri));
            common_redirect($target, 303);
        }
    }

    /**
     * Show the initial form, when we haven't yet been given a valid
     * remote profile.
     */
    function showInputForm()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_ostatus_sub',
                                          'class' => 'form_settings',
                                          'action' => $this->selfLink()));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('profile',
                     // TRANS: Field label.
                     _m('Join group'),
                     $this->profile_uri,
                     // TRANS: Tooltip for field label "Join group".
                     _m("OStatus group's address, like http://example.net/group/nickname."));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        // TRANS: Button text.
        $this->submit('validate', _m('BUTTON','Continue'));

        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Show a preview for a remote group's profile
     * @return boolean true if we're ok to try joining
     */
    function preview()
    {
        $oprofile = $this->oprofile;
        $group = $oprofile->localGroup();

        $cur = common_current_user();
        if ($cur->isMember($group)) {
            $this->element('div', array('class' => 'error'),
                           _m("You are already a member of this group."));
            $ok = false;
        } else {
            $ok = true;
        }

        $this->showEntity($group,
                          $group->homeUrl(),
                          $group->homepage_logo,
                          $group->description);
        return $ok;
    }

    /**
     * Redirect on successful remote group join
     */
    function success()
    {
        $cur = common_current_user();
        $url = common_local_url('usergroups', array('nickname' => $cur->nickname));
        common_redirect($url, 303);
    }

    /**
     * Attempt to finalize subscription.
     * validateFeed must have been run first.
     *
     * Calls showForm on failure or success on success.
     */
    function saveFeed()
    {
        $user = common_current_user();
        $group = $this->oprofile->localGroup();
        if ($user->isMember($group)) {
            // TRANS: OStatus remote group subscription dialog error.
            $this->showForm(_m('Already a member!'));
            return;
        }

        if (Event::handle('StartJoinGroup', array($group, $user))) {
            $ok = Group_member::join($this->oprofile->group_id, $user->id);
            if ($ok) {
                Event::handle('EndJoinGroup', array($group, $user));
                $this->success();
            } else {
                // TRANS: OStatus remote group subscription dialog error.
                $this->showForm(_m('Remote group join failed!'));
            }
        } else {
            // TRANS: OStatus remote group subscription dialog error.
            $this->showForm(_m('Remote group join aborted!'));
        }
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Page title for OStatus remote group join form
        return _m('Confirm joining remote group');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Instructions.
        return _m('You can subscribe to groups from other supported sites. Paste the group\'s profile URI below:');
    }

    function selfLink()
    {
        return common_local_url('ostatusgroup');
    }
}
