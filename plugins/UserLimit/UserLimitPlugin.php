<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to limit number of users that can register (best for cloud providers)
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to limit number of users that can register (best for cloud providers)
 *
 * For cloud providers whose freemium model is based on how many
 * users can register. We use it on the StatusNet Cloud.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */

class UserLimitPlugin extends Plugin
{
    public $maxUsers = null;

    function onStartUserRegister(&$user, &$profile)
    {
        $this->_checkMaxUsers();
        return true;
    }

    function onStartRegistrationTry($action)
    {
        $this->_checkMaxUsers();
        return true;
    }

    function _checkMaxUsers()
    {
        if (!is_null($this->maxUsers)) {

            $cls = new User();

            $cnt = $cls->count();

            if ($cnt >= $this->maxUsers) {
                $msg = sprintf(_('Cannot register; maximum number of users (%d) reached.'),
                               $this->maxUsers);

                throw new ClientException($msg);
            }
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'UserLimit',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:UserLimit',
                            'description' =>
                            _m('Limit the number of users who can register.'));
        return true;
    }
}
