<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Utility for doing HTTP-related things
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once 'HTTP/Request2.php';
require_once 'HTTP/Request2/Response.php';

/**
 * Useful structure for HTTP responses
 *
 * We make HTTP calls in several places, and we have several different
 * ways of doing them. This class hides the specifics of what underlying
 * library (curl or PHP-HTTP or whatever) that's used.
 *
 * This extends the HTTP_Request2_Response class with methods to get info
 * about any followed redirects.
 * 
 * Originally used the name 'HTTPResponse' to match earlier code, but
 * this conflicts with a class in in the PECL HTTP extension.
 *
 * @category HTTP
 * @package StatusNet
 * @author Evan Prodromou <evan@status.net>
 * @author Brion Vibber <brion@status.net>
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link http://status.net/
 */
class StatusNet_HTTPResponse extends HTTP_Request2_Response
{
    function __construct(HTTP_Request2_Response $response, $url, $redirects=0)
    {
        foreach (get_object_vars($response) as $key => $val) {
            $this->$key = $val;
        }
        $this->url = strval($url);
        $this->redirectCount = intval($redirects);
    }

    /**
     * Get the count of redirects that have been followed, if any.
     * @return int
     */
    function getRedirectCount()
    {
        return $this->redirectCount;
    }

    /**
     * Gets the final target URL, after any redirects have been followed.
     * @return string URL
     */
    function getUrl()
    {
        return $this->url;
    }

    /**
     * Check if the response is OK, generally a 200 or other 2xx status code.
     * @return bool
     */
    function isOk()
    {
        $status = $this->getStatus();
        return ($status >= 200 && $status < 300);
    }
}

