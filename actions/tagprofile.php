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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR . '/lib/settingsaction.php';
require_once INSTALLDIR . '/lib/peopletags.php';

class TagprofileAction extends Action
{
    var $profile = null;
    var $error = null;

    function prepare($args)
    {
        parent::prepare($args);
        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'), 403);
            return false;
        }

        $id = $this->trimmed('id');
        if (!$id) {
            $this->profile = false;
        } else {
            $this->profile = Profile::staticGet('id', $id);

            if (!$this->profile) {
                $this->clientError(_('No profile with that ID.'));
                return false;
            }
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->saveTags();
        } else {
            $this->showForm();
        }
    }

    function title()
    {
        return sprintf(_('Tag %s'), $this->profile->nickname);
    }

    function showForm($error=null)
    {
        $this->error = $error;
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Error'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p', 'error', $error);
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->showPage();
        }
    }

    function showContent()
    {
        if (!$this->profile) {
            // TODO: show form for fetching a remote profile to tag.
        }

        $this->elementStart('div', 'entity_profile vcard author');
        $this->element('h2', null, _('User profile'));

        $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
        $this->elementStart('dl', 'entity_depiction');
        $this->element('dt', null, _('Photo'));
        $this->elementStart('dd');
        $this->element('img', array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_PROFILE_SIZE),
                                    'class' => 'photo avatar',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' =>
                                    ($this->profile->fullname) ? $this->profile->fullname :
                                    $this->profile->nickname));
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_nickname');
        $this->element('dt', null, _('Nickname'));
        $this->elementStart('dd');
        $this->element('a', array('href' => $this->profile->profileurl,
                                  'class' => 'nickname'),
                       $this->profile->nickname);
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        if ($this->profile->fullname) {
            $this->elementStart('dl', 'entity_fn');
            $this->element('dt', null, _('Full name'));
            $this->elementStart('dd');
            $this->element('span', 'fn', $this->profile->fullname);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        if ($this->profile->location) {
            $this->elementStart('dl', 'entity_location');
            $this->element('dt', null, _('Location'));
            $this->element('dd', 'label', $this->profile->location);
            $this->elementEnd('dl');
        }
        if ($this->profile->homepage) {
            $this->elementStart('dl', 'entity_url');
            $this->element('dt', null, _('URL'));
            $this->elementStart('dd');
            $this->element('a', array('href' => $this->profile->homepage,
                                      'rel' => 'me', 'class' => 'url'),
                           $this->profile->homepage);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        if ($this->profile->bio) {
            $this->elementStart('dl', 'entity_note');
            $this->element('dt', null, _('Note'));
            $this->element('dd', 'note', $this->profile->bio);
            $this->elementEnd('dl');
        }
        $this->elementEnd('div');

        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_tag_user',
                                           'class' => 'form_settings',
                                           'name' => 'tagprofile',
                                           'action' => common_local_url('tagprofile', array('id' => $this->profile->id))));

        $this->elementStart('fieldset');
        $this->element('legend', null, _('Tag user'));
        $this->hidden('token', common_session_token());
        $this->hidden('id', $this->profile->id);

        $user = common_current_user();

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('tags', _('Tags'),
                     ($this->arg('tags')) ? $this->arg('tags') : implode(' ', Profile_tag::getTags($user->id, $this->profile->id)),
                     _('Tags for this user (letters, numbers, -, ., and _), comma- or space- separated'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->submit('save', _('Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function saveTags()
    {
        $id = $this->trimmed('id');
        $tagstring = $this->trimmed('tags');
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if (is_string($tagstring) && strlen($tagstring) > 0) {

            $tags = array_map('common_canonical_tag',
                              preg_split('/[\s,]+/', $tagstring));

            foreach ($tags as $tag) {
                if (!common_valid_profile_tag($tag)) {
                    $this->showForm(sprintf(_('Invalid tag: "%s"'), $tag));
                    return;
                }
            }
        } else {
            $tags = array();
        }

        $user = common_current_user();

        $result = Profile_tag::setTags($user->id, $this->profile->id, $tags);

        if (!$result) {
            $this->clientError(_('Could not save tags.'));
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Tags'));
            $this->elementEnd('head');
            $this->elementStart('body');

            if ($user->id == $this->profile->id) {
                $widget = new SelftagsWidget($this, $user, $this->profile);
                $widget->show();
            } else {
                $widget = new PeopletagsWidget($this, $user, $this->profile);
                $widget->show();
            }

            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $url = common_get_returnto();

            if ($url) {
                // We don't have to return to it again
                common_set_returnto(null);
                $url = common_inject_session($url);
            } else {
                $url = common_local_url('peopletagsbyuser',
                                array('nickname' => $user->nickname));
            }
            common_redirect($url, 303);
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $this->elementStart('div', 'instructions');
            $this->element('p', null,
                           _('Use this form to add tags to your subscribers or subscriptions.'));
            $this->elementEnd('div');
        }
    }
}

