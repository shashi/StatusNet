<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to do "real time" updates using Comet/Bayeux
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/Realtime/RealtimePlugin.php';

/**
 * Plugin to do realtime updates using Comet
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class CometPlugin extends RealtimePlugin
{
    public $server   = null;
    public $username = null;
    public $password = null;
    public $prefix   = null;
    protected $bay   = null;

    function __construct($server=null, $username=null, $password=null, $prefix=null)
    {
        $this->server   = $server;
        $this->username = $username;
        $this->password = $password;
        $this->prefix   = $prefix;

        parent::__construct();
    }

    function _getScripts()
    {
        $scripts = parent::_getScripts();

        $ours = array('jquery.comet.js', 'cometupdate.js');

        foreach ($ours as $script) {
            $scripts[] = 'plugins/Comet/'.$script;
        }

        return $scripts;
    }

    function _updateInitialize($timeline, $user_id)
    {
        $script = parent::_updateInitialize($timeline, $user_id);
        return $script." CometUpdate.init(\"$this->server\", \"$timeline\", $user_id, \"$this->replyurl\", \"$this->favorurl\", \"$this->deleteurl\");";
    }

    function _connect()
    {
        require_once INSTALLDIR.'/plugins/Comet/bayeux.class.inc.php';
        // Bayeux? Comet? Huh? These terms confuse me
        $this->bay = new Bayeux($this->server, $this->user, $this->password);
    }

    function _publish($timeline, $json)
    {
        $this->bay->publish($timeline, $json);
    }

    function _disconnect()
    {
        unset($this->bay);
    }

    function _pathToChannel($path)
    {
        if (!empty($this->prefix)) {
            array_unshift($path, $this->prefix);
        }
        return '/' . implode('/', $path);
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Comet',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Comet',
                            'rawdescription' =>
                            _m('Plugin to do "real time" updates using Comet/Bayeux.'));
        return true;
    }
}
