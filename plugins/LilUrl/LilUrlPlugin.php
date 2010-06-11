<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to push RSS/Atom updates to a PubSubHubBub hub
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

if (!defined('STATUSNET')) {
    exit(1);
}

class LilUrlPlugin extends UrlShortenerPlugin
{
    public $serviceUrl;

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->serviceUrl)){
            throw new Exception("must specify a serviceUrl");
        }
    }

    protected function shorten($url) {
        $data = array('longurl' => $url);

        $responseBody = $this->http_post($this->serviceUrl,$data);

        if (!$responseBody) return;
        $y = @simplexml_load_string($responseBody);
        if (!isset($y->body)) return;
        $x = $y->body->p[0]->a->attributes();
        if (isset($x['href'])) {
            return strval($x['href']);
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => sprintf('LilUrl (%s)', $this->shortenerName),
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:LilUrl',
                            'rawdescription' =>
                            sprintf(_m('Uses <a href="http://%1$s/">%1$s</a> URL-shortener service.'),
                                    $this->shortenerName));

        return true;
    }
}