/**
 * Utility class for doing HTTP client stuff
 *
 * We make HTTP calls in several places, and we have several different
 * ways of doing them. This class hides the specifics of what underlying
 * library (curl or PHP-HTTP or whatever) that's used.
 *
 * This extends the PEAR HTTP_Request2 package:
 * - sends StatusNet-specific User-Agent header
 * - 'follow_redirects' config option, defaulting off
 * - 'max_redirs' config option, defaulting to 10
 * - extended response class adds getRedirectCount() and getUrl() methods
 * - get() and post() convenience functions return body content directly
 *
 * @category HTTP
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class HTTPClient extends HTTP_Request2
{

    function __construct($url=null, $method=self::METHOD_GET, $config=array())
    {
        $this->config['max_redirs'] = 10;
        $this->config['follow_redirects'] = true;
        
        // We've had some issues with keepalive breaking with
        // HEAD requests, such as to youtube which seems to be
        // emitting chunked encoding info for an empty body
        // instead of not emitting anything. This may be a
        // bug on YouTube's end, but the upstream libray
        // ought to be investigated to see if we can handle
        // it gracefully in that case as well.
        $this->config['protocol_version'] = '1.0';

        // Default state of OpenSSL seems to have no trusted
        // SSL certificate authorities, which breaks hostname
        // verification and means we have a hard time communicating
        // with other sites' HTTPS interfaces.
        //
        // Turn off verification unless we've configured a CA bundle.
        if (common_config('http', 'ssl_cafile')) {
            $this->config['ssl_cafile'] = common_config('http', 'ssl_cafile');
        } else {
            $this->config['ssl_verify_peer'] = false;
        }

        if (common_config('http', 'curl') && extension_loaded('curl')) {
            $this->config['adapter'] = 'HTTP_Request2_Adapter_Curl';
        }

        parent::__construct($url, $method, $config);
        $this->setHeader('User-Agent', $this->userAgent());
    }

    /**
     * Convenience/back-compat instantiator
     * @return HTTPClient
     */
    public static function start()
    {
        return new HTTPClient();
    }

    /**
     * Convenience function to run a GET request.
     *
     * @return StatusNet_HTTPResponse
     * @throws HTTP_Request2_Exception
     */
    public function get($url, $headers=array())
    {
        return $this->doRequest($url, self::METHOD_GET, $headers);
    }

    /**
     * Convenience function to run a HEAD request.
     *
     * @return StatusNet_HTTPResponse
     * @throws HTTP_Request2_Exception
     */
    public function head($url, $headers=array())
    {
        return $this->doRequest($url, self::METHOD_HEAD, $headers);
    }

    /**
     * Convenience function to POST form data.
     *
     * @param string $url
     * @param array $headers optional associative array of HTTP headers
     * @param array $data optional associative array or blob of form data to submit
     * @return StatusNet_HTTPResponse
     * @throws HTTP_Request2_Exception
     */
    public function post($url, $headers=array(), $data=array())
    {
        if ($data) {
            $this->addPostParameter($data);
        }
        return $this->doRequest($url, self::METHOD_POST, $headers);
    }

    /**
     * @return StatusNet_HTTPResponse
     * @throws HTTP_Request2_Exception
     */
    protected function doRequest($url, $method, $headers)
    {
        $this->setUrl($url);

        // Workaround for HTTP_Request2 not setting up SNI in socket contexts;
        // This fixes cert validation for SSL virtual hosts using SNI.
        // Requires PHP 5.3.2 or later and OpenSSL with SNI support.
        if ($this->url->getScheme() == 'https' && defined('OPENSSL_TLSEXT_SERVER_NAME')) {
            $this->config['ssl_SNI_enabled'] = true;
            $this->config['ssl_SNI_server_name'] = $this->url->getHost();
        }

        $this->setMethod($method);
        if ($headers) {
            foreach ($headers as $header) {
                $this->setHeader($header);
            }
        }
        $response = $this->send();
        return $response;
    }
    
    protected function log($level, $detail) {
        $method = $this->getMethod();
        $url = $this->getUrl();
        common_log($level, __CLASS__ . ": HTTP $method $url - $detail");
    }

    /**
     * Pulls up StatusNet's customized user-agent string, so services
     * we hit can track down the responsible software.
     *
     * @return string
     */
    function userAgent()
    {
        return "StatusNet/".STATUSNET_VERSION." (".STATUSNET_CODENAME.")";
    }

    /**
     * Actually performs the HTTP request and returns a
     * StatusNet_HTTPResponse object with response body and header info.
     *
     * Wraps around parent send() to add logging and redirection processing.
     *
     * @return StatusNet_HTTPResponse
     * @throw HTTP_Request2_Exception
     */
    public function send()
    {
        $maxRedirs = intval($this->config['max_redirs']);
        if (empty($this->config['follow_redirects'])) {
            $maxRedirs = 0;
        }
        $redirs = 0;
        do {
            try {
                $response = parent::send();
            } catch (HTTP_Request2_Exception $e) {
                $this->log(LOG_ERR, $e->getMessage());
                throw $e;
            }
            $code = $response->getStatus();
            if ($code >= 200 && $code < 300) {
                $reason = $response->getReasonPhrase();
                $this->log(LOG_INFO, "$code $reason");
            } elseif ($code >= 300 && $code < 400) {
                $url = $this->getUrl();
                $target = $response->getHeader('Location');
                
                if (++$redirs >= $maxRedirs) {
                    common_log(LOG_ERR, __CLASS__ . ": Too many redirects: skipping $code redirect from $url to $target");
                    break;
                }
                try {
                    $this->setUrl($target);
                    $this->setHeader('Referer', $url);
                    common_log(LOG_INFO, __CLASS__ . ": Following $code redirect from $url to $target");
                    continue;
                } catch (HTTP_Request2_Exception $e) {
                    common_log(LOG_ERR, __CLASS__ . ": Invalid $code redirect from $url to $target");
                }
            } else {
                $reason = $response->getReasonPhrase();
                $this->log(LOG_ERR, "$code $reason");
            }
            break;
        } while ($maxRedirs);
        return new StatusNet_HTTPResponse($response, $this->getUrl(), $redirs);
    }
}
