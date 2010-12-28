<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Queuehandler for Facebook transport
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class FacebookQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'facebook';
    }

    function handle($notice)
    {
        if ($this->_isLocal($notice)) {
            return Facebookclient::facebookBroadcastNotice($notice);
        }
        return true;
    }

    /**
     * Determine whether the notice was locally created
     *
     * @param Notice $notice the notice
     *
     * @return boolean locality
     */
    function _isLocal($notice)
    {
        return ($notice->is_local == Notice::LOCAL_PUBLIC ||
                $notice->is_local == Notice::LOCAL_NONPUBLIC);
    }
}
