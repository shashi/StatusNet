<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Send a direct message via the API
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
 * @category  API
 * @package   StatusNet
 * @author    Adrian Lang <mail@adrianlang.de>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Creates a new direct message from the authenticating user to
 * the user specified by id.
 *
 * @category API
 * @package  StatusNet
 * @author   Adrian Lang <mail@adrianlang.de>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <robin@millette.info>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiDirectMessageNewAction extends ApiAuthAction
{
    var $other   = null;
    var $content = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->user = $this->auth_user;

        if (empty($this->user)) {
            // TRANS: Client error when user not found for an API direct message action.
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        $this->content = $this->trimmed('text');

        $this->user  = $this->auth_user;

        $user_param  = $this->trimmed('user');
        $user_id     = $this->arg('user_id');
        $screen_name = $this->trimmed('screen_name');

        if (isset($user_param) || isset($user_id) || isset($screen_name)) {
            $this->other = $this->getTargetUser($user_param);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Save the new message
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                // TRANS: Client error. POST is a HTTP command. It should not be translated.
                _('This method requires a POST.'),
                400,
                $this->format
            );
            return;
        }

        if (empty($this->content)) {
            $this->clientError(
                // TRANS: Client error displayed when no message text was submitted (406).
                _('No message text!'),
                406,
                $this->format
            );
        } else {
            $content_shortened = $this->auth_user->shortenLinks($this->content);
            if (Message::contentTooLong($content_shortened)) {
                $this->clientError(
                    // TRANS: Client error displayed when message content is too long.
                    // TRANS: %d is the maximum number of characters for a message.
                    sprintf(_m('That\'s too long. Maximum message size is %d character.', 'That\'s too long. Maximum message size is %d characters.', Message::maxContent()),
                        Message::maxContent()
                    ),
                    406,
                    $this->format
                );
                return;
            }
        }

        if (empty($this->other)) {
            // TRANS: Client error displayed if a recipient user could not be found (403).
            $this->clientError(_('Recipient user not found.'), 403, $this->format);
            return;
        } else if (!$this->user->mutuallySubscribed($this->other)) {
            $this->clientError(
                // TRANS: Client error displayed trying to direct message another user who's not a friend (403).
                _('Can\'t send direct messages to users who aren\'t your friend.'),
                403,
                $this->format
            );
            return;
        } else if ($this->user->id == $this->other->id) {

            // Note: sending msgs to yourself is allowed by Twitter

            // TRANS: Client error displayed trying to direct message self (403).
            $this->clientError(_('Do not send a message to yourself; ' .
                   'just say it to yourself quietly instead.'), 403, $this->format);
            return;
        }

        $message = Message::saveNew(
            $this->user->id,
            $this->other->id,
            html_entity_decode($this->content, ENT_NOQUOTES, 'UTF-8'),
            $this->source
        );

        if (is_string($message)) {
            $this->serverError($message);
            return;
        }

        $message->notify();

        if ($this->format == 'xml') {
            $this->showSingleXmlDirectMessage($message);
        } elseif ($this->format == 'json') {
            $this->showSingleJsondirectMessage($message);
        }
    }
}
