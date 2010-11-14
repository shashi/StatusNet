<?php
/**
 * Make another user an admin of a group
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Make another user an admin of a group
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class MakeadminAction extends RedirectingAction
{
    var $profile = null;
    var $group = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);
        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return false;
        }
        $token = $this->trimmed('token');
        if (empty($token) || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }
        $id = $this->trimmed('profileid');
        if (empty($id)) {
            $this->clientError(_('No profile specified.'));
            return false;
        }
        $this->profile = Profile::staticGet('id', $id);
        if (empty($this->profile)) {
            $this->clientError(_('No profile with that ID.'));
            return false;
        }
        $group_id = $this->trimmed('groupid');
        if (empty($group_id)) {
            $this->clientError(_('No group specified.'));
            return false;
        }
        $this->group = User_group::staticGet('id', $group_id);
        if (empty($this->group)) {
            $this->clientError(_('No such group.'));
            return false;
        }
        $user = common_current_user();
        if (!$user->isAdmin($this->group) &&
            !$user->hasRight(Right::MAKEGROUPADMIN)) {
            $this->clientError(_('Only an admin can make another user an admin.'), 401);
            return false;
        }
        if ($this->profile->isAdmin($this->group)) {
            $this->clientError(sprintf(_('%1$s is already an admin for group "%2$s".'),
                                       $this->profile->getBestName(),
                                       $this->group->getBestName()),
                               401);
            return false;
        }
        return true;
    }

    /**
     * Handle request
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->makeAdmin();
        }
    }

    /**
     * Make user an admin
     *
     * @return void
     */

    function makeAdmin()
    {
        $member = Group_member::pkeyGet(array('group_id' => $this->group->id,
                                              'profile_id' => $this->profile->id));

        if (empty($member)) {
            $this->serverError(_('Can\'t get membership record for %1$s in group %2$s.'),
                               $this->profile->getBestName(),
                               $this->group->getBestName());
        }

        $orig = clone($member);

        $member->is_admin = 1;

        $result = $member->update($orig);

        if (!$result) {
            common_log_db_error($member, 'UPDATE', __FILE__);
            $this->serverError(_('Can\'t make %1$s an admin for group %2$s.'),
                               $this->profile->getBestName(),
                               $this->group->getBestName());
        }

        $this->returnToPrevious();
    }

    /**
     * If we reached this form without returnto arguments, default to
     * the top of the group's member list.
     * 
     * @return string URL
     */
    function defaultReturnTo()
    {
        return common_local_url('groupmembers',
                                array('nickname' => $this->group->nickname));
    }

}
