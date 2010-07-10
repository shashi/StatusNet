<?php

/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of profiles
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
 * @category  Public
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/profilelist.php';

/**
 * Widget to show a list of subscriptions
 *
 * @category Public
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class SubscriptionList extends ProfileList
{
    /** Owner of this list */
    var $owner = null;

    function __construct($profile, $owner=null, $action=null)
    {
        parent::__construct($profile, $action);

        $this->owner = $owner;
    }

    function newListItem($profile)
    {
        return new SubscriptionListItem($profile, $this->owner, $this->action);
    }
}

class SubscriptionListItem extends ProfileListItem
{
    /** Owner of this list */
    var $owner = null;

    function __construct($profile, $owner, $action)
    {
        parent::__construct($profile, $action);

        $this->owner = $owner;
    }

    function showProfile()
    {
        $this->startProfile();
        $this->showAvatar();
        $this->showFullName();
        $this->showLocation();
        $this->showHomepage();
        $this->showBio();
        // Relevant portion!
        $this->showTags();
        $this->endProfile();
    }

    function isOwn()
    {
        $user = common_current_user();
        return (!empty($user) && ($this->owner->id == $user->id));
    }

    function showTags()
    {
        $tags = Profile_tag::getTags($this->owner->id, $this->profile->id);

        $this->out->elementStart('dl', 'entity_tags');
        $this->out->elementStart('dt');
        if ($this->isOwn()) {
            $this->out->element('a', array('href' => common_local_url('tagprofile',
                                                                      array('id' => $this->profile->id))),
                                _('Tags'));
        } else {
            $this->out->text(_('Tags'));
        }
        $this->out->elementEnd('dt');
        $this->out->elementStart('dd');
        if ($tags) {
            $this->out->elementStart('ul', 'tags xoxo');
            foreach ($tags as $tag) {
                $this->out->elementStart('li');
                // Avoid space by using raw output.
                $pt = '<span class="mark_hash">#</span><a rel="tag" href="' .
                  common_local_url($this->action->trimmed('action'),
                                   array('nickname' => $this->owner->nickname,
                                   'tag' => $tag)) .
                  '">' . $tag . '</a>';
                $this->out->raw($pt);
                $this->out->elementEnd('li');
            }
            $this->out->elementEnd('ul');
        } else {
            $this->out->text(_('(None)'));
        }
        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }
}
