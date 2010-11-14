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

/**
 * Utilities for turning DOMish things into Activityish things
 *
 * Some common functions that I didn't have the bandwidth to try to factor
 * into some kind of reasonable superclass, so just dumped here. Might
 * be useful to have an ActivityObject parent class or something.
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class ActivityUtils
{
    const ATOM = 'http://www.w3.org/2005/Atom';

    const LINK = 'link';
    const REL  = 'rel';
    const TYPE = 'type';
    const HREF = 'href';

    const CONTENT = 'content';
    const SRC     = 'src';

    /**
     * Get the permalink for an Activity object
     *
     * @param DOMElement $element A DOM element
     *
     * @return string related link, if any
     */
    static function getPermalink($element)
    {
        return self::getLink($element, 'alternate', 'text/html');
    }

    /**
     * Get the permalink for an Activity object
     *
     * @param DOMElement $element A DOM element
     *
     * @return string related link, if any
     */
    static function getLink(DOMNode $element, $rel, $type=null)
    {
        $els = $element->childNodes;

        foreach ($els as $link) {
            if (!($link instanceof DOMElement)) {
                continue;
            }

            if ($link->localName == self::LINK && $link->namespaceURI == self::ATOM) {
                $linkRel = $link->getAttribute(self::REL);
                $linkType = $link->getAttribute(self::TYPE);

                if ($linkRel == $rel &&
                    (is_null($type) || $linkType == $type)) {
                    return $link->getAttribute(self::HREF);
                }
            }
        }

        return null;
    }

    static function getLinks(DOMNode $element, $rel, $type=null)
    {
        $els = $element->childNodes;
        $out = array();

        foreach ($els as $link) {
            if ($link->localName == self::LINK && $link->namespaceURI == self::ATOM) {
                $linkRel = $link->getAttribute(self::REL);
                $linkType = $link->getAttribute(self::TYPE);

                if ($linkRel == $rel &&
                    (is_null($type) || $linkType == $type)) {
                    $out[] = $link;
                }
            }
        }

        return $out;
    }

    /**
     * Gets the first child element with the given tag
     *
     * @param DOMElement $element   element to pick at
     * @param string     $tag       tag to look for
     * @param string     $namespace Namespace to look under
     *
     * @return DOMElement found element or null
     */
    static function child(DOMNode $element, $tag, $namespace=self::ATOM)
    {
        $els = $element->childNodes;
        if (empty($els) || $els->length == 0) {
            return null;
        } else {
            for ($i = 0; $i < $els->length; $i++) {
                $el = $els->item($i);
                if ($el->localName == $tag && $el->namespaceURI == $namespace) {
                    return $el;
                }
            }
        }
    }

    /**
     * Grab the text content of a DOM element child of the current element
     *
     * @param DOMElement $element   Element whose children we examine
     * @param string     $tag       Tag to look up
     * @param string     $namespace Namespace to use, defaults to Atom
     *
     * @return string content of the child
     */
    static function childContent(DOMNode $element, $tag, $namespace=self::ATOM)
    {
        $el = self::child($element, $tag, $namespace);

        if (empty($el)) {
            return null;
        } else {
            return $el->textContent;
        }
    }

    static function childHtmlContent(DOMNode $element, $tag, $namespace=self::ATOM)
    {
        $el = self::child($element, $tag, $namespace);

        if (empty($el)) {
            return null;
        } else {
            return self::textConstruct($el);
        }
    }

    /**
     * Get the content of an atom:entry-like object
     *
     * @param DOMElement $element The element to examine.
     *
     * @return string unencoded HTML content of the element, like "This -&lt; is <b>HTML</b>."
     *
     * @todo handle remote content
     * @todo handle embedded XML mime types
     * @todo handle base64-encoded non-XML and non-text mime types
     */
    static function getContent($element)
    {
        return self::childHtmlContent($element, self::CONTENT, self::ATOM);
    }

    static function textConstruct($el)
    {
        $src  = $el->getAttribute(self::SRC);

        if (!empty($src)) {
            // TRANS: Client exception thrown when there is no source attribute.
            throw new ClientException(_("Can't handle remote content yet."));
        }

        $type = $el->getAttribute(self::TYPE);

        // slavishly following http://atompub.org/rfc4287.html#rfc.section.4.1.3.3

        if (empty($type) || $type == 'text') {
            // We have plaintext saved as the XML text content.
            // Since we want HTML, we need to escape any special chars.
            return htmlspecialchars($el->textContent);
        } else if ($type == 'html') {
            // We have HTML saved as the XML text content.
            // No additional processing required once we've got it.
            $text = $el->textContent;
            return $text;
        } else if ($type == 'xhtml') {
            // Per spec, the <content type="xhtml"> contains a single
            // HTML <div> with XHTML namespace on it as a child node.
            // We need to pull all of that <div>'s child nodes and
            // serialize them back to an (X)HTML source fragment.
            $divEl = ActivityUtils::child($el, 'div', 'http://www.w3.org/1999/xhtml');
            if (empty($divEl)) {
                return null;
            }
            $doc = $divEl->ownerDocument;
            $text = '';
            $children = $divEl->childNodes;

            for ($i = 0; $i < $children->length; $i++) {
                $child = $children->item($i);
                $text .= $doc->saveXML($child);
            }
            return trim($text);
        } else if (in_array($type, array('text/xml', 'application/xml')) ||
                   preg_match('#(+|/)xml$#', $type)) {
            // TRANS: Client exception thrown when there embedded XML content is found that cannot be processed yet.
            throw new ClientException(_("Can't handle embedded XML content yet."));
        } else if (strncasecmp($type, 'text/', 5)) {
            return $el->textContent;
        } else {
            // TRANS: Client exception thrown when base64 encoded content is found that cannot be processed yet.
            throw new ClientException(_("Can't handle embedded Base64 content yet."));
        }
    }

    /**
     * Is this a valid URI for remote profile/notice identification?
     * Does not have to be a resolvable URL.
     * @param string $uri
     * @return boolean
     */
    static function validateUri($uri)
    {
        // Check mailto: URIs first

        if (preg_match('/^mailto:(.*)$/', $uri, $match)) {
            return Validate::email($match[1], common_config('email', 'check_domain'));
        }

        if (Validate::uri($uri)) {
            return true;
        }

        // Possibly an upstream bug; tag: URIs aren't validated properly
        // unless you explicitly ask for them. All other schemes are accepted
        // for basic URI validation without asking.
        if (Validate::uri($uri, array('allowed_scheme' => array('tag')))) {
            return true;
        }

        return false;
    }
}
