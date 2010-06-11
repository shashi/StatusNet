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

require_once INSTALLDIR.'/plugins/OpenID/openid.php';

class FinishopenidloginAction extends Action
{
    var $error = null;
    var $username = null;
    var $message = null;

    function handle($args)
    {
        parent::handle($args);
        if (common_is_real_login()) {
            // TRANS: Client error message trying to log on with OpenID while already logged on.
            $this->clientError(_m('Already logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                // TRANS: Message given when there is a problem with the user's session token.
                $this->showForm(_m('There was a problem with your session token. Try again, please.'));
                return;
            }
            if ($this->arg('create')) {
                if (!$this->boolean('license')) {
                    // TRANS: Message given if user does not agree with the site's license.
                    $this->showForm(_m('You can\'t register if you don\'t agree to the license.'),
                                    $this->trimmed('newname'));
                    return;
                }
                $this->createNewUser();
            } else if ($this->arg('connect')) {
                $this->connectUser();
            } else {
                // TRANS: Messag given on an unknown error.
                $this->showForm(_m('An unknown error has occured.'),
                                $this->trimmed('newname'));
            }
        } else {
            $this->tryLogin();
        }
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('div', array('class' => 'error'), $this->error);
        } else {
            $this->element('div', 'instructions',
                           // TRANS: Instructions given after a first successful logon using OpenID.
                           // TRANS: %s is the site name.
                           sprintf(_m('This is the first time you\'ve logged into %s so we must connect your OpenID to a local account. You can either create a new account, or connect with your existing account, if you have one.'), common_config('site', 'name')));
        }
    }

    function title()
    {
        // TRANS: Title
        return _m('OpenID Account Setup');
    }

    function showForm($error=null, $username=null)
    {
        $this->error = $error;
        $this->username = $username;

        $this->showPage();
    }

