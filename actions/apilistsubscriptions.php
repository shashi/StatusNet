<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Get a list of lists a user is subscribed to.
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
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apibareauth.php';

class ApiListSubscriptionsAction extends ApiBareAuthAction
{
    var $lists = array();
    var $cursor = -1;
    var $next_cursor = 0;
    var $prev_cursor = 0;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->cursor = (int) $this->arg('cursor', -1);
        $this->user = $this->getTargetUser($this->arg('user'));
        $this->getLists();

        return true;
    }

    /**
     * Handle the request
     *
     * Show the lists
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if (empty($this->user)) {
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
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
                400,
                $this->format
            );
            break;
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
        return true;
    }

    function getLists()
    {
        if(empty($this->user)) {
            return;
        }

        $profile = $this->user->getProfile();
        $fn = array($profile, 'getTagSubscriptions');
        # 20 lists
        list($this->lists, $this->next_cursor, $this->prev_cursor) =
                Profile_list::getAtCursor($fn, array(), $this->cursor, 20);
    }
}
