<?php
/**
 * Anonyous favor action
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Anonymous favor class
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class AnonFavorAction extends RedirectingAction
{
    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        $profile = AnonymousFavePlugin::getAnonProfile();

        if (empty($profile) || $_SERVER['REQUEST_METHOD'] != 'POST') {
            // TRANS: Client error.
            $this->clientError( _m('Could not favor notice! Please make sure your browser has cookies enabled.')
            );
            return;
        }

        $id     = $this->trimmed('notice');
        $notice = Notice::staticGet($id);
        $token  = $this->trimmed('token-' . $notice->id);

        if (empty($token) || $token != common_session_token()) {
            // TRANS: Client error.
            $this->clientError(_m('There was a problem with your session token. Try again, please.'));
            return;
        }


        if ($profile->hasFave($notice)) {
            // TRANS: Client error.
            $this->clientError(_m('This notice is already a favorite!'));
            return;
        }
        $fave = Fave::addNew($profile, $notice);

        if (!$fave) {
            // TRANS: Server error.
            $this->serverError(_m('Could not create favorite.'));
            return;
        }

        $profile->blowFavesCache();

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title.
            $this->element('title', null, _m('Disfavor favorite'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $disfavor = new AnonDisFavorForm($this, $notice);
            $disfavor->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->returnToPrevious();
        }
    }

    /**
     * If returnto not set, return to the public stream.
     *
     * @return string URL
     */
    function defaultReturnTo()
    {
        $returnto = common_get_returnto();
        if (empty($returnto)) {
            return common_local_url('public');
        } else {
            return $returnto;
        }
    }
}
