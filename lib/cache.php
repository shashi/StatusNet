<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Cache interface plus default in-memory cache implementation
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
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/**
 * Interface for caching
 *
 * An abstract interface for caching. Because we originally used the
 * Memcache plugin directly, the interface uses a small subset of the
 * Memcache interface.
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Cache
{
    var $_items   = array();
    static $_inst = null;

    const COMPRESSED = 1;

    /**
     * Singleton constructor
     *
     * Use this to get the singleton instance of Cache.
     *
     * @return Cache cache object
     */
    static function instance()
    {
        if (is_null(self::$_inst)) {
            self::$_inst = new Cache();
        }

        return self::$_inst;
    }

    /**
     * Create a cache key from input text
     *
     * Builds a cache key from input text. Helps to namespace
     * the cache area (if shared with other applications or sites)
     * and prevent conflicts.
     *
     * @param string $extra the real part of the key
     *
     * @return string full key
     */
    static function key($extra)
    {
        $base_key = common_config('cache', 'base');

        if (empty($base_key)) {
            $base_key = self::keyize(common_config('site', 'name'));
        }

        return 'statusnet:' . $base_key . ':' . $extra;
    }

    /**
     * Make a string suitable for use as a key
     *
     * Useful for turning primary keys of tables into cache keys.
     *
     * @param string $str string to turn into a key
     *
     * @return string keyized string
     */
    static function keyize($str)
    {
        $str = strtolower($str);
        $str = preg_replace('/\s/', '_', $str);
        return $str;
    }

    /**
     * Get a value associated with a key
     *
     * The value should have been set previously.
     *
     * @param string $key Lookup key
     *
     * @return string retrieved value or null if unfound
     */
    function get($key)
    {
        $value = false;

        if (Event::handle('StartCacheGet', array(&$key, &$value))) {
            if (array_key_exists($key, $this->_items)) {
                $value = unserialize($this->_items[$key]);
            }
            Event::handle('EndCacheGet', array($key, &$value));
        }

        return $value;
    }

    /**
     * Set the value associated with a key
     *
     * @param string  $key    The key to use for lookups
     * @param string  $value  The value to store
     * @param integer $flag   Flags to use, may include Cache::COMPRESSED
     * @param integer $expiry Expiry value, mostly ignored
     *
     * @return boolean success flag
     */
    function set($key, $value, $flag=null, $expiry=null)
    {
        $success = false;

        if (Event::handle('StartCacheSet', array(&$key, &$value, &$flag,
                                                 &$expiry, &$success))) {

            $this->_items[$key] = serialize($value);

            $success = true;

            Event::handle('EndCacheSet', array($key, $value, $flag,
                                               $expiry));
        }

        return $success;
    }

    /**
     * Atomically increment an existing numeric value.
     * Existing expiration time should remain unchanged, if any.
     *
     * @param string  $key    The key to use for lookups
     * @param int     $step   Amount to increment (default 1)
     *
     * @return mixed incremented value, or false if not set.
     */
    function increment($key, $step=1)
    {
        $value = false;
        if (Event::handle('StartCacheIncrement', array(&$key, &$step, &$value))) {
            // Fallback is not guaranteed to be atomic,
            // and may original expiry value.
            $value = $this->get($key);
            if ($value !== false) {
                $value += $step;
                $ok = $this->set($key, $value);
                $got = $this->get($key);
            }
            Event::handle('EndCacheIncrement', array($key, $step, $value));
        }
        return $value;
    }

    /**
     * Delete the value associated with a key
     *
     * @param string $key Key to delete
     *
     * @return boolean success flag
     */
    function delete($key)
    {
        $success = false;

        if (Event::handle('StartCacheDelete', array(&$key, &$success))) {
            if (array_key_exists($key, $this->_items)) {
                unset($this->_items[$key]);
            }
            $success = true;
            Event::handle('EndCacheDelete', array($key));
        }

        return $success;
    }

    /**
     * Close or reconnect any remote connections, such as to give
     * daemon processes a chance to reconnect on a fresh socket.
     *
     * @return boolean success flag
     */
    function reconnect()
    {
        $success = false;

        if (Event::handle('StartCacheReconnect', array(&$success))) {
            $success = true;
            Event::handle('EndCacheReconnect', array());
        }

        return $success;
    }
}
