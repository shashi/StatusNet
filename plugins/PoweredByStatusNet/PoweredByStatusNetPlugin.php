<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Outputs 'powered by StatusNet' after site name
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Outputs 'powered by StatusNet' after site name
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class PoweredByStatusNetPlugin extends Plugin
{
    function onEndAddressData($action)
    {
        $action->text(' ');
        $action->elementStart('span', 'poweredby');
        // TRANS: %s is a URL to status.net with "StatusNet" (localised) as link text.
        $action->raw(sprintf(_m('powered by %s'),
                     sprintf('<a href="http://status.net/">%s</a>',
                             _m('StatusNet'))));
        $action->elementEnd('span');

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'PoweredByStatusNet',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Sarven Capadisli',
                            'homepage' => 'http://status.net/wiki/Plugin:PoweredByStatusNet',
                            'rawdescription' =>
                            _m('Outputs "powered by <a href="http://status.net/">StatusNet</a>" after site name.'));
        return true;
    }
}
