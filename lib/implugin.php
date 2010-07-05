<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do instant messaging
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
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for plugins that do authentication
 *
 * Implementations will likely want to override onStartIoManagerClasses() so that their
 *   IO manager is used
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

abstract class ImPlugin extends Plugin
{
    //name of this IM transport
    public $transport = null;
    //list of screennames that should get all public notices
    public $public = array();

    /**
     * normalize a screenname for comparison
     *
     * @param string $screenname screenname to normalize
     *
     * @return string an equivalent screenname in normalized form
     */
    abstract function normalize($screenname);


    /**
     * validate (ensure the validity of) a screenname
     *
     * @param string $screenname screenname to validate
     *
     * @return boolean
     */
    abstract function validate($screenname);

    /**
     * get the internationalized/translated display name of this IM service
     *
     * @return string
     */
    abstract function getDisplayName();

    /**
     * send a single notice to a given screenname
     * The implementation should put raw data, ready to send, into the outgoing
     *   queue using enqueue_outgoing_raw()
     *
     * @param string $screenname screenname to send to
     * @param Notice $notice notice to send
     *
     * @return boolean success value
     */
    function send_notice($screenname, $notice)
    {
        return $this->send_message($screenname, $this->format_notice($notice));
    }

    /**
     * send a message (text) to a given screenname
     * The implementation should put raw data, ready to send, into the outgoing
     *   queue using enqueue_outgoing_raw()
     *
     * @param string $screenname screenname to send to
     * @param Notice $body text to send
     *
     * @return boolean success value
     */
    abstract function send_message($screenname, $body);

    /**
     * receive a raw message
     * Raw IM data is taken from the incoming queue, and passed to this function.
     * It should parse the raw message and call handle_incoming()
     * 
     * Returning false may CAUSE REPROCESSING OF THE QUEUE ITEM, and should
     * be used for temporary failures only. For permanent failures such as
     * unrecognized addresses, return true to indicate your processing has
     * completed.
     *
     * @param object $data raw IM data
     *
     * @return boolean true if processing completed, false for temporary failures
     */
    abstract function receive_raw_message($data);

    /**
     * get the screenname of the daemon that sends and receives message for this service
     *
     * @return string screenname of this plugin
     */
    abstract function daemon_screenname();

    /**
     * get the microid uri of a given screenname
     *
     * @param string $screenname screenname
     *
     * @return string microid uri
     */
    function microiduri($screenname)
    {
        return $this->transport . ':' . $screenname;    
    }
    //========================UTILITY FUNCTIONS USEFUL TO IMPLEMENTATIONS - MISC ========================\

    /**
     * Put raw message data (ready to send) into the outgoing queue
     *
     * @param object $data
     */
    function enqueue_outgoing_raw($data)
    {
        $qm = QueueManager::get();
        $qm->enqueue($data, $this->transport . '-out');
    }

    /**
     * Put raw message data (received, ready to be processed) into the incoming queue
     *
     * @param object $data
     */
    function enqueue_incoming_raw($data)
    {
        $qm = QueueManager::get();
        $qm->enqueue($data, $this->transport . '-in');
    }

    /**
     * given a screenname, get the corresponding user
     *
     * @param string $screenname
     *
     * @return User user
     */
    function get_user($screenname)
    {
        $user_im_prefs = $this->get_user_im_prefs_from_screenname($screenname);
        if($user_im_prefs){
            $user = User::staticGet('id', $user_im_prefs->user_id);
            $user_im_prefs->free();
            return $user;
        }else{
            return false;
        }
    }


    /**
     * given a screenname, get the User_im_prefs object for this transport
     *
     * @param string $screenname
     *
     * @return User_im_prefs user_im_prefs
     */
    function get_user_im_prefs_from_screenname($screenname)
    {
        $user_im_prefs = User_im_prefs::pkeyGet(
            array('transport' => $this->transport,
                  'screenname' => $this->normalize($screenname)));
        if ($user_im_prefs) {
            return $user_im_prefs;
        } else {
            return false;
        }
    }


