<?php
/*
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

/**
 * @package OStatusPlugin
 * @maintainer James Walker <james@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }


class OStatusTagAction extends OStatusInitAction
{

    var $nickname;
    var $profile;
    var $err;

    function prepare($args)
    {
        parent::prepare($args);

        if (common_logged_in()) {
            $this->clientError(_m('You can use the local tagging!'));
            return false;
        }

        $this->nickname = $this->trimmed('nickname');

        // Webfinger or profile URL of the remote user
        $this->profile = $this->trimmed('profile');

        return true;
    }

    function showContent()
    {
        $header = sprintf(_m('Tag %s'), $this->nickname);
        $submit = _m('Go');
        $this->elementStart('form', array('id' => 'form_ostatus_connect',
                                          'method' => 'post',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('ostatustag')));
        $this->elementStart('fieldset');
        $this->element('legend', null,  $header);
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'ostatus_nickname'));
        $this->input('nickname', _m('User nickname'), $this->nickname,
                     _m('Nickname of the user you want to tag'));
        $this->elementEnd('li');
        $this->elementStart('li', array('id' => 'ostatus_profile'));
        $this->input('profile', _m('Profile Account'), $this->profile,
                     _m('Your account id (i.e. user@identi.ca)'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('submit', $submit);
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function connectWebfinger($acct)
    {
        $target_profile = $this->targetProfile();

        $disco = new Discovery;
        $result = $disco->lookup($acct);
        if (!$result) {
            $this->clientError(_m("Couldn't look up OStatus account profile."));
        }

        foreach ($result->links as $link) {
            if ($link['rel'] == 'http://ostatus.org/schema/1.0/tag') {
                // We found a URL - let's redirect!
                $url = Discovery::applyTemplate($link['template'], $target_profile);
                common_log(LOG_INFO, "Sending remote subscriber $acct to $url");
                common_redirect($url, 303);
            }

        }
        $this->clientError(_m("Couldn't confirm remote profile address."));
    }

    function connectProfile($subscriber_profile)
    {
        $target_profile = $this->targetProfile();

        // @fixme hack hack! We should look up the remote sub URL from XRDS
        $suburl = preg_replace('!^(.*)/(.*?)$!', '$1/main/tagprofile', $subscriber_profile);
        $suburl .= '?uri=' . urlencode($target_profile);

        common_log(LOG_INFO, "Sending remote subscriber $subscriber_profile to $suburl");
        common_redirect($suburl, 303);
    }

    function title()
    {
      return _m('OStatus people tag');  
    }
}
