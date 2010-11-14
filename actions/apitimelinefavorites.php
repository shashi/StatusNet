<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a user's favorite notices
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
 * @category  API
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/apibareauth.php';

/**
 * Returns the 20 most recent favorite notices for the authenticating user or user
 * specified by the ID parameter in the requested format.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiTimelineFavoritesAction extends ApiBareAuthAction
{
    var $notices  = null;

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

        $this->user = $this->getTargetUser($this->arg('id'));

        if (empty($this->user)) {
            // TRANS: Client error displayed when requesting most recent favourite notices by a user for a non-existing user.
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        $this->notices = $this->getNotices();

        return true;
    }

    /**
     * Handle the request
     *
     * Just show the notices
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showTimeline();
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */
    function showTimeline()
    {
        $profile  = $this->user->getProfile();
        $avatar   = $profile->getAvatar(AVATAR_PROFILE_SIZE);

        $sitename = common_config('site', 'name');
        $title    = sprintf(
            // TRANS: Title for timeline of most recent favourite notices by a user.
            // TRANS: %1$s is the StatusNet sitename, %2$s is a user nickname.
            _('%1$s / Favorites from %2$s'),
            $sitename,
            $this->user->nickname
        );

        $taguribase = TagURI::base();
        $id         = "tag:$taguribase:Favorites:" . $this->user->id;

        $subtitle = sprintf(
            // TRANS: Subtitle for timeline of most recent favourite notices by a user.
            // TRANS: %1$s is the StatusNet sitename, %2$s is a user's full name,
            // TRANS: %3$s is a user nickname.
            _('%1$s updates favorited by %2$s / %3$s.'),
            $sitename,
            $profile->getBestName(),
            $this->user->nickname
        );
        $logo = !empty($avatar)
            ? $avatar->displayUrl()
            : Avatar::defaultImage(AVATAR_PROFILE_SIZE);

        $link = common_local_url(
            'showfavorites',
            array('nickname' => $this->user->nickname)
        );

        $self = $this->getSelfUri();

        switch($this->format) {
        case 'xml':
            $this->showXmlTimeline($this->notices);
            break;
        case 'rss':
            $this->showRssTimeline(
                $this->notices,
                $title,
                $link,
                $subtitle,
                null,
                $logo,
                $self
            );
            break;
        case 'atom':
            header('Content-Type: application/atom+xml; charset=utf-8');

            $atom = new AtomNoticeFeed($this->auth_user);

            $atom->setId($id);
            $atom->setTitle($title);
            $atom->setSubtitle($subtitle);
            $atom->setLogo($logo);
            $atom->setUpdated('now');

            $atom->addLink($link);
            $atom->setSelfLink($self);

            $atom->addEntryFromNotices($this->notices);

            $this->raw($atom->getString());
            break;
        case 'json':
            $this->showJsonTimeline($this->notices);
            break;
        default:
            // TRANS: Client error displayed when trying to handle an unknown API method.
            $this->clientError(_('API method not found.'), $code = 404);
            break;
        }
    }

    /**
     * Get notices
     *
     * @return array notices
     */
    function getNotices()
    {
        $notices = array();

        common_debug("since id = " . $this->since_id . " max id = " . $this->max_id);

        if (!empty($this->auth_user) && $this->auth_user->id == $this->user->id) {
            $notice = $this->user->favoriteNotices(
                true,
                ($this->page-1) * $this->count,
                $this->count,
                $this->since_id,
                $this->max_id
            );
        } else {
            $notice = $this->user->favoriteNotices(
                false,
                ($this->page-1) * $this->count,
                $this->count,
                $this->since_id,
                $this->max_id
            );
        }

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this feed last modified?
     *
     * @return string datestamp of the latest notice in the stream
     */
    function lastModified()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {
            return strtotime($this->notices[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this stream
     *
     * Returns an Etag based on the action name, language, user ID, and
     * timestamps of the first and last notice in the timeline
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {

            $last = count($this->notices) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_user_cache_hash($this->auth_user),
                      common_language(),
                      $this->user->id,
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }
}
