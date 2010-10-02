<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List existing lists or create a new list.
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
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apibareauth.php';

/**
 * Action handler for Twitter list_memeber methods
 *
 * @category API
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      ApiBareAuthAction
 */

class ApiListsAction extends ApiBareAuthAction
{
    var $lists   = null;
    var $cursor = 0;
    var $next_cursor = 0;
    var $prev_cursor = 0;
    var $create = false;

    /**
     * Set the flags for handling the request. List lists created by user if this
     * is a GET request, create a new list if it is a POST request.
     *
     * Takes parameters:
     *     - user: the user id or nickname
     * Parameters for POST request
     *     - name: name of the new list (the people tag itself)
     *     - mode: (optional) mode for the new list private/public
     *     - description: (optional) description for the list
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->create = ($_SERVER['REQUEST_METHOD'] == 'POST');

        if (!$this->create) {

            $this->user = $this->getTargetUser($this->arg('user'));

            if (empty($this->user)) {
                $this->clientError(_('No such user.'), 404, $this->format);
                return false;
            }
            $this->getLists();
        }

        return true;
    }

    /**
     * require authentication if it is a write action or user is ambiguous
     *
     */

    function requiresAuth()
    {
        return parent::requiresAuth() ||
            $this->create || $this->delete;
    }

    /**
     * Handle request:
     *     Show the lists the user has created if the request method is GET
     *     Create a new list by diferring to handlePost() if it is POST.
     */

    function handle($args)
    {
        parent::handle($args);

        if($this->create) {
            return $this->handlePost();
        }

        switch($this->format) {
        case 'xml':
            $this->showXmlLists($this->lists, $this->next_cursor, $this->prev_cursor);
            break;
        case 'json':
            $this->showJsonLists($this->lists, $this->next_cursor, $this->prev_cursor);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
    }

    /**
     * Create a new list
     *
     * @return boolean success
     */

    function handlePost()
    {
        $name=$this->arg('name');
        if(empty($name)) {
            // mimick twitter
            print _("A list's name can't be blank.");
            exit(1);
        }

        // twitter creates a new list by appending a number to the end
        // if the list by the given name already exists
        // it makes more sense to return the existing list instead

        $private = null;
        if ($this->arg('mode') === 'public') {
            $private = false;
        } else if ($this->arg('mode') === 'private') {
            $private = true;
        }

        $list = Profile_list::ensureTag($this->auth_user->id,
                                        $this->arg('name'),
                                        $this->arg('description'),
                                        $private);
        if (empty($list)) {
            return false;
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($list);
            break;
        case 'json':
            $this->showSingleJsonList($list);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
        return true;
    }

    /**
     * Get lists
     */

    function getLists()
    {
        $cursor = (int) $this->arg('cursor', -1);

        // twitter fixes count at 20
        // there is no argument named count
        $count = 20;
        $profile = $this->user->getProfile();
        $fn = array($profile, 'getOwnedTags');

        list($this->lists,
             $this->next_cursor,
             $this->prev_cursor) = Profile_list::getAtCursor($fn, array($this->auth_user), $cursor, $count);
    }

    function isReadOnly($args)
    {
        return false;
    }

    function lastModified()
    {
        if (!$this->create && !empty($this->lists) && (count($this->lists) > 0)) {
            return strtotime($this->lists[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this list of lists
     *
     * Returns an Etag based on the action name, language, user ID and
     * timestamps of the first and last list the user has joined
     *
     * @return string etag
     */

    function etag()
    {
        if (!$this->create && !empty($this->lists) && (count($this->lists) > 0)) {

            $last = count($this->lists) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->user->id,
                      strtotime($this->lists[0]->created),
                      strtotime($this->lists[$last]->created))
            )
            . '"';
        }

        return null;
    }

}
