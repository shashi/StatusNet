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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Key UI methods:
 *
 *  showInputForm() - form asking for a remote profile account or URL
 *                    We end up back here on errors
 *
 *  showPreviewForm() - surrounding form for preview-and-confirm
 *    preview() - display profile for a remote user
 *
 *  success() - redirects to subscriptions page on subscribe
 */
class OStatusSubAction extends Action
{
    protected $profile_uri; // provided acct: or URI of remote entity
    protected $oprofile; // Ostatus_profile of remote entity, if valid

    /**
     * Show the initial form, when we haven't yet been given a valid
     * remote profile.
     */
    function showInputForm()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_ostatus_sub',
                                          'class' => 'form_settings',
                                          'action' => $this->selfLink()));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('profile',
                     // TRANS: Field label for a field that takes an OStatus user address.
                     _m('Subscribe to'),
                     $this->profile_uri,
                     // TRANS: Tooltip for field label "Subscribe to".
                     _m('OStatus user\'s address, like nickname@example.com or http://example.net/nickname'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Button text.
        $this->submit('validate', _m('BUTTON','Continue'));

        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Show the preview-and-confirm form. We've got a valid remote
     * profile and are ready to poke it!
     *
     * This controls the wrapper form; actual profile display will
     * be in previewUser() or previewGroup() depending on the type.
     */
    function showPreviewForm()
    {
        $ok = $this->preview();
        if (!$ok) {
            // @fixme maybe provide a cancel button or link back?
            return;
        }

        $this->elementStart('div', 'entity_actions');
        $this->elementStart('ul');
        $this->elementStart('li', 'entity_subscribe');
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_ostatus_sub',
                                          'class' => 'form_remote_authorize',
                                          'action' =>
                                          $this->selfLink()));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->hidden('profile', $this->profile_uri);
        if ($this->oprofile->isGroup()) {
            $this->submit('submit', _m('Join'), 'submit', null,
                         // TRANS: Button text.
                         // TRANS: Tooltip for button "Join".
                         _m('BUTTON','Join this group'));
        } else {
            // TRANS: Button text.
            $this->submit('submit', _m('BUTTON','Confirm'), 'submit', null,
                         // TRANS: Tooltip for button "Confirm".
                         _m('Subscribe to this user'));
        }
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('div');
    }

    /**
     * Show a preview for a remote user's profile
     * @return boolean true if we're ok to try subscribing
     */
    function preview()
    {
        $oprofile = $this->oprofile;
        $profile = $oprofile->localProfile();

        $cur = common_current_user();
        if ($cur->isSubscribed($profile)) {
            $this->element('div', array('class' => 'error'),
                           _m("You are already subscribed to this user."));
            $ok = false;
        } else {
            $ok = true;
        }

        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        $avatarUrl = $avatar ? $avatar->displayUrl() : false;

        $this->showEntity($profile,
                          $profile->profileurl,
                          $avatarUrl,
                          $profile->bio);
        return $ok;
    }

    function showEntity($entity, $profile, $avatar, $note)
    {
        $nickname = $entity->nickname;
        $fullname = $entity->fullname;
        $homepage = $entity->homepage;
        $location = $entity->location;

        if (!$avatar) {
            $avatar = Avatar::defaultImage(AVATAR_PROFILE_SIZE);
        }

        $this->elementStart('div', 'entity_profile vcard');
        $this->elementStart('dl', 'entity_depiction');
        $this->element('dt', null, _m('Photo'));
        $this->elementStart('dd');
        $this->element('img', array('src' => $avatar,
                                    'class' => 'photo avatar',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $nickname));
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_nickname');
        $this->element('dt', null, _m('Nickname'));
        $this->elementStart('dd');
        $hasFN = ($fullname !== '') ? 'nickname' : 'fn nickname';
        $this->elementStart('a', array('href' => $profile,
                                       'class' => 'url '.$hasFN));
        $this->raw($nickname);
        $this->elementEnd('a');
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        if (!is_null($fullname)) {
            $this->elementStart('dl', 'entity_fn');
            $this->elementStart('dd');
            $this->elementStart('span', 'fn');
            $this->raw($fullname);
            $this->elementEnd('span');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        if (!is_null($location)) {
            $this->elementStart('dl', 'entity_location');
            $this->element('dt', null, _m('Location'));
            $this->elementStart('dd', 'label');
            $this->raw($location);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if (!is_null($homepage)) {
            $this->elementStart('dl', 'entity_url');
            $this->element('dt', null, _m('URL'));
            $this->elementStart('dd');
            $this->elementStart('a', array('href' => $homepage,
                                                'class' => 'url'));
            $this->raw($homepage);
            $this->elementEnd('a');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if (!is_null($note)) {
            $this->elementStart('dl', 'entity_note');
            $this->element('dt', null, _m('Note'));
            $this->elementStart('dd', 'note');
            $this->raw($note);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        $this->elementEnd('div');
    }

    /**
     * Redirect on successful remote user subscription
     */
    function success()
    {
        $cur = common_current_user();
        $url = common_local_url('subscriptions', array('nickname' => $cur->nickname));
        common_redirect($url, 303);
    }

    /**
     * Pull data for a remote profile and check if it's valid.
     * Fills out error UI string in $this->error
     * Fills out $this->oprofile on success.
     *
     * @return boolean
     */
    function pullRemoteProfile()
    {
        $this->profile_uri = $this->trimmed('profile');
        try {
            if (Validate::email($this->profile_uri)) {
                $this->oprofile = Ostatus_profile::ensureWebfinger($this->profile_uri);
            } else if (Validate::uri($this->profile_uri)) {
                $this->oprofile = Ostatus_profile::ensureProfileURL($this->profile_uri);
            } else {
                // TRANS: Error text.
                $this->error = _m("Sorry, we could not reach that address. Please make sure that the OStatus address is like nickname@example.com or http://example.net/nickname.");
                common_debug('Invalid address format.', __FILE__);
                return false;
            }
            return true;
        } catch (FeedSubBadURLException $e) {
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that address. Please make sure that the OStatus address is like nickname@example.com or http://example.net/nickname.");
            common_debug('Invalid URL or could not reach server.', __FILE__);
        } catch (FeedSubBadResponseException $e) {
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that feed. Please try that OStatus address again later.");
            common_debug('Cannot read feed; server returned error.', __FILE__);
        } catch (FeedSubEmptyException $e) {
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that feed. Please try that OStatus address again later.");
            common_debug('Cannot read feed; server returned an empty page.', __FILE__);
        } catch (FeedSubBadHTMLException $e) {
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that feed. Please try that OStatus address again later.");
            common_debug('Bad HTML, could not find feed link.', __FILE__);
        } catch (FeedSubNoFeedException $e) {
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that feed. Please try that OStatus address again later.");
            common_debug('Could not find a feed linked from this URL.', __FILE__);
        } catch (FeedSubUnrecognizedTypeException $e) {
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that feed. Please try that OStatus address again later.");
            common_debug('Not a recognized feed type.', __FILE__);
        } catch (Exception $e) {
            // Any new ones we forgot about
            // TRANS: Error text.
            $this->error = _m("Sorry, we could not reach that address. Please make sure that the OStatus address is like nickname@example.com or http://example.net/nickname.");
            common_debug(sprintf('Bad feed URL: %s %s', get_class($e), $e->getMessage()), __FILE__);
        }

        return false;
    }

    function validateRemoteProfile()
    {
        // Send us to the respective subscription form for conf
        if ($this->oprofile->isGroup()) {
            $target = common_local_url('ostatusgroup', array(), array('profile' => $this->profile_uri));
            common_redirect($target, 303);
        } else if ($this->oprofile->isPeopletag()) {
            $target = common_local_url('ostatuspeopletag', array(), array('profile' => $this->profile_uri));
            common_redirect($target, 303);
        }
    }

    /**
     * Attempt to finalize subscription.
     * validateFeed must have been run first.
     *
     * Calls showForm on failure or success on success.
     */
    function saveFeed()
    {
        // And subscribe the current user to the local profile
        $user = common_current_user();
        $local = $this->oprofile->localProfile();
        if ($user->isSubscribed($local)) {
            // TRANS: OStatus remote subscription dialog error.
            $this->showForm(_m('Already subscribed!'));
        } elseif (Subscription::start($user, $local)) {
            $this->success();
        } else {
            // TRANS: OStatus remote subscription dialog error.
            $this->showForm(_m('Remote subscription failed!'));
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // XXX: selfURL() didn't work. :<
            common_set_returnto($_SERVER['REQUEST_URI']);
            if (Event::handle('RedirectToLogin', array($this, null))) {
                common_redirect(common_local_url('login'), 303);
            }
            return false;
        }

        if ($this->pullRemoteProfile()) {
            $this->validateRemoteProfile();
        }
        return true;
    }

    /**
     * Handle the submission.
     */
    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            $this->showForm();
        }
    }

    /**
     * Handle posts to this form
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_m('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->oprofile) {
            if ($this->arg('submit')) {
                $this->saveFeed();
                return;
            }
        }
        $this->showForm();
    }

    /**
     * Show the appropriate form based on our input state.
     */
    function showForm($err=null)
    {
        if ($err) {
            $this->error = $err;
        }
        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Form title.
            $this->element('title', null, _m('Subscribe to user'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showContent();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->showPage();
        }
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Page title for OStatus remote subscription form
        return _m('Confirm');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Instructions.
        return _m('You can subscribe to users from other supported sites. Paste their address or profile URI below:');
    }

    function showPageNotice()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }
    }

    /**
     * Content area of the page
     *
     * Shows a form for associating a remote OStatus account with this
     * StatusNet account.
     *
     * @return void
     */
    function showContent()
    {
        if ($this->oprofile) {
            $this->showPreviewForm();
        } else {
            $this->showInputForm();
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('feedurl');
    }

    function selfLink()
    {
        return common_local_url('ostatussub');
    }

    /**
     * Disable the send-notice form at the top of the page.
     * This is really just a hack for the broken CSS in the Cloudy theme,
     * I think; copying from other non-notice-navigation pages that do this
     * as well. There will be plenty of others also broken.
     *
     * @fixme fix the cloudy theme
     * @fixme do this in a more general way
     */
    function showNoticeForm() {
        // nop
    }
}
