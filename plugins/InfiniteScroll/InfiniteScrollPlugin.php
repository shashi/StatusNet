<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable Infinite Scrolling
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

class InfiniteScrollPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onEndShowScripts($action)
    {
        $action->script('plugins/InfiniteScroll/jquery.infinitescroll.js');
        $action->script('plugins/InfiniteScroll/infinitescroll.js');
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'InfiniteScroll',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:InfiniteScroll',
                            'rawdescription' =>
                            _m('Infinite Scroll adds the following functionality to your StatusNet installation: When a user scrolls towards the bottom of the page, the next page of notices is automatically retrieved and appended. This means they never need to click "Next Page", which dramatically increases stickiness.'));
        return true;
    }
}
