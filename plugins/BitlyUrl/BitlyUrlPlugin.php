<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to use bit.ly URL shortening services.
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @copyright 2010 StatusNet, Inc http://status.net/
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class BitlyUrlPlugin extends UrlShortenerPlugin
{
    public $shortenerName = 'bit.ly';
    public $serviceUrl = 'http://bit.ly/api?method=shorten&version=2.0.1&longUrl=%s';
    public $login; // To set a site-default when admins or users don't override it.
    public $apiKey;

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->serviceUrl)){
            throw new Exception(_m("You must specify a serviceUrl for bit.ly shortening."));
        }
    }

    /**
     * Add bit.ly to the list of available URL shorteners if it's configured,
     * otherwise leave it out.
     *
     * @param array $shorteners
     * @return boolean hook return value
     */
    function onGetUrlShorteners(&$shorteners)
    {
        if ($this->getLogin() && $this->getApiKey()) {
            return parent::onGetUrlShorteners($shorteners);
        }
        return true;
    }

    /**
     * Short a URL
     * @param url
     * @return string shortened version of the url, or null if URL shortening failed
     */
    protected function shorten($url) {
        $response = $this->query($url);
        if ($this->isOk($url, $response)) {
            return $this->decode($url, $response->getBody());
        } else {
            return null;
        }
    }

    /**
     * Get the user's or site-wide default bit.ly login name.
     *
     * @return string
     */
    protected function getLogin()
    {
        $login = common_config('bitly', 'default_login');
        if (!$login) {
            $login = $this->login;
        }
        return $login;
    }

    /**
     * Get the user's or site-wide default bit.ly API key.
     *
     * @return string
     */
    protected function getApiKey()
    {
        $key = common_config('bitly', 'default_apikey');
        if (!$key) {
            $key = $this->apiKey;
        }
        return $key;
    }

    /**
     * Inject API key into query before sending out...
     *
     * @param string $url
     * @return HTTPResponse
     */
    protected function query($url)
    {
        // http://code.google.com/p/bitly-api/wiki/ApiDocumentation#/shorten
        $params = http_build_query(array(
            'login' => $this->getLogin(),
            'apiKey' => $this->getApiKey()), '', '&');
        $serviceUrl = sprintf($this->serviceUrl, urlencode($url)) . '&' . $params;

        $request = HTTPClient::start();
        return $request->get($serviceUrl);
    }

    /**
     * JSON decode for API result
     */
    protected function decode($url, $body)
    {
        $json = json_decode($body, true);
        return $json['results'][$url]['shortUrl'];
    }

    /**
     * JSON decode for API result
     */
    protected function isOk($url, $response)
    {
        $code = 'unknown';
        $msg = '';
        if ($response->isOk()) {
            $body = $response->getBody();
            common_log(LOG_INFO, $body);
            $json = json_decode($body, true);
            if ($json['statusCode'] == 'OK') {
                if (!isset($json['results'][$url])) {
                    common_log(LOG_ERR, "bit.ly returned OK response, but didn't find expected URL $url in $body");
                    return false;
                }
                $data = $json['results'][$url];
                if (isset($data['shortUrl'])) {
                    return true;
                } else if (isset($data['statusCode']) && $data['statusCode'] == 'ERROR') {
                    $code = $data['errorCode'];
                    $msg = $data['errorMessage'];
                }
            } else if ($json['statusCode'] == 'ERROR') {
                $code = $json['errorCode'];
                $msg = $json['errorMessage'];
            }
            common_log(LOG_ERR, "bit.ly returned error $code $msg for $url");
        }
        return false;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => sprintf('BitlyUrl (%s)', $this->shortenerName),
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews, Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:BitlyUrl',
                            'rawdescription' =>
                            sprintf(_m('Uses <a href="http://%1$s/">%1$s</a> URL-shortener service.'),
                                    $this->shortenerName));

        return true;
    }

    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('admin/bitly',
                    array('action' => 'bitlyadminpanel'));
        return true;
    }

    /**
     * If the plugin's installed, this should be accessible to admins.
     */
    function onAdminPanelCheck($name, &$isOK)
    {
        if ($name == 'bitly') {
            $isOK = true;
            return false;
        }

        return true;
    }

    /**
     * Add the bit.ly admin panel to the list...
     */
    function onEndAdminPanelNav($nav)
    {
        if (AdminPanelAction::canAdmin('bitly')) {
            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(common_local_url('bitlyadminpanel'),
                                _m('bit.ly'),
                                _m('bit.ly URL shortening'),
                                $action_name == 'bitlyadminpanel',
                                'nav_bitly_admin_panel');
        }

        return true;
    }

    /**
     * Automatically load the actions and libraries used by the plugin
     *
     * @param Class $cls the class
     *
     * @return boolean hook return
     *
     */
    function onAutoload($cls)
    {
        $base = dirname(__FILE__);
        $lower = strtolower($cls);
        switch ($lower) {
        case 'bitlyadminpanelaction':
            require_once "$base/$lower.php";
            return false;
        default:
            return true;
        }
    }

    /**
     * Internal hook point to check the default global credentials so
     * the admin form knows if we have a fallback or not.
     *
     * @param string $login
     * @param string $apiKey
     * @return boolean hook return value
     */
    function onBitlyDefaultCredentials(&$login, &$apiKey)
    {
        $login = $this->login;
        $apiKey = $this->apiKey;
        return false;
    }

}