    /**
     * given a User, get their screenname
     *
     * @param User $user
     *
     * @return string screenname of that user
     */
    function get_screenname($user)
    {
        $user_im_prefs = $this->get_user_im_prefs_from_user($user);
        if ($user_im_prefs) {
            return $user_im_prefs->screenname;
        } else {
            return false;
        }
    }


    /**
     * given a User, get their User_im_prefs
     *
     * @param User $user
     *
     * @return User_im_prefs user_im_prefs of that user
     */
    function get_user_im_prefs_from_user($user)
    {
        $user_im_prefs = User_im_prefs::pkeyGet(
            array('transport' => $this->transport,
                  'user_id' => $user->id));
        if ($user_im_prefs){
            return $user_im_prefs;
        } else {
            return false;
        }
    }
    //========================UTILITY FUNCTIONS USEFUL TO IMPLEMENTATIONS - SENDING ========================\
    /**
     * Send a message to a given screenname from the site
     *
     * @param string $screenname screenname to send the message to
     * @param string $msg message contents to send
     *
     * @param boolean success
     */
    protected function send_from_site($screenname, $msg)
    {
        $text = '['.common_config('site', 'name') . '] ' . $msg;
        $this->send_message($screenname, $text);
    }

    /**
     * send a confirmation code to a user
     *
     * @param string $screenname screenname sending to
     * @param string $code the confirmation code
     * @param User $user user sending to
     *
     * @return boolean success value
     */
    function send_confirmation_code($screenname, $code, $user)
    {
        $body = sprintf(_('User "%s" on %s has said that your %s screenname belongs to them. ' .
          'If that\'s true, you can confirm by clicking on this URL: ' .
          '%s' .
          ' . (If you cannot click it, copy-and-paste it into the ' .
          'address bar of your browser). If that user isn\'t you, ' .
          'or if you didn\'t request this confirmation, just ignore this message.'),
          $user->nickname, common_config('site', 'name'), $this->getDisplayName(), common_local_url('confirmaddress', array('code' => $code)));

        return $this->send_message($screenname, $body);
    }

    /**
     * send a notice to all public listeners
     *
     * For notices that are generated on the local system (by users), we can optionally
     * forward them to remote listeners by XMPP.
     *
     * @param Notice $notice notice to broadcast
     *
     * @return boolean success flag
     */

    function public_notice($notice)
    {
        // Now, users who want everything

        // FIXME PRIV don't send out private messages here
        // XXX: should we send out non-local messages if public,localonly
        // = false? I think not

        foreach ($this->public as $screenname) {
            common_log(LOG_INFO,
                       'Sending notice ' . $notice->id .
                       ' to public listener ' . $screenname,
                       __FILE__);
            $this->send_notice($screenname, $notice);
        }

        return true;
    }

    /**
     * broadcast a notice to all subscribers and reply recipients
     *
     * This function will send a notice to all subscribers on the local server
     * who have IM addresses, and have IM notification enabled, and
     * have this subscription enabled for IM. It also sends the notice to
     * all recipients of @-replies who have IM addresses and IM notification
     * enabled. This is really the heart of IM distribution in StatusNet.
     *
     * @param Notice $notice The notice to broadcast
     *
     * @return boolean success flag
     */