    /**
     * @fixme much of this duplicates core code, which is very fragile.
     * Should probably be replaced with an extensible mini version of
     * the core registration form.
     */
    function showContent()
    {
        if (!empty($this->message_text)) {
            $this->element('div', array('class' => 'error'), $this->message_text);
            return;
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'account_connect',
                                          'class' => 'form_settings',
                                          'action' => common_local_url('finishopenidlogin')));
        $this->hidden('token', common_session_token());
        $this->elementStart('fieldset', array('id' => 'form_openid_createaccount'));
        $this->element('legend', null,
                       _m('Create new account'));
        $this->element('p', null,
                       _m('Create a new user with this nickname.'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('newname', _m('New nickname'),
                     ($this->username) ? $this->username : '',
                     _m('1-64 lowercase letters or numbers, no punctuation or spaces'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->element('input', array('type' => 'checkbox',
                                      'id' => 'license',
                                      'class' => 'checkbox',
                                      'name' => 'license',
                                      'value' => 'true'));
        $this->elementStart('label', array('for' => 'license',
                                          'class' => 'checkbox'));
        // TRANS: OpenID plugin link text.
        // TRANS: %s is a link to a licese with the license name as link text.
        $message = _('My text and files are available under %s ' .
                     'except this private data: password, ' .
                     'email address, IM address, and phone number.');
        $link = '<a href="' .
                htmlspecialchars(common_config('license', 'url')) .
                '">' .
                htmlspecialchars(common_config('license', 'title')) .
                '</a>';
        $this->raw(sprintf(htmlspecialchars($message), $link));
        $this->elementEnd('label');
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Button label in form in which to create a new user on the site for an OpenID.
        $this->submit('create', _m('BUTTON', 'Create'));
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset', array('id' => 'form_openid_createaccount'));
        $this->element('legend', null,
                       // TRANS: Used as form legend for form in which to connect an OpenID to an existing user on the site.
                       _m('Connect existing account'));
        $this->element('p', null,
                       // TRANS: User instructions for form in which to connect an OpenID to an existing user on the site.
                       _m('If you already have an account, login with your username and password to connect it to your OpenID.'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        // TRANS: Field label in form in which to connect an OpenID to an existing user on the site.
        $this->input('nickname', _m('Existing nickname'));
        $this->elementEnd('li');
        $this->elementStart('li');
        // TRANS: Field label in form in which to connect an OpenID to an existing user on the site.
        $this->password('password', _m('Password'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Button label in form in which to connect an OpenID to an existing user on the site.
        $this->submit('connect', _m('BUTTON', 'Connect'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function tryLogin()
    {
        $consumer = oid_consumer();

        $response = $consumer->complete(common_local_url('finishopenidlogin'));

        if ($response->status == Auth_OpenID_CANCEL) {
            // TRANS: Status message in case the response from the OpenID provider is that the logon attempt was cancelled.
            $this->message(_m('OpenID authentication cancelled.'));
            return;
        } else if ($response->status == Auth_OpenID_FAILURE) {
            // TRANS: OpenID authentication failed; display the error message. %s is the error message.
            $this->message(sprintf(_m('OpenID authentication failed: %s'), $response->message));
        } else if ($response->status == Auth_OpenID_SUCCESS) {
            // This means the authentication succeeded; extract the
            // identity URL and Simple Registration data (if it was
            // returned).
            $display = $response->getDisplayIdentifier();
            $canonical = ($response->endpoint->canonicalID) ?
              $response->endpoint->canonicalID : $response->getDisplayIdentifier();

            oid_assert_allowed($display);
            oid_assert_allowed($canonical);

            $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);

            if ($sreg_resp) {
                $sreg = $sreg_resp->contents();
            }

            // Launchpad teams extension
            if (!oid_check_teams($response)) {
                $this->message(_m('OpenID authentication aborted: you are not allowed to login to this site.'));
                return;
            }

            $user = oid_get_user($canonical);

            if ($user) {
                oid_set_last($display);
                # XXX: commented out at @edd's request until better
                # control over how data flows from OpenID provider.
                # oid_update_user($user, $sreg);
                common_set_user($user);
                common_real_login(true);
                if (isset($_SESSION['openid_rememberme']) && $_SESSION['openid_rememberme']) {
                    common_rememberme($user);
                }
                unset($_SESSION['openid_rememberme']);
                $this->goHome($user->nickname);
            } else {
                $this->saveValues($display, $canonical, $sreg);
                $this->showForm(null, $this->bestNewNickname($display, $sreg));
            }
        }
    }

    function message($msg)
    {
        $this->message_text = $msg;
        $this->showPage();
    }

    function saveValues($display, $canonical, $sreg)
    {
        common_ensure_session();
        $_SESSION['openid_display'] = $display;
        $_SESSION['openid_canonical'] = $canonical;
        $_SESSION['openid_sreg'] = $sreg;
    }

    function getSavedValues()
    {
        return array($_SESSION['openid_display'],
                     $_SESSION['openid_canonical'],
                     $_SESSION['openid_sreg']);
    }

    function createNewUser()
    {
        # FIXME: save invite code before redirect, and check here

        if (common_config('site', 'closed')) {
            // TRANS: OpenID plugin message. No new user registration is allowed on the site.
            $this->clientError(_m('Registration not allowed.'));
            return;
        }

        $invite = null;

        if (common_config('site', 'inviteonly')) {
            $code = $_SESSION['invitecode'];
            if (empty($code)) {
                // TRANS: OpenID plugin message. No new user registration is allowed on the site without an invitation code, and none was provided.
                $this->clientError(_m('Registration not allowed.'));
                return;
            }

            $invite = Invitation::staticGet($code);

            if (empty($invite)) {
                // TRANS: OpenID plugin message. No new user registration is allowed on the site without an invitation code, and the one provided was not valid.
                $this->clientError(_m('Not a valid invitation code.'));
                return;
            }
        }

        $nickname = $this->trimmed('newname');

        if (!Validate::string($nickname, array('min_length' => 1,
                                               'max_length' => 64,
                                               'format' => NICKNAME_FMT))) {
            // TRANS: OpenID plugin message. The entered new user name did not conform to the requirements.
            $this->showForm(_m('Nickname must have only lowercase letters and numbers and no spaces.'));
            return;
        }

        if (!User::allowed_nickname($nickname)) {
            // TRANS: OpenID plugin message. The entered new user name is blacklisted.
            $this->showForm(_m('Nickname not allowed.'));
            return;
        }

        if (User::staticGet('nickname', $nickname)) {
            // TRANS: OpenID plugin message. The entered new user name is already used.
            $this->showForm(_m('Nickname already in use. Try another one.'));
            return;
        }

        list($display, $canonical, $sreg) = $this->getSavedValues();

        if (!$display || !$canonical) {
            // TRANS: OpenID plugin server error. A stored OpenID cannot be retrieved.
            $this->serverError(_m('Stored OpenID not found.'));
            return;
        }

        # Possible race condition... let's be paranoid

        $other = oid_get_user($canonical);

        if ($other) {
            // TRANS: OpenID plugin server error.
            $this->serverError(_m('Creating new account for OpenID that already has a user.'));
            return;
        }

        Event::handle('StartOpenIDCreateNewUser', array($canonical, &$sreg));

        $location = '';
        if (!empty($sreg['country'])) {
            if ($sreg['postcode']) {
                # XXX: use postcode to get city and region
                # XXX: also, store postcode somewhere -- it's valuable!
                $location = $sreg['postcode'] . ', ' . $sreg['country'];
            } else {
                $location = $sreg['country'];
            }
        }

        if (!empty($sreg['fullname']) && mb_strlen($sreg['fullname']) <= 255) {
            $fullname = $sreg['fullname'];
        } else {
            $fullname = '';
        }

        if (!empty($sreg['email']) && Validate::email($sreg['email'], common_config('email', 'check_domain'))) {
            $email = $sreg['email'];
        } else {
            $email = '';
        }

        # XXX: add language
        # XXX: add timezone

        $args = array('nickname' => $nickname,
                      'email' => $email,
                      'fullname' => $fullname,
                      'location' => $location);

        if (!empty($invite)) {
            $args['code'] = $invite->code;
        }

        $user = User::register($args);

        $result = oid_link_user($user->id, $canonical, $display);

        Event::handle('EndOpenIDCreateNewUser', array($user, $canonical, $sreg));

        oid_set_last($display);
        common_set_user($user);
        common_real_login(true);
        if (isset($_SESSION['openid_rememberme']) && $_SESSION['openid_rememberme']) {
            common_rememberme($user);
        }
        unset($_SESSION['openid_rememberme']);
        common_redirect(common_local_url('showstream', array('nickname' => $user->nickname)),
                        303);
    }

    function connectUser()
    {
        $nickname = $this->trimmed('nickname');
        $password = $this->trimmed('password');

        if (!common_check_user($nickname, $password)) {
            // TRANS: OpenID plugin message.
            $this->showForm(_m('Invalid username or password.'));
            return;
        }

        # They're legit!

        $user = User::staticGet('nickname', $nickname);

        list($display, $canonical, $sreg) = $this->getSavedValues();

        if (!$display || !$canonical) {
            // TRANS: OpenID plugin server error. A stored OpenID cannot be found.
            $this->serverError(_m('Stored OpenID not found.'));
            return;
        }

        $result = oid_link_user($user->id, $canonical, $display);

        if (!$result) {
            // TRANS: OpenID plugin server error. The user or user profile could not be saved.
            $this->serverError(_m('Error connecting user to OpenID.'));
            return;
        }

        if (Event::handle('StartOpenIDUpdateUser', array($user, $canonical, &$sreg))) {
            oid_update_user($user, $sreg);
        }
        Event::handle('EndOpenIDUpdateUser', array($user, $canonical, $sreg));

        oid_set_last($display);
        common_set_user($user);
        common_real_login(true);
        if (isset($_SESSION['openid_rememberme']) && $_SESSION['openid_rememberme']) {
            common_rememberme($user);
        }
        unset($_SESSION['openid_rememberme']);
        $this->goHome($user->nickname);
    }

    function goHome($nickname)
    {
        $url = common_get_returnto();
        if ($url) {
            # We don't have to return to it again
            common_set_returnto(null);
	    $url = common_inject_session($url);
        } else {
            $url = common_local_url('all',
                                    array('nickname' =>
                                          $nickname));
        }
        common_redirect($url, 303);
    }

    function bestNewNickname($display, $sreg)
    {

        # Try the passed-in nickname

        if (!empty($sreg['nickname'])) {
            $nickname = $this->nicknamize($sreg['nickname']);
            if ($this->isNewNickname($nickname)) {
                return $nickname;
            }
        }

        # Try the full name

        if (!empty($sreg['fullname'])) {
            $fullname = $this->nicknamize($sreg['fullname']);
            if ($this->isNewNickname($fullname)) {
                return $fullname;
            }
        }

        # Try the URL

        $from_url = $this->openidToNickname($display);

        if ($from_url && $this->isNewNickname($from_url)) {
            return $from_url;
        }

        # XXX: others?

        return null;
    }

    function isNewNickname($str)
    {
        if (!Validate::string($str, array('min_length' => 1,
                                          'max_length' => 64,
                                          'format' => NICKNAME_FMT))) {
            return false;
        }
        if (!User::allowed_nickname($str)) {
            return false;
        }
        if (User::staticGet('nickname', $str)) {
            return false;
        }
        return true;
    }

    function openidToNickname($openid)
    {
        if (Auth_Yadis_identifierScheme($openid) == 'XRI') {
            return $this->xriToNickname($openid);
        } else {
            return $this->urlToNickname($openid);
        }
    }

    # We try to use an OpenID URL as a legal StatusNet user name in this order
    # 1. Plain hostname, like http://evanp.myopenid.com/
    # 2. One element in path, like http://profile.typekey.com/EvanProdromou/
    #    or http://getopenid.com/evanprodromou

    function urlToNickname($openid)
    {
        return common_url_to_nickname($openid);
    }

    function xriToNickname($xri)
    {
        $base = $this->xriBase($xri);

        if (!$base) {
            return null;
        } else {
            # =evan.prodromou
            # or @gratis*evan.prodromou
            $parts = explode('*', substr($base, 1));
            return $this->nicknamize(array_pop($parts));
        }
    }

    function xriBase($xri)
    {
        if (substr($xri, 0, 6) == 'xri://') {
            return substr($xri, 6);
        } else {
            return $xri;
        }
    }

    # Given a string, try to make it work as a nickname

    function nicknamize($str)
    {
        return common_nicknamize($str);
    }
}
