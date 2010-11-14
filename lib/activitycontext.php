<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class ActivityContext
{
    public $replyToID;
    public $replyToUrl;
    public $location;
    public $attention = array();
    public $conversation;

    const THR     = 'http://purl.org/syndication/thread/1.0';
    const GEORSS  = 'http://www.georss.org/georss';
    const OSTATUS = 'http://ostatus.org/schema/1.0';

    const INREPLYTO = 'in-reply-to';
    const REF       = 'ref';
    const HREF      = 'href';

    const POINT     = 'point';

    const ATTENTION    = 'ostatus:attention';
    const MENTIONED    = 'mentioned';
    const CONVERSATION = 'ostatus:conversation';

    function __construct($element = null)
    {
        if (empty($element)) {
            return;
        }

        $replyToEl = ActivityUtils::child($element, self::INREPLYTO, self::THR);

        if (!empty($replyToEl)) {
            $this->replyToID  = $replyToEl->getAttribute(self::REF);
            $this->replyToUrl = $replyToEl->getAttribute(self::HREF);
        }

        $this->location = $this->getLocation($element);

        $this->conversation = ActivityUtils::getLink($element, self::CONVERSATION);

        // Multiple attention links allowed

        $links = $element->getElementsByTagNameNS(ActivityUtils::ATOM, ActivityUtils::LINK);

        $attention = array();
        for ($i = 0; $i < $links->length; $i++) {
            $link = $links->item($i);

            $linkRel = $link->getAttribute(ActivityUtils::REL);

            // XXX: Deprecate this in favour of "mentioned" from Salmon spec
            // http://salmon-protocol.googlecode.com/svn/trunk/draft-panzer-salmon-00.html#SALR
            if ($linkRel == self::ATTENTION) {
                $attention[] = $link->getAttribute(self::HREF);
            } elseif ($linkRel == self::MENTIONED) {
                $attention[] = $link->getAttribute(self::HREF);
            }
        }
        $this->attention = array_unique($attention);
    }

    /**
     * Parse location given as a GeoRSS-simple point, if provided.
     * http://www.georss.org/simple
     *
     * @param feed item $entry
     * @return mixed Location or false
     */
    function getLocation($dom)
    {
        $points = $dom->getElementsByTagNameNS(self::GEORSS, self::POINT);

        for ($i = 0; $i < $points->length; $i++) {
            $point = $points->item($i)->textContent;
            return self::locationFromPoint($point);
        }

        return null;
    }

    // XXX: Move to ActivityUtils or Location?
    static function locationFromPoint($point)
    {
        $point = str_replace(',', ' ', $point); // per spec "treat commas as whitespace"
        $point = preg_replace('/\s+/', ' ', $point);
        $point = trim($point);
        $coords = explode(' ', $point);
        if (count($coords) == 2) {
            list($lat, $lon) = $coords;
            if (is_numeric($lat) && is_numeric($lon)) {
                common_log(LOG_INFO, "Looking up location for $lat $lon from georss point");
                return Location::fromLatLon($lat, $lon);
            }
        }
        common_log(LOG_ERR, "Ignoring bogus georss:point value $point");
        return null;
    }
}