    function broadcast_notice($notice)
    {

        $ni = $notice->whoGets();

        foreach ($ni as $user_id => $reason) {
            $user = User::staticGet($user_id);
            if (empty($user)) {
                // either not a local user, or just not found
                continue;
            }
            $user_im_prefs = $this->get_user_im_prefs_from_user($user);
            if(!$user_im_prefs || !$user_im_prefs->notify){
                continue;
            }

            switch ($reason) {
            case NOTICE_INBOX_SOURCE_REPLY:
                if (!$user_im_prefs->replies) {
                    continue 2;
                }
                break;
            case NOTICE_INBOX_SOURCE_SUB:
                $sub = Subscription::pkeyGet(array('subscriber' => $user->id,
                                                   'subscribed' => $notice->profile_id));
                if (empty($sub) || !$sub->jabber) {
                    continue 2;
                }
                break;
            case NOTICE_INBOX_SOURCE_GROUP:
                break;
            default:
                throw new Exception(sprintf(_("Unknown inbox source %d."), $reason));
            }

            common_log(LOG_INFO,
                       'Sending notice ' . $notice->id . ' to ' . $user_im_prefs->screenname,
                       __FILE__);
            $this->send_notice($user_im_prefs->screenname, $notice);
            $user_im_prefs->free();
        }

        return true;
    }

    /**
     * makes a plain-text formatted version of a notice, suitable for IM distribution
     *
     * @param Notice  $notice  notice being sent
     *
     * @return string plain-text version of the notice, with user nickname prefixed
     */

    function format_notice($notice)
    {
        $profile = $notice->getProfile();
        return $profile->nickname . ': ' . $notice->content . ' [' . $notice->id . ']';
    }
    //========================UTILITY FUNCTIONS USEFUL TO IMPLEMENTATIONS - RECEIVING ========================\

