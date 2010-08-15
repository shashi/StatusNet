<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';
require_once INSTALLDIR.'/classes/File.php';
require_once INSTALLDIR.'/classes/File_oembed.php';

define('USER_AGENT', 'StatusNet user agent / file probe');

/**
 * Table Definition for file_redirection
 */

class File_redirection extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_redirection';                // table name
    public $url;                             // varchar(255)  primary_key not_null
    public $file_id;                         // int(4)
    public $redirections;                    // int(4)
    public $httpcode;                        // int(4)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('File_redirection',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function _commonHttp($url, $redirs) {
        $request = new HTTPClient($url);
        $request->setConfig(array(
            'connect_timeout' => 10, // # seconds to wait
            'max_redirs' => $redirs, // # max number of http redirections to follow
            'follow_redirects' => true, // Follow redirects
            'store_body' => false, // We won't need body content here.
        ));
        return $request;
    }

    /**
     * Check if this URL is a redirect and return redir info.
     *
     * Most code should call File_redirection::where instead, to check if we
     * already know that redirection and avoid extra hits to the web.
     *
     * The URL is hit and any redirects are followed, up to 10 levels or until
     * a protected URL is reached.
     *
     * @param string $in_url
     * @return mixed one of:
     *         string - target URL, if this is a direct link or can't be followed
     *         array - redirect info if this is an *unknown* redirect:
     *              associative array with the following elements:
     *                code: HTTP status code
     *                redirects: count of redirects followed
     *                url: URL string of final target
     *                type (optional): MIME type from Content-Type header
     *                size (optional): byte size from Content-Length header
     *                time (optional): timestamp from Last-Modified header
     */
    public function lookupWhere($short_url, $redirs = 10, $protected = false) {
        if ($redirs < 0) return false;

        if(strpos($short_url,'://') === false){
            return $short_url;
        }
        try {
            $request = self::_commonHttp($short_url, $redirs);
            // Don't include body in output
            $request->setMethod(HTTP_Request2::METHOD_HEAD);
            $response = $request->send();

            if (405 == $response->getStatus()) {
                // Server doesn't support HEAD method? Can this really happen?
                // We'll try again as a GET and ignore the response data.
                $request = self::_commonHttp($short_url, $redirs);
                $response = $request->send();
            }
        } catch (Exception $e) {
            // Invalid URL or failure to reach server
            common_log(LOG_ERR, "Error while following redirects for $short_url: " . $e->getMessage());
            return $short_url;
        }

        if ($response->getRedirectCount() && File::isProtected($response->getUrl())) {
            // Bump back up the redirect chain until we find a non-protected URL
            return self::lookupWhere($short_url, $response->getRedirectCount() - 1, true);
        }

        $ret = array('code' => $response->getStatus()
                , 'redirects' => $response->getRedirectCount()
                , 'url' => $response->getUrl());

        $type = $response->getHeader('Content-Type');
        if ($type) $ret['type'] = $type;
        if ($protected) $ret['protected'] = true;
        $size = $response->getHeader('Content-Length'); // @fixme bytes?
        if ($size) $ret['size'] = $size;
        $time = $response->getHeader('Last-Modified');
        if ($time) $ret['time'] = strtotime($time);
        return $ret;
    }

    /**
     * Check if this URL is a redirect and return redir info.
     * If a File record is present for this URL, it is not considered a redirect.
     * If a File_redirection record is present for this URL, the recorded target is returned.
     *
     * If no File or File_redirect record is present, the URL is hit and any
     * redirects are followed, up to 10 levels or until a protected URL is
     * reached.
     *
     * @param string $in_url
     * @return mixed one of:
     *         string - target URL, if this is a direct link or a known redirect
     *         array - redirect info if this is an *unknown* redirect:
     *              associative array with the following elements:
     *                code: HTTP status code
     *                redirects: count of redirects followed
     *                url: URL string of final target
     *                type (optional): MIME type from Content-Type header
     *                size (optional): byte size from Content-Length header
     *                time (optional): timestamp from Last-Modified header
     */
    public function where($in_url) {
        // let's see if we know this...
        $a = File::staticGet('url', $in_url);

        if (!empty($a)) {
            // this is a direct link to $a->url
            return $a->url;
        } else {
            $b = File_redirection::staticGet('url', $in_url);
            if (!empty($b)) {
                // this is a redirect to $b->file_id
                $a = File::staticGet('id', $b->file_id);
                return $a->url;
            }
        }

        $ret = File_redirection::lookupWhere($in_url);
        return $ret;
    }

    /**
     * Shorten a URL with the current user's configured shortening
     * options, if applicable.
     *
     * If it cannot be shortened or the "short" URL is longer than the
     * original, the original is returned.
     *
     * If the referenced item has not been seen before, embedding data
     * may be saved.
     *
     * @param string $long_url
     * @return string
     */

    function makeShort($long_url)
    {
        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon);

        // Did we get one? Is it shorter?

        if (!empty($short_url)) {
            return $short_url;
        } else {
            return $long_url;
        }
    }

    /**
     * Shorten a URL with the current user's configured shortening
     * options, if applicable.
     *
     * If it cannot be shortened or the "short" URL is longer than the
     * original, the original is returned.
     *
     * If the referenced item has not been seen before, embedding data
     * may be saved.
     *
     * @param string $long_url
     * @return string
     */

    function forceShort($long_url)
    {
        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon, true);

        // Did we get one? Is it shorter?
        if (!empty($short_url)) {
            return $short_url;
        } else {
            return $long_url;
        }
    }

    function _userMakeShort($long_url, $force = false) {
        $short_url = common_shorten_url($long_url, $force);
        if (!empty($short_url) && $short_url != $long_url) {
            $short_url = (string)$short_url;
            // store it
            $file = File::staticGet('url', $long_url);
            if (empty($file)) {
                // Check if the target URL is itself a redirect...
                $redir_data = File_redirection::where($long_url);
                if (is_array($redir_data)) {
                    // We haven't seen the target URL before.
                    // Save file and embedding data about it!
                    $file = File::saveNew($redir_data, $long_url);
                    $file_id = $file->id;
                    if (!empty($redir_data['oembed']['json'])) {
                        File_oembed::saveNew($redir_data['oembed']['json'], $file_id);
                    }
                } else if (is_string($redir_data)) {
                    // The file is a known redirect target.
                    $file = File::staticGet('url', $redir_data);
                    if (empty($file)) {
                        // @fixme should we save a new one?
                        // this case was triggering sometimes for redirects
                        // with unresolvable targets; found while fixing
                        // "can't linkify" bugs with shortened links to
                        // SSL sites with cert issues.
                        return null;
                    }
                    $file_id = $file->id;
                }
            } else {
                $file_id = $file->id;
            }
            $file_redir = File_redirection::staticGet('url', $short_url);
            if (empty($file_redir)) {
                $file_redir = new File_redirection;
                $file_redir->url = $short_url;
                $file_redir->file_id = $file_id;
                $file_redir->insert();
            }
            return $short_url;
        }
        return null;
    }

    function _canonUrl($in_url, $default_scheme = 'http://') {
        if (empty($in_url)) return false;
        $out_url = $in_url;
        $p = parse_url($out_url);
        if (empty($p['host']) || empty($p['scheme'])) {
            list($scheme) = explode(':', $in_url, 2);
            switch ($scheme) {
            case 'fax':
            case 'tel':
                $out_url = str_replace('.-()', '', $out_url);
                break;

            case 'mailto':
            case 'aim':
            case 'jabber':
            case 'xmpp':
                // don't touch anything
                break;

            default:
                $out_url = $default_scheme . ltrim($out_url, '/');
                $p = parse_url($out_url);
                if (empty($p['scheme'])) return false;
                break;
            }
        }

        if (('ftp' == $p['scheme']) || ('ftps' == $p['scheme']) || ('http' == $p['scheme']) || ('https' == $p['scheme'])) {
            if (empty($p['host'])) return false;
            if (empty($p['path'])) {
                $out_url .= '/';
            }
        }

        return $out_url;
    }

    function saveNew($data, $file_id, $url) {
        $file_redir = new File_redirection;
        $file_redir->url = $url;
        $file_redir->file_id = $file_id;
        $file_redir->redirections = intval($data['redirects']);
        $file_redir->httpcode = intval($data['code']);
        $file_redir->insert();
    }
}

