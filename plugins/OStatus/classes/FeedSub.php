<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

/*
PuSH subscription flow:

    $profile->subscribe()
        generate random verification token
            save to verify_token
        sends a sub request to the hub...

    main/push/callback
        hub sends confirmation back to us via GET
        We verify the request, then echo back the challenge.
        On our end, we save the time we subscribed and the lease expiration

    main/push/callback
        hub sends us updates via POST

*/

class FeedDBException extends FeedSubException
{
    public $obj;

    function __construct($obj)
    {
        parent::__construct('Database insert failure');
        $this->obj = $obj;
    }
}

/**
 * FeedSub handles low-level PubHubSubbub (PuSH) subscriptions.
 * Higher-level behavior building OStatus stuff on top is handled
 * under Ostatus_profile.
 */
class FeedSub extends Memcached_DataObject
{
    public $__table = 'feedsub';

    public $id;
    public $uri;

    // PuSH subscription data
    public $huburi;
    public $secret;
    public $verify_token;
    public $sub_state; // subscribe, active, unsubscribe, inactive
    public $sub_start;
    public $sub_end;
    public $last_update;

    public $created;
    public $modified;

    public /*static*/ function staticGet($k, $v=null)
    {
        return parent::staticGet(__CLASS__, $k, $v);
    }

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array('id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'uri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'huburi' =>  DB_DATAOBJECT_STR,
                     'secret' => DB_DATAOBJECT_STR,
                     'verify_token' => DB_DATAOBJECT_STR,
                     'sub_state' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'sub_start' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'sub_end' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'last_update' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'modified' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('id', 'integer',
                                   /*size*/ null,
                                   /*nullable*/ false,
                                   /*key*/ 'PRI',
                                   /*default*/ null,
                                   /*extra*/ null,
                                   /*auto_increment*/ true),
                     new ColumnDef('uri', 'varchar',
                                   255, false, 'UNI'),
                     new ColumnDef('huburi', 'text',
                                   null, true),
                     new ColumnDef('verify_token', 'text',
                                   null, true),
                     new ColumnDef('secret', 'text',
                                   null, true),
                     new ColumnDef('sub_state', "enum('subscribe','active','unsubscribe','inactive')",
                                   null, false),
                     new ColumnDef('sub_start', 'datetime',
                                   null, true),
                     new ColumnDef('sub_end', 'datetime',
                                   null, true),
                     new ColumnDef('last_update', 'datetime',
                                   null, false),
                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('modified', 'datetime',
                                   null, false));
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        return array('id' => 'K', 'uri' => 'U');
    }

    function sequenceKey()
    {
        return array('id', true, false);
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localProfile()
    {
        if ($this->profile_id) {
            return Profile::staticGet('id', $this->profile_id);
        }
        return null;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localGroup()
    {
        if ($this->group_id) {
            return User_group::staticGet('id', $this->group_id);
        }
        return null;
    }

    /**
     * @param string $feeduri
     * @return FeedSub
     * @throws FeedSubException if feed is invalid or lacks PuSH setup
     */
    public static function ensureFeed($feeduri)
    {
        $current = self::staticGet('uri', $feeduri);
        if ($current) {
            return $current;
        }

        $discover = new FeedDiscovery();
        $discover->discoverFromFeedURL($feeduri);

        $huburi = $discover->getHubLink();
        if (!$huburi && !common_config('feedsub', 'fallback_hub')) {
            throw new FeedSubNoHubException();
        }

        $feedsub = new FeedSub();
        $feedsub->uri = $feeduri;
        $feedsub->huburi = $huburi;
        $feedsub->sub_state = 'inactive';

        $feedsub->created = common_sql_now();
        $feedsub->modified = common_sql_now();

        $result = $feedsub->insert();
        if (empty($result)) {
            throw new FeedDBException($feedsub);
        }

        return $feedsub;
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function subscribe($mode='subscribe')
    {
        if ($this->sub_state && $this->sub_state != 'inactive') {
            common_log(LOG_WARNING, "Attempting to (re)start PuSH subscription to $this->uri in unexpected state $this->sub_state");
        }
        if (empty($this->huburi)) {
            if (common_config('feedsub', 'fallback_hub')) {
                // No native hub on this feed?
                // Use our fallback hub, which handles polling on our behalf.
            } else if (common_config('feedsub', 'nohub')) {
                // Fake it! We're just testing remote feeds w/o hubs.
                // We'll never actually get updates in this mode.
                return true;
            } else {
                throw new ServerException("Attempting to start PuSH subscription for feed with no hub");
            }
        }

        return $this->doSubscribe('subscribe');
    }

    /**
     * Send a PuSH unsubscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     * Warning: this will cancel the subscription even if someone else in
     * the system is using it. Most callers will want garbageCollect() instead,
     * which confirms there's no uses left.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function unsubscribe() {
        if ($this->sub_state != 'active') {
            common_log(LOG_WARNING, "Attempting to (re)end PuSH subscription to $this->uri in unexpected state $this->sub_state");
        }
        if (empty($this->huburi)) {
            if (common_config('feedsub', 'fallback_hub')) {
                // No native hub on this feed?
                // Use our fallback hub, which handles polling on our behalf.
            } else if (common_config('feedsub', 'nohub')) {
                // Fake it! We're just testing remote feeds w/o hubs.
                // We'll never actually get updates in this mode.
                return true;
            } else {
                throw new ServerException("Attempting to end PuSH subscription for feed with no hub");
            }
        }

        return $this->doSubscribe('unsubscribe');
    }

    /**
     * Check if there are any active local uses of this feed, and if not then
     * make sure it's inactive, unsubscribing if necessary.
     *
     * @return boolean true if the subscription is now inactive, false if still active.
     */
    public function garbageCollect()
    {
        if ($this->sub_state == '' || $this->sub_state == 'inactive') {
            // No active PuSH subscription, we can just leave it be.
            return true;
        } else {
            // PuSH subscription is either active or in an indeterminate state.
            // Check if we're out of subscribers, and if so send an unsubscribe.
            $count = 0;
            Event::handle('FeedSubSubscriberCount', array($this, &$count));

            if ($count) {
                common_log(LOG_INFO, __METHOD__ . ': ok, ' . $count . ' user(s) left for ' . $this->uri);
                return false;
            } else {
                common_log(LOG_INFO, __METHOD__ . ': unsubscribing, no users left for ' . $this->uri);
                return $this->unsubscribe();
            }
        }
    }

    protected function doSubscribe($mode)
    {
        $orig = clone($this);
        $this->verify_token = common_good_rand(16);
        if ($mode == 'subscribe') {
            $this->secret = common_good_rand(32);
        }
        $this->sub_state = $mode;
        $this->update($orig);
        unset($orig);

        try {
            $callback = common_local_url('pushcallback', array('feed' => $this->id));
            $headers = array('Content-Type: application/x-www-form-urlencoded');
            $post = array('hub.mode' => $mode,
                          'hub.callback' => $callback,
                          'hub.verify' => 'sync',
                          'hub.verify_token' => $this->verify_token,
                          'hub.secret' => $this->secret,
                          'hub.topic' => $this->uri);
            $client = new HTTPClient();
            if ($this->huburi) {
                $hub = $this->huburi;
            } else {
                if (common_config('feedsub', 'fallback_hub')) {
                    $hub = common_config('feedsub', 'fallback_hub');
                    if (common_config('feedsub', 'hub_user')) {
                        $u = common_config('feedsub', 'hub_user');
                        $p = common_config('feedsub', 'hub_pass');
                        $client->setAuth($u, $p);
                    }
                } else {
                    throw new FeedSubException('WTF?');
                }
            }
            $response = $client->post($hub, $headers, $post);
            $status = $response->getStatus();
            if ($status == 202) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok, awaiting verification callback');
                return true;
            } else if ($status == 204) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok and verified');
                return true;
            } else if ($status >= 200 && $status < 300) {
                common_log(LOG_ERR, __METHOD__ . ": sub req returned unexpected HTTP $status: " . $response->getBody());
                return false;
            } else {
                common_log(LOG_ERR, __METHOD__ . ": sub req failed with HTTP $status: " . $response->getBody());
                return false;
            }
        } catch (Exception $e) {
            // wtf!
            common_log(LOG_ERR, __METHOD__ . ": error \"{$e->getMessage()}\" hitting hub $this->huburi subscribing to $this->uri");

            $orig = clone($this);
            $this->verify_token = '';
            $this->sub_state = 'inactive';
            $this->update($orig);
            unset($orig);

            return false;
        }
    }

    /**
     * Save PuSH subscription confirmation.
     * Sets approximate lease start and end times and finalizes state.
     *
     * @param int $lease_seconds provided hub.lease_seconds parameter, if given
     */
    public function confirmSubscribe($lease_seconds=0)
    {
        $original = clone($this);

        $this->sub_state = 'active';
        $this->sub_start = common_sql_date(time());
        if ($lease_seconds > 0) {
            $this->sub_end = common_sql_date(time() + $lease_seconds);
        } else {
            $this->sub_end = null;
        }
        $this->modified = common_sql_now();

        return $this->update($original);
    }

    /**
     * Save PuSH unsubscription confirmation.
     * Wipes active PuSH sub info and resets state.
     */
    public function confirmUnsubscribe()
    {
        $original = clone($this);

        // @fixme these should all be null, but DB_DataObject doesn't save null values...?????
        $this->verify_token = '';
        $this->secret = '';
        $this->sub_state = '';
        $this->sub_start = '';
        $this->sub_end = '';
        $this->modified = common_sql_now();

        return $this->update($original);
    }

    /**
     * Accept updates from a PuSH feed. If validated, this object and the
     * feed (as a DOMDocument) will be passed to the StartFeedSubHandleFeed
     * and EndFeedSubHandleFeed events for processing.
     *
     * Not guaranteed to be running in an immediate POST context; may be run
     * from a queue handler.
     *
     * Side effects: the feedsub record's lastupdate field will be updated
     * to the current time (not published time) if we got a legit update.
     *
     * @param string $post source of Atom or RSS feed
     * @param string $hmac X-Hub-Signature header, if present
     */
    public function receive($post, $hmac)
    {
        common_log(LOG_INFO, __METHOD__ . ": packet for \"$this->uri\"! $hmac $post");

        if ($this->sub_state != 'active') {
            common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH for inactive feed $this->uri (in state '$this->sub_state')");
            return;
        }

        if ($post === '') {
            common_log(LOG_ERR, __METHOD__ . ": ignoring empty post");
            return;
        }

        if (!$this->validatePushSig($post, $hmac)) {
            // Per spec we silently drop input with a bad sig,
            // while reporting receipt to the server.
            return;
        }

        $feed = new DOMDocument();
        if (!$feed->loadXML($post)) {
            // @fixme might help to include the err message
            common_log(LOG_ERR, __METHOD__ . ": ignoring invalid XML");
            return;
        }

        $orig = clone($this);
        $this->last_update = common_sql_now();
        $this->update($orig);

        Event::handle('StartFeedSubReceive', array($this, $feed));
        Event::handle('EndFeedSubReceive', array($this, $feed));
    }

    /**
     * Validate the given Atom chunk and HMAC signature against our
     * shared secret that was set up at subscription time.
     *
     * If we don't have a shared secret, there should be no signature.
     * If we we do, our the calculated HMAC should match theirs.
     *
     * @param string $post raw XML source as POSTed to us
     * @param string $hmac X-Hub-Signature HTTP header value, or empty
     * @return boolean true for a match
     */
    protected function validatePushSig($post, $hmac)
    {
        if ($this->secret) {
            if (preg_match('/^sha1=([0-9a-fA-F]{40})$/', $hmac, $matches)) {
                $their_hmac = strtolower($matches[1]);
                $our_hmac = hash_hmac('sha1', $post, $this->secret);
                if ($their_hmac === $our_hmac) {
                    return true;
                }
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bad SHA-1 HMAC: got $their_hmac, expected $our_hmac");
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bogus HMAC '$hmac'");
            }
        } else {
            if (empty($hmac)) {
                return true;
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with unexpected HMAC '$hmac'");
            }
        }
        return false;
    }

}