    /**
     * Attempt to handle a message as a command
     * @param User $user user the message is from
     * @param string $body message text
     * @return boolean true if the message was a command and was executed, false if it was not a command
     */
    protected function handle_command($user, $body)
    {
        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($user, $body);
        if ($cmd) {
            $chan = new IMChannel($this);
            $cmd->execute($chan);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Is some text an autoreply message?
     * @param string $txt message text
     * @return boolean true if autoreply
     */
    protected function is_autoreply($txt)
    {
        if (preg_match('/[\[\(]?[Aa]uto[-\s]?[Rr]e(ply|sponse)[\]\)]/', $txt)) {
            return true;
        } else if (preg_match('/^System: Message wasn\'t delivered. Offline storage size was exceeded.$/', $txt)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Is some text an OTR message?
     * @param string $txt message text
     * @return boolean true if OTR
     */
    protected function is_otr($txt)
    {
        if (preg_match('/^\?OTR/', $txt)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Helper for handling incoming messages
     * Your incoming message handler will probably want to call this function
     *
     * @param string $from screenname the message was sent from
     * @param string $message message contents
     *
     * @param boolean success
     */
    protected function handle_incoming($from, $notice_text)
    {
        $user = $this->get_user($from);
        // For common_current_user to work
        global $_cur;
        $_cur = $user;

        if (!$user) {
            $this->send_from_site($from, 'Unknown user; go to ' .
                             common_local_url('imsettings') .
                             ' to add your address to your account');
            common_log(LOG_WARNING, 'Message from unknown user ' . $from);
            return;
        }
        if ($this->handle_command($user, $notice_text)) {
            common_log(LOG_INFO, "Command message by $from handled.");
            return;
        } else if ($this->is_autoreply($notice_text)) {
            common_log(LOG_INFO, 'Ignoring auto reply from ' . $from);
            return;
        } else if ($this->is_otr($notice_text)) {
            common_log(LOG_INFO, 'Ignoring OTR from ' . $from);
            return;
        } else {

            common_log(LOG_INFO, 'Posting a notice from ' . $user->nickname);

            $this->add_notice($from, $user, $notice_text);
        }

        $user->free();
        unset($user);
        unset($_cur);
        unset($message);
    }

    /**
     * Helper for handling incoming messages
     * Your incoming message handler will probably want to call this function
     *
     * @param string $from screenname the message was sent from
     * @param string $message message contents
     *
     * @param boolean success
     */
    protected function add_notice($screenname, $user, $body)
    {
        $body = trim(strip_tags($body));
        $content_shortened = common_shorten_links($body);
        if (Notice::contentTooLong($content_shortened)) {
          $this->send_from_site($screenname, sprintf(_('Message too long - maximum is %1$d characters, you sent %2$d.'),
                                          Notice::maxContent(),
                                          mb_strlen($content_shortened)));
          return;
        }

        try {
            $notice = Notice::saveNew($user->id, $content_shortened, $this->transport);
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            $this->send_from_site($from, $e->getMessage());
            return;
        }

        common_broadcast_notice($notice);
        common_log(LOG_INFO,
                   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
        $notice->free();
        unset($notice);
    }

    //========================EVENT HANDLERS========================\
    
    /**
     * Register notice queue handler
     *
     * @param QueueManager $manager
     *
     * @return boolean hook return
     */
    function onEndInitializeQueueManager($manager)
    {
        $manager->connect($this->transport . '-in', new ImReceiverQueueHandler($this), 'im');
        $manager->connect($this->transport, new ImQueueHandler($this));
        $manager->connect($this->transport . '-out', new ImSenderQueueHandler($this), 'im');
        return true;
    }

    function onStartImDaemonIoManagers(&$classes)
    {
        //$classes[] = new ImManager($this); // handles sending/receiving/pings/reconnects
        return true;
    }

    function onStartEnqueueNotice($notice, &$transports)
    {
        $profile = Profile::staticGet($notice->profile_id);

        if (!$profile) {
            common_log(LOG_WARNING, 'Refusing to broadcast notice with ' .
                       'unknown profile ' . common_log_objstring($notice),
                       __FILE__);
        }else{
            $transports[] = $this->transport;
        }

        return true;
    }

    function onEndShowHeadElements($action)
    {
        $aname = $action->trimmed('action');

        if ($aname == 'shownotice') {

            $user_im_prefs = new User_im_prefs();
            $user_im_prefs->user_id = $action->profile->id;
            $user_im_prefs->transport = $this->transport;

            if ($user_im_prefs->find() && $user_im_prefs->fetch() && $user_im_prefs->microid && $action->notice->uri) {
                $id = new Microid($this->microiduri($user_im_prefs->screenname),
                                  $action->notice->uri);
                $action->element('meta', array('name' => 'microid',
                                             'content' => $id->toString()));
            }

        } else if ($aname == 'showstream') {

            $user_im_prefs = new User_im_prefs();
            $user_im_prefs->user_id = $action->user->id;
            $user_im_prefs->transport = $this->transport;

            if ($user_im_prefs->find() && $user_im_prefs->fetch() && $user_im_prefs->microid && $action->profile->profileurl) {
                $id = new Microid($this->microiduri($user_im_prefs->screenname),
                                  $action->selfUrl());
                $action->element('meta', array('name' => 'microid',
                                               'content' => $id->toString()));
            }
        }
    }

    function onNormalizeImScreenname($transport, &$screenname)
    {
        if($transport == $this->transport)
        {
            $screenname = $this->normalize($screenname);
            return false;
        }
    }

    function onValidateImScreenname($transport, $screenname, &$valid)
    {
        if($transport == $this->transport)
        {
            $valid = $this->validate($screenname);
            return false;
        }
    }

    function onGetImTransports(&$transports)
    {
        $transports[$this->transport] = array(
            'display' => $this->getDisplayName(),
            'daemon_screenname' => $this->daemon_screenname());
    }

    function onSendImConfirmationCode($transport, $screenname, $code, $user)
    {
        if($transport == $this->transport)
        {
            $this->send_confirmation_code($screenname, $code, $user);
            return false;
        }
    }

    function onUserDeleteRelated($user, &$tables)
    {
        $tables[] = 'User_im_prefs';
        return true;
    }

    function initialize()
    {
        if( ! common_config('queue', 'enabled'))
        {
            throw new ServerException("Queueing must be enabled to use IM plugins");
        }

        if(is_null($this->transport)){
            throw new ServerException('transport cannot be null');
        }
    }
}
