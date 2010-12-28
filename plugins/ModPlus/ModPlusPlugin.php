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
 * Some UI extras for now...
 *
 * @package ModPlusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class ModPlusPlugin extends Plugin
{
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'ModPlus',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:ModPlus',
                            'rawdescription' =>
                            _m('UI extensions for profile moderation actions.'));

        return true;
    }

    /**
     * Load JS at runtime if we're logged in.
     *
     * @param Action $action
     * @return boolean hook result
     */
    function onEndShowScripts($action)
    {
        $user = common_current_user();
        if ($user) {
            $action->script('plugins/ModPlus/modplus.js');
        }
        return true;
    }

    function onEndShowStatusNetStyles($action) {
        $action->cssLink('plugins/ModPlus/modplus.css');
        return true;
    }

    /**
     * Autoloader
     *
     * Loads our classes if they're requested.
     *
     * @param string $cls Class requested
     *
     * @return boolean hook return
     */
    function onAutoload($cls)
    {
        switch ($cls)
        {
        case 'RemoteprofileAction':
        case 'RemoteProfileAction':
            require_once dirname(__FILE__) . '/remoteprofileaction.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Add OpenID-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m URL mapper
     *
     * @return boolean hook return
     */
    function onStartInitializeRouter($m)
    {
        $m->connect('user/remote/:id',
                array('action' => 'remoteprofile'),
                array('id' => '[\d]+'));

        return true;
    }

    function onStartShowNoticeItem($item)
    {
        $profile = $item->profile;
        $isRemote = !(User::staticGet('id', $profile->id));
        if ($isRemote) {
            $target = common_local_url('remoteprofile', array('id' => $profile->id));
            $label = _m('Remote profile options...');
            $item->out->elementStart('div', 'remote-profile-options');
            $item->out->element('a', array('href' => $target), $label);
            $item->out->elementEnd('div');
        }
    }
}
