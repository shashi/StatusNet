<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Feed of group memberships for a user, in ActivityStreams format
 * 
 * PHP version 5
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
 *
 * @category  AtomPub
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Feed of group memberships for a user, in ActivityStreams format
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class AtompubmembershipfeedAction extends ApiAuthAction
{
    private $_profile     = null;
    private $_memberships = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        parent::prepare($argarray);

        $profileId = $this->trimmed('profile');

        $this->_profile = Profile::staticGet('id', $profileId);
        
        if (empty($this->_profile)) {
            throw new ClientException(_('No such profile.'), 404);
        }

        $offset = ($this->page-1) * $this->count;
        $limit  = $this->count + 1;

        $this->_memberships = Group_member::byMember($this->_profile->id,
                                                     $offset,
                                                     $limit);
        
        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($argarray=null)
    {
        parent::handle($argarray);

        switch ($_SERVER['REQUEST_METHOD']) {
        case 'HEAD':
        case 'GET':
            $this->showFeed();
            break;
        case 'POST':
            $this->addMembership();
            break;
        default:
            throw new ClientException(_('HTTP method not supported.'), 405);
            return;
        }

        return;
    }

    /**
     * Show a feed of favorite activity streams objects
     *
     * @return void
     */

    function showFeed()
    {
        header('Content-Type: application/atom+xml; charset=utf-8');

        $url = common_local_url('AtomPubMembershipFeed',
                                array('profile' => $this->_profile->id));

        $feed = new Atom10Feed(true);

        $feed->addNamespace('activity',
                            'http://activitystrea.ms/spec/1.0/');

        $feed->addNamespace('poco',
                            'http://portablecontacts.net/spec/1.0');

        $feed->addNamespace('media',
                            'http://purl.org/syndication/atommedia');

        $feed->id = $url;

        $feed->setUpdated('now');

        $feed->addAuthor($this->_profile->getBestName(),
                         $this->_profile->getURI());

        $feed->setTitle(sprintf(_("%s group memberships"),
                                $this->_profile->getBestName()));

        $feed->setSubtitle(sprintf(_("Groups %s is a member of on %s"),
                                   $this->_profile->getBestName(),
                                   common_config('site', 'name')));

        $feed->addLink(common_local_url('usergroups',
                                        array('nickname' => 
                                              $this->_profile->nickname)));

        $feed->addLink($url,
                       array('rel' => 'self',
                             'type' => 'application/atom+xml'));
                                        
        // If there's more...

        if ($this->page > 1) {
            $feed->addLink($url,
                           array('rel' => 'first',
                                 'type' => 'application/atom+xml'));

            $feed->addLink(common_local_url('AtomPubMembershipFeed',
                                            array('profile' => 
                                                  $this->_profile->id),
                                            array('page' => 
                                                  $this->page - 1)),
                           array('rel' => 'prev',
                                 'type' => 'application/atom+xml'));
        }

        if ($this->_memberships->N > $this->count) {

            $feed->addLink(common_local_url('AtomPubMembershipFeed',
                                            array('profile' =>
                                                  $this->_profile->id),
                                            array('page' =>
                                                  $this->page + 1)),
                           array('rel' => 'next',
                                 'type' => 'application/atom+xml'));
        }

        $i = 0;

        while ($this->_memberships->fetch()) {

            // We get one more than needed; skip that one

            $i++;

            if ($i > $this->count) {
                break;
            }

            $act = $this->_memberships->asActivity();
            $feed->addEntryRaw($act->asString(false, false, false));
        }

        $this->raw($feed->getString());
    }

    /**
     * add a new favorite
     *
     * @return void
     */

    function addMembership()
    {
        // XXX: Refactor this; all the same for atompub

        if (empty($this->auth_user) ||
            $this->auth_user->id != $this->_profile->id) {
            throw new ClientException(_("Can't add someone else's".
                                        " membership"), 403);
        }
        
        $xml = file_get_contents('php://input');

        $dom = DOMDocument::loadXML($xml);

        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            // TRANS: Client error displayed when not using an Atom entry.
            throw new ClientException(_('Atom post must be an Atom entry.'));
            return;
        }

        $activity = new Activity($dom->documentElement);

        $membership = null;

        if (Event::handle('StartAtomPubNewActivity', array(&$activity))) {

            if ($activity->verb != ActivityVerb::JOIN) {
                // TRANS: Client error displayed when not using the POST verb.
                // TRANS: Do not translate POST.
                throw new ClientException(_('Can only handle Join activities.'));
                return;
            }

            $groupObj = $activity->objects[0];

            if ($groupObj->type != ActivityObject::GROUP) {
                throw new ClientException(_('Can only fave notices.'));
                return;
            }

            $group = User_group::staticGet('uri', $groupObj->id);
            
            if (empty($group)) {
                // XXX: import from listed URL or something
                throw new ClientException(_('Unknown group.'));
            }

            $old = Group_member::pkeyGet(array('profile_id' => $this->auth_user->id,
                                               'group_id' => $group->id));

            if (!empty($old)) {
                throw new ClientException(_('Already a member.'));
            }

            $profile = $this->auth_user->getProfile();

            if (Group_block::isBlocked($group, $profile)) {
                // XXX: import from listed URL or something
                throw new ClientException(_('Blocked by admin.'));
            }

            if (Event::handle('StartJoinGroup', array($group, $this->auth_user))) {
                $membership = Group_member::join($group->id, $this->auth_user->id);
                Event::handle('EndJoinGroup', array($group, $this->auth_user));
            }

            Event::handle('EndAtomPubNewActivity', array($activity, $membership));
        }

        if (!empty($membership)) {
            $act = $membership->asActivity();

            header('Content-Type: application/atom+xml; charset=utf-8');
            header('Content-Location: ' . $act->selfLink);

            $this->startXML();
            $this->raw($act->asString(true, true, true));
            $this->endXML();
        }
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */

    function etag()
    {
        return null;
    }

    /**
     * Does this require authentication?
     *
     * @return boolean true if delete, else false
     */

    function requiresAuth()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return false;
        } else {
            return true;
        }
    }
}
