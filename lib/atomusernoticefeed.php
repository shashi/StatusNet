<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for building an in-memory Atom feed for a particular user's
 * timeline.
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
 * @category  Feed
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET'))
{
    exit(1);
}

/**
 * Class for user notice feeds.  May contain a reference to the user.
 *
 * @category Feed
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AtomUserNoticeFeed extends AtomNoticeFeed
{
    protected $user;

    /**
     * Constructor
     *
     * @param User    $user    the user for the feed
     * @param User    $cur     the current authenticated user, if any
     * @param boolean $indent  flag to turn indenting on or off
     *
     * @return void
     */
    function __construct($user, $cur = null, $indent = true) {
        parent::__construct($cur, $indent);
        $this->user = $user;
        if (!empty($user)) {
            $profile = $user->getProfile();
            $this->addAuthor($profile->nickname, $user->uri);
            $this->setActivitySubject($profile->asActivityNoun('subject'));
        }

        // TRANS: Title in atom user notice feed. %s is a user name.
        $title      = sprintf(_("%s timeline"), $user->nickname);
        $this->setTitle($title);

        $sitename   = common_config('site', 'name');
        $subtitle   = sprintf(
            // TRANS: Message is used as a subtitle in atom user notice feed.
            // TRANS: %1$s is a user name, %2$s is a site name.
            _('Updates from %1$s on %2$s!'),
            $user->nickname, $sitename
        );
        $this->setSubtitle($subtitle);

        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        $logo = ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_PROFILE_SIZE);
        $this->setLogo($logo);

        $this->setUpdated('now');

        $this->addLink(
            common_local_url(
                'showstream',
                array('nickname' => $user->nickname)
            )
        );

        $self = common_local_url('ApiTimelineUser',
                                 array('id' => $user->id,
                                       'format' => 'atom'));
        $this->setId($self);
        $this->setSelfLink($self);

        $this->addLink(
            common_local_url('sup', null, null, $user->id),
            array(
                'rel' => 'http://api.friendfeed.com/2008/03#sup',
                'type' => 'application/json'
            )
        );
    }

    function getUser()
    {
        return $this->user;
    }

    function showSource()
    {
        return false;
    }

    function showAuthor()
    {
        return false;
    }
}
