<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * @package ModHelperPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class ModHelperPlugin extends Plugin
{
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'ModHelper',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:ModHelper',
                            'rawdescription' =>
                            _m('Lets users who have been manually marked as "modhelper"s silence accounts.'));

        return true;
    }

    function onUserRightsCheck($profile, $right, &$result)
    {
        if ($right == Right::SILENCEUSER) {
            // Hrm.... really we should confirm that the *other* user isn't privleged. :)
            if ($profile->hasRole('modhelper')) {
                $result = true;
                return false;
            }
        }
        return true;
    }
}
