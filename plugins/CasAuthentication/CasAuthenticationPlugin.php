<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable Single Sign On via CAS (Central Authentication Service)
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

// We bundle the phpCAS library...
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/extlib/CAS');

class CasAuthenticationPlugin extends AuthenticationPlugin
{
    public $server;
    public $port = 443;
    public $path = '';
    public $takeOverLogin = false;

    function checkPassword($username, $password)
    {
        global $casTempPassword;
        return ($casTempPassword == $password);
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'phpCAS':
            require_once(INSTALLDIR.'/plugins/CasAuthentication/extlib/CAS.php');
            return false;
         case 'CasloginAction':
            require_once(INSTALLDIR.'/plugins/CasAuthentication/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
            return false;
        }
    }

    function onArgsInitialize(&$args)
    {
        if($this->takeOverLogin && $args['action'] == 'login')
        {
            $args['action'] = 'caslogin';
        }
    }

    function onStartInitializeRouter($m)
    {
        $m->connect('main/cas', array('action' => 'caslogin'));
        return true;
    }

    function onEndLoginGroupNav(&$action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('caslogin'),
                          _m('CAS'),
                          _m('Login or register with CAS'),
                          $action_name === 'caslogin');

        return true;
    }

    function onEndShowPageNotice($action)
    {
        $name = $action->trimmed('action');

        switch ($name)
        {
         case 'login':
            $instr = '(Have an account with CAS? ' .
              'Try our [CAS login]'.
              '(%%action.caslogin%%)!)';
            break;
         default:
            return true;
        }

        $output = common_markup_to_html($instr);
        $action->raw($output);
        return true;
    }

    function onLoginAction($action, &$login)
    {
        switch ($action)
        {
         case 'caslogin':
            $login = true;
            return false;
         default:
            return true;
        }
    }

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->server)){
            throw new Exception("must specify a server");
        }
        if(!isset($this->port)){
            throw new Exception("must specify a port");
        }
        if(!isset($this->path)){
            throw new Exception("must specify a path");
        }
        //These values need to be accessible to a action object
        //I can't think of any other way than global variables
        //to allow the action instance to be able to see values :-(
        global $casSettings;
        $casSettings = array();
        $casSettings['server']=$this->server;
        $casSettings['port']=$this->port;
        $casSettings['path']=$this->path;
        $casSettings['takeOverLogin']=$this->takeOverLogin;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'CAS Authentication',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:CasAuthentication',
                            'rawdescription' =>
                            _m('The CAS Authentication plugin allows for StatusNet to handle authentication through CAS (Central Authentication Service).'));
        return true;
    }
}
